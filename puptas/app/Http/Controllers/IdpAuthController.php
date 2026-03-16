<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdpAuthController extends Controller
{
    /**
     * Maps IDP role names to local PUPTAS role_id numbers.
     * Adjust these strings to match exactly what the IDP sends.
     */
    private array $roleMap = [
        'applicant'   => 1,
        'admin'       => 2,
        'evaluator'   => 3,
        'interviewer' => 4,
        'medical'     => 5,
        'registrar'   => 6,
        'superadmin'  => 7,
    ];

    /**
     * Redirect user to IDP login page.
     * 
     * This initiates the OAuth2 authorization flow by redirecting
     * the user to the Identity Provider's login/authorization page.
     * Only sends client_id as per IDP requirements.
     */
    public function redirect()
    {
        $idpConfig = config('services.idp');
        
        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['client_id']) || empty($idpConfig['base_url'])) {
            \Log::error('IDP configuration is missing or incomplete', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'IDP configuration is missing. Please contact administrator.'
            ]);
        }

        $state = Str::random(40);
        session(['idp_oauth_state' => $state]);

        $authorizeQuery = [
            'client_id' => $idpConfig['client_id'],
            'response_type' => 'code',
            'state' => $state,
        ];

        if (!empty($idpConfig['redirect_uri'])) {
            $authorizeQuery['redirect_uri'] = $idpConfig['redirect_uri'];
        }

        // Keep scope optional because some IDPs reject unknown scopes.
        if (!empty($idpConfig['scope'])) {
            $authorizeQuery['scope'] = $idpConfig['scope'];
        }

        $authorizeUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/authorize?' . http_build_query($authorizeQuery);

        \Log::info('Redirecting to IDP authorization endpoint', [
            'authorize_url' => $authorizeUrl,
            'client_id_sent' => true,
        ]);

        return redirect($authorizeUrl);
    }

    /**
     * Handle the OAuth2 callback from the IDP.
     * 
     * IDP sends user back here with ?code=xxxx
     * This method exchanges the code for tokens and returns them to the user.
     */
    public function callback(Request $request)
    {
        $expectedState = session('idp_oauth_state');
        $returnedState = $request->query('state');

        if (!empty($expectedState) && !empty($returnedState) && !hash_equals($expectedState, $returnedState)) {
            \Log::warning('IDP callback state mismatch', [
                'ip' => $request->ip(),
            ]);

            return redirect('/login')->withErrors([
                'idp' => 'Invalid IDP callback state. Please try signing in again.',
            ]);
        }

                // Capture authorization code from common callback parameter names.
                $code = $request->query('code')
                        ?? $request->query('authorization_code')
                        ?? $request->input('code');

        if (empty($code)) {
                        // Some IDPs return OAuth values in the URL hash fragment (#code=...),
                        // which never reaches the server. Serve a tiny bridge page to convert
                        // fragment params into query params and reload this callback route.
                        if (empty($request->query())) {
                                return response(<<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processing Sign-In...</title>
</head>
<body>
    <script>
        (function () {
            var hash = window.location.hash ? window.location.hash.substring(1) : '';
            var params = new URLSearchParams(hash);
            var code = params.get('code') || params.get('authorization_code');
            var state = params.get('state');
            var error = params.get('error');
            var errorDescription = params.get('error_description');

            if (code || error) {
                var query = new URLSearchParams(window.location.search);
                if (code) query.set('code', code);
                if (state) query.set('state', state);
                if (error) query.set('error', error);
                if (errorDescription) query.set('error_description', errorDescription);
                window.location.replace(window.location.pathname + '?' + query.toString());
                return;
            }

            window.location.replace('/login?idp_error=missing_authorization_code');
        })();
    </script>
</body>
</html>
HTML, 200)->header('Content-Type', 'text/html');
                        }

            \Log::warning('IDP callback received without authorization code', [
                'ip' => $request->ip(),
                'query_params' => $request->query(),
                                'full_url' => $request->fullUrl(),
            ]);

            return redirect('/login')->withErrors([
                'idp' => 'Authorization code is missing. Please try signing in again.',
            ]);
        }

        $idpConfig = config('services.idp');
        
        // Validate IDP configuration
        if (empty($idpConfig) || empty($idpConfig['base_url']) || empty($idpConfig['client_id']) || empty($idpConfig['client_secret'])) {
            \Log::error('IDP configuration is incomplete during callback', [
                'config_keys' => $idpConfig ? array_keys($idpConfig) : 'config not found',
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'IDP configuration is incomplete. Please contact administrator.',
            ]);
        }

        // Step 1: Exchange authorization code for tokens
        $tokenUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/auth/token';
        
        \Log::info('Exchanging authorization code for tokens', [
            'token_url' => $tokenUrl,
            'client_id' => $idpConfig['client_id'],
        ]);

        try {
            // Try common OAuth2 token exchange formats because IDP implementations vary.
            // Never log client_secret for security.
            $tokenPayload = [
                'client_id' => $idpConfig['client_id'],
                'client_secret' => $idpConfig['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
            ];

            if (!empty($idpConfig['redirect_uri'])) {
                $tokenPayload['redirect_uri'] = $idpConfig['redirect_uri'];
            }

            $attempts = [
                [
                    'label' => 'json_client_credentials',
                    'request' => fn() => Http::acceptJson()->timeout(30)->post($tokenUrl, $tokenPayload),
                ],
                [
                    'label' => 'form_client_credentials',
                    'request' => fn() => Http::acceptJson()->asForm()->timeout(30)->post($tokenUrl, $tokenPayload),
                ],
                [
                    'label' => 'form_basic_auth',
                    'request' => fn() => Http::acceptJson()
                        ->asForm()
                        ->withBasicAuth($idpConfig['client_id'], $idpConfig['client_secret'])
                        ->timeout(30)
                        ->post($tokenUrl, array_filter([
                            'code' => $code,
                            'grant_type' => 'authorization_code',
                            'redirect_uri' => $idpConfig['redirect_uri'] ?? null,
                        ])),
                ],
                [
                    'label' => 'minimal_form_payload',
                    'request' => fn() => Http::acceptJson()->asForm()->timeout(30)->post($tokenUrl, [
                        'client_id' => $idpConfig['client_id'],
                        'client_secret' => $idpConfig['client_secret'],
                        'code' => $code,
                    ]),
                ],
            ];

            $tokenResponse = null;
            $lastFailure = null;

            foreach ($attempts as $attempt) {
                $candidate = $attempt['request']();

                if ($candidate->successful()) {
                    $tokenResponse = $candidate;
                    \Log::info('IDP token exchange succeeded', [
                        'attempt' => $attempt['label'],
                    ]);
                    break;
                }

                $lastFailure = [
                    'attempt' => $attempt['label'],
                    'status_code' => $candidate->status(),
                    'response' => $candidate->json(),
                    'raw_body' => $candidate->body(),
                ];
            }

            if (!$tokenResponse) {
                \Log::error('IDP token exchange failed on all supported formats', [
                    'token_url' => $tokenUrl,
                    'last_failure' => $lastFailure,
                ]);

                $idpReason = $lastFailure['response']['error'] ?? null;
                $friendlyMessage = $idpReason === 'unauthorized'
                    ? 'IDP rejected client credentials or callback URL. Please verify IDP client settings.'
                    : 'Failed to authenticate with IDP. Please try again.';

                return redirect('/login')->withErrors([
                    'idp' => $friendlyMessage,
                ]);
            }

            $tokenData = $tokenResponse->json();
            
            // Verify tokens were returned
            $accessToken = $tokenData['access_token'] ?? null;
            $idToken = $tokenData['id_token'] ?? null;

            if (empty($accessToken)) {
                \Log::error('IDP token response missing access_token', [
                    'response_keys' => array_keys($tokenData),
                ]);
                
                return redirect('/login')->withErrors([
                    'idp' => 'Invalid token response from IDP.',
                ]);
            }

            \Log::info('Successfully obtained tokens from IDP', [
                'has_access_token' => !empty($accessToken),
                'has_id_token' => !empty($idToken),
                'token_type' => $tokenData['token_type'] ?? null,
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);

            // Step 2: Get user info using access token
            $userInfoUrl = rtrim($idpConfig['base_url'], '/') . '/api/v1/user';
            
            \Log::info('Fetching user info from IDP', [
                'user_info_url' => $userInfoUrl,
            ]);

            $userResponse = Http::withToken($accessToken)
                ->timeout(30)
                ->get($userInfoUrl);

            $userData = [];
            if ($userResponse->successful()) {
                $userData = $userResponse->json();
                \Log::info('Received user info from IDP', [
                    'idp_user_id' => $userData['id'] ?? null,
                    'email' => $userData['email'] ?? null,
                ]);
            } else {
                \Log::warning('Failed to fetch user info from IDP', [
                    'status_code' => $userResponse->status(),
                ]);
            }

            // Determine role from string/array payload variants.
            $rawRole = $userData['role'] ?? $userData['roles'] ?? null;
            if (is_array($rawRole)) {
                $rawRole = $rawRole[0] ?? null;
            }

            $normalizedRole = strtolower(trim((string) $rawRole));
            $roleId = $this->roleMap[$normalizedRole] ?? 1;

            $email = $userData['email'] ?? null;
            $idpUserId = $userData['id'] ?? null;

            if (empty($email)) {
                \Log::error('Unable to identify IDP user email.', [
                    'idp_user_id' => $idpUserId,
                    'user_response_keys' => array_keys($userData),
                ]);

                return redirect('/login')->withErrors([
                    'idp' => 'IDP user record is missing email.',
                ]);
            }

            $lookup = !empty($idpUserId)
                ? ['idp_user_id' => (string) $idpUserId]
                : ['email' => (string) $email];

            $user = User::updateOrCreate(
                $lookup,
                [
                    'idp_user_id' => $idpUserId ? (string) $idpUserId : null,
                    'email' => $email,
                    'firstname' => $userData['firstname'] ?? $userData['name'] ?? '',
                    'lastname' => $userData['lastname'] ?? '',
                    'role_id' => $roleId,
                    'password' => bcrypt(Str::random(32)),
                ]
            );

            Auth::login($user, true);
            $request->session()->regenerate();

            \Log::info('IDP user logged in successfully', [
                'user_id' => $user->id,
                'idp_user_id' => $user->idp_user_id,
                'role_id' => $user->role_id,
            ]);

            return redirect('/home');

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error('IDP connection error', [
                'error' => $e->getMessage(),
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'Unable to connect to IDP. Please try again later.',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('IDP authentication unexpected error', [
                'error' => $e->getMessage(),
            ]);
            
            return redirect('/login')->withErrors([
                'idp' => 'An unexpected error occurred during IDP login.',
            ]);
        }
    }
}