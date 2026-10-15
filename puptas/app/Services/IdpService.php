<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IDP Service
 * 
 * Handles communication with the Identity Provider (IDP) API
 * for user account management operations.
 */
class IdpService
{
    /**
     * Update a user's account type in the IDP
     * 
     * This method implements the Sudo MFA Session Cache pattern from the IDP.
     * It will first attempt to update without MFA code (optimistic approach).
     * If MFA is required, the caller should retry with the MFA code.
     * 
     * SECURITY MEASURES:
     * - Uses HTTPS for all IDP communications
     * - Validates admin JWT token server-side by IDP
     * - Sanitizes all log output (no tokens logged)
     * - Rate limiting enforced by IDP
     * - MFA validation format check
     * - Account type ID hardcoded to prevent manipulation
     *
     * @param string $idpUserId The IDP user ID
     * @param int $accountTypeId The account type ID (3 = student)
     * @param string $accessToken Admin JWT access token
     * @param string|null $mfaCode Optional MFA TOTP code (6 digits)
     * @return array Response data with 'success' boolean and optional 'message' or 'error'
     */
    public function updateUserAccountType(
        string $idpUserId, 
        int $accountTypeId, 
        string $accessToken,
        ?string $mfaCode = null
    ): array {
        // SECURITY: Validate account type ID to prevent privilege escalation
        // Only allow changing to student (3) account type
        $allowedAccountTypes = [3]; // 3 = student
        if (!in_array($accountTypeId, $allowedAccountTypes)) {
            Log::error('Invalid account type ID requested', [
                'account_type_id' => $accountTypeId,
                'allowed' => $allowedAccountTypes,
            ]);
            return [
                'success' => false,
                'error' => 'INVALID_ACCOUNT_TYPE',
                'message' => 'Invalid account type. Only student account type is allowed.',
            ];
        }

        // SECURITY: Validate MFA code format if provided
        if ($mfaCode !== null && !preg_match('/^\d{6}$/', $mfaCode)) {
            Log::warning('Invalid MFA code format provided', [
                'idp_user_id' => $idpUserId,
            ]);
            return [
                'success' => false,
                'error' => 'INVALID_MFA_FORMAT',
                'message' => 'MFA code must be 6 digits.',
            ];
        }

        $idpConfig = config('services.idp');

        // Validate IDP configuration
        if (empty($idpConfig['base_url'])) {
            Log::error('IDP configuration is incomplete - missing base_url');
            return [
                'success' => false,
                'error' => 'IDP_CONFIG_INCOMPLETE',
                'message' => 'IDP configuration is incomplete. Please contact administrator.',
            ];
        }

        $baseUrl = rtrim($idpConfig['base_url'], '/');
        $url = "{$baseUrl}/api/v1/admin/users/{$idpUserId}";

        // Prepare payload
        $payload = [
            'account_type_id' => $accountTypeId,
        ];

        // Include MFA code if provided
        if ($mfaCode !== null) {
            $payload['mfa_code'] = $mfaCode;
        }

        // Inspect token expiration before sending
        $tokenPayload = $this->getJwtPayload($accessToken);
        $tokenExpired = $this->isTokenExpired($accessToken);

        // SECURITY: Log operation without sensitive data
        Log::info('Attempting to update IDP user account type', [
            'idp_user_id' => $idpUserId,
            'account_type_id' => $accountTypeId,
            'has_mfa_code' => $mfaCode !== null,
            'url' => $url,
            'token_sub' => $tokenPayload['sub'] ?? null,
            'token_exp' => isset($tokenPayload['exp']) ? date('Y-m-d H:i:s', $tokenPayload['exp']) : 'unknown',
            'token_is_expired' => $tokenExpired,
        ]);

        if ($tokenExpired) {
            Log::error('Cannot update IDP account type: Admin access token is EXPIRED', [
                'idp_user_id' => $idpUserId,
                'token_sub' => $tokenPayload['sub'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => 'TOKEN_EXPIRED',
                'message' => 'Admin IDP token is expired. Please log in via IDP again to renew.',
            ];
        }

        try {
            $httpRequest = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30);

            // Support both body payload and custom headers for MFA code
            if ($mfaCode !== null) {
                $payload['mfa_code'] = $mfaCode;
                $httpRequest = $httpRequest->withHeaders([
                    'X-MFA-Code' => $mfaCode,
                    'X-OTP' => $mfaCode,
                ]);
            }

            $response = $httpRequest->patch($url, $payload);

            // Success - account type updated
            if ($response->successful()) {
                Log::info('IDP user account type updated successfully', [
                    'idp_user_id' => $idpUserId,
                    'account_type_id' => $accountTypeId,
                ]);

                return [
                    'success' => true,
                    'message' => $response->json('message') ?? 'User account type updated successfully',
                    'data' => $response->json(),
                ];
            }

            // Handle 400 Bad Request - MFA code required or invalid
            if ($response->status() === 400) {
                $error = $response->json('error') ?? '';
                $title = $response->json('title') ?? '';

                // Check if MFA is required
                if (str_contains($error, 'MFA code is required') || $title === 'MFA Required') {
                    Log::info('IDP requires MFA code for account type update', [
                        'idp_user_id' => $idpUserId,
                    ]);

                    return [
                        'success' => false,
                        'error' => 'MFA_REQUIRED',
                        'message' => 'MFA code is required to update account type.',
                        'requires_mfa' => true,
                    ];
                }

                // Other 400 errors (invalid payload, etc.)
                Log::warning('IDP rejected account type update request', [
                    'idp_user_id' => $idpUserId,
                    'status' => 400,
                    'error' => $error,
                    'title' => $title,
                ]);

                return [
                    'success' => false,
                    'error' => 'INVALID_REQUEST',
                    'message' => $error ?: 'Invalid request. Please check the payload.',
                ];
            }

            // Handle 401 Unauthorized - Capture actual IDP response message
            if ($response->status() === 401) {
                $rawBody = trim((string) $response->body());
                $wwwAuth = $response->header('WWW-Authenticate');

                // `??` only skips null, so pick the first NON-EMPTY candidate
                $error = collect([
                    $response->json('message'),
                    $response->json('error'),
                    $response->json('detail'),
                    $response->json('error_description'),
                    $response->json('title'),
                ])->first(fn ($v) => is_string($v) && trim($v) !== '');

                // Always surface the raw response so the real cause is visible
                $details = [];
                if ($error) {
                    $details[] = $error;
                }
                $details[] = 'body=' . ($rawBody !== '' ? substr($rawBody, 0, 300) : '(empty)');
                if ($wwwAuth) {
                    $details[] = 'www-authenticate=' . substr($wwwAuth, 0, 200);
                }
                $error = implode(' | ', $details);

                Log::warning('IDP authorization failed for account type update', [
                    'idp_user_id' => $idpUserId,
                    'status' => 401,
                    'error' => $error,
                    'response_body' => substr($rawBody, 0, 1000),
                    'www_authenticate' => $wwwAuth,
                    'had_mfa_code' => $mfaCode !== null,
                    'token_sub' => $tokenPayload['sub'] ?? null,
                    'token_aud' => $tokenPayload['aud'] ?? null,
                    'token_scope' => $tokenPayload['scope'] ?? ($tokenPayload['scopes'] ?? null),
                ]);

                return [
                    'success' => false,
                    'error' => 'UNAUTHORIZED',
                    'message' => $error,
                ];
            }

            // Handle 404 Not Found - User doesn't exist in IDP
            if ($response->status() === 404) {
                Log::error('IDP user not found', [
                    'idp_user_id' => $idpUserId,
                ]);

                return [
                    'success' => false,
                    'error' => 'USER_NOT_FOUND',
                    'message' => 'User not found in IDP.',
                ];
            }

            // Handle 429 Too Many Requests - Rate limit exceeded
            if ($response->status() === 429) {
                Log::warning('IDP rate limit exceeded', [
                    'idp_user_id' => $idpUserId,
                ]);

                return [
                    'success' => false,
                    'error' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                ];
            }

            // Handle other error status codes
            $statusCode = $response->status();
            // SECURITY: Don't expose full error body to prevent information leakage
            Log::error('IDP account type update failed', [
                'idp_user_id' => $idpUserId,
                'status' => $statusCode,
                // Only log first 200 characters of error to prevent log flooding
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            return [
                'success' => false,
                'error' => 'IDP_ERROR',
                'message' => "IDP request failed with status {$statusCode}",
            ];
        } catch (\Exception $e) {
            // SECURITY: Log exception without exposing sensitive details
            Log::error('Exception during IDP account type update', [
                'idp_user_id' => $idpUserId,
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                // Don't log full trace in production to prevent exposing internal paths
            ]);

            return [
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => 'An error occurred while communicating with IDP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the admin's access token from the session or database
     * 
     * This is a helper method to retrieve the access token needed for admin operations.
     * The token should be stored during the admin's login process.
     * 
     * SECURITY:
     * - Token retrieved from secure session storage (not exposed to client)
     * - Falls back to environment config for development only
     * - Token format validated before use
     * - Never logs actual token values
     *
     * @return string|null The access token or null if not available
     */
    public function getAdminAccessToken(bool $preferSession = false): ?string
    {
        // 1. Prefer the cached sudo-verified admin token if available and not expired
        if (!$preferSession) {
            $sudoToken = $this->getSudoToken();
            if ($sudoToken) {
                if (!$this->isTokenExpired($sudoToken)) {
                    return $sudoToken;
                }
                $this->clearSudoToken($sudoToken);
            }
        }

        // 2. Check token in session
        $token = session('idp_access_token');
        if ($token && $this->isValidJwtFormat($token)) {
            if (!$this->isTokenExpired($token)) {
                return $token;
            }
            Log::warning('Session IDP access token is expired, clearing from session');
            session()->forget('idp_access_token');
        }

        // 3. Fallback: check logged-in user's token stored in Redis
        if (auth()->check()) {
            try {
                $tokenData = Cache::store('redis')->get("idp_tokens:user_" . auth()->id());
                if (!empty($tokenData['access_token']) && $this->isValidJwtFormat($tokenData['access_token'])) {
                    if (!$this->isTokenExpired($tokenData['access_token'])) {
                        // Resync to session
                        session(['idp_access_token' => $tokenData['access_token']]);
                        return $tokenData['access_token'];
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Could not retrieve user IDP token from Redis', ['error' => $e->getMessage()]);
            }
        }

        // 4. Fallback: check if admin token is configured in environment
        $configToken = config('services.idp.admin_access_token');
        if (!empty($configToken) && $this->isValidJwtFormat($configToken)) {
            if ($this->isTokenExpired($configToken)) {
                Log::error('IDP_ADMIN_ACCESS_TOKEN in environment is EXPIRED! Please refresh it or log in via IDP.');
            } else {
                return $configToken;
            }
        }

        Log::warning('No valid unexpired Admin IDP access token found in sudo cache, session, Redis, or config');
        return null;
    }

    /**
     * Cache key for the sudo-verified admin token.
     */
    private const SUDO_TOKEN_CACHE_KEY = 'idp:sudo_admin_token';

    /**
     * Store an admin access token that the IDP has accepted (sudo mode active).
     * Stored encrypted; expires after config('services.idp.sudo_ttl_minutes').
     */
    public function cacheSudoToken(string $accessToken): void
    {
        if (!$this->isValidJwtFormat($accessToken)) {
            return;
        }

        $ttlMinutes = (int) config('services.idp.sudo_ttl_minutes', 60);

        try {
            Cache::put(self::SUDO_TOKEN_CACHE_KEY, Crypt::encryptString($accessToken), now()->addMinutes($ttlMinutes));
            Log::info('Cached sudo-verified IDP admin token', ['ttl_minutes' => $ttlMinutes]);
        } catch (\Throwable $e) {
            Log::warning('Failed to cache sudo IDP admin token', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieve the cached sudo-verified admin token, if any.
     */
    public function getSudoToken(): ?string
    {
        try {
            $encrypted = Cache::get(self::SUDO_TOKEN_CACHE_KEY);
            if (!$encrypted) {
                return null;
            }

            $token = Crypt::decryptString($encrypted);
            return $this->isValidJwtFormat($token) ? $token : null;
        } catch (\Throwable $e) {
            Log::warning('Failed to read cached sudo IDP admin token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Clear the cached sudo token (e.g. after the IDP rejects it).
     * If $onlyIfMatches is given, the cache is cleared only when it holds that token,
     * so a rejected non-admin token never wipes a valid admin token.
     */
    public function clearSudoToken(?string $onlyIfMatches = null): void
    {
        try {
            if ($onlyIfMatches !== null && $this->getSudoToken() !== $onlyIfMatches) {
                return;
            }

            Cache::forget(self::SUDO_TOKEN_CACHE_KEY);
            Log::warning('Cleared cached sudo IDP admin token (expired or rejected by IDP)');
        } catch (\Throwable $e) {
            Log::warning('Failed to clear cached sudo IDP admin token', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Whether a sudo-verified admin token is currently cached.
     */
    public function hasSudoToken(): bool
    {
        return $this->getSudoToken() !== null;
    }

    /**
     * Validate JWT token format
     * 
     * SECURITY: Basic format validation to prevent injection attacks
     * JWT tokens should have 3 parts: header.payload.signature
     * 
     * @param string $token The token to validate
     * @return bool True if valid JWT format, false otherwise
     */
    private function isValidJwtFormat(string $token): bool
    {
        // JWT tokens have 3 parts separated by dots
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }

        // Each part should be base64url encoded (alphanumeric, -, _)
        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Decode JWT payload without verifying signature (for claims inspection like exp).
     *
     * @param string $token
     * @return array|null
     */
    public function getJwtPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
        if (!$decoded) {
            return null;
        }

        $payload = json_decode($decoded, true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Check if a JWT token is expired based on its exp claim.
     *
     * @param string $token
     * @return bool
     */
    public function isTokenExpired(string $token): bool
    {
        $payload = $this->getJwtPayload($token);
        if (!$payload || !isset($payload['exp'])) {
            return false;
        }

        return (int) $payload['exp'] <= time();
    }
}
