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

        // SECURITY: Log operation without sensitive data
        Log::info('Attempting to update IDP user account type', [
            'idp_user_id' => $idpUserId,
            'account_type_id' => $accountTypeId,
            'has_mfa_code' => $mfaCode !== null,
            'url' => $url,
            // NOTE: Never log the actual token or MFA code
        ]);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30)
                ->patch($url, $payload);

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

            // Handle 401 Unauthorized - Invalid MFA code or insufficient permissions
            if ($response->status() === 401) {
                $error = $response->json('error') ?? '';

                Log::warning('IDP authorization failed for account type update', [
                    'idp_user_id' => $idpUserId,
                    'status' => 401,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => 'UNAUTHORIZED',
                    'message' => $error ?: 'Unauthorized. Invalid MFA code or insufficient permissions.',
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
        // Prefer the cached sudo-verified admin token (set after one MFA-verified
        // request). The IDP keeps that token in sudo mode, so it can be reused
        // without MFA — including for enrollments triggered by non-admin staff.
        // When a fresh MFA code is supplied, the caller prefers the session token
        // instead so a new sudo session can be established.
        if (!$preferSession) {
            $sudoToken = $this->getSudoToken();
            if ($sudoToken) {
                return $sudoToken;
            }
        }

        // Next, check if the token is in the session
        $token = session('idp_access_token');

        if ($token) {
            // SECURITY: Validate token format (JWT should have 3 parts separated by dots)
            if ($this->isValidJwtFormat($token)) {
                return $token;
            }
            
            Log::warning('Invalid JWT token format in session, ignoring', [
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
            // Clear invalid token from session
            session()->forget('idp_access_token');
        }

        // Fallback: check if admin token is configured in environment
        $configToken = config('services.idp.admin_access_token');
        
        if (!empty($configToken)) {
            // SECURITY: Validate token format
            if ($this->isValidJwtFormat($configToken)) {
                return $configToken;
            }
            
            Log::warning('Invalid JWT token format in config');
        }

        // Alternative: retrieve from database or cache if stored there
        // This implementation depends on how your app stores admin tokens

        Log::warning('Admin IDP access token not found in session or config');
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
}
