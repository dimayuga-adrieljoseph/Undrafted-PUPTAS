<?php

namespace App\Helpers;

class ChatwootHelper
{
    /**
     * Generate HMAC hash for Chatwoot identity validation
     * 
     * @param string $identifier User identifier (email, user ID, etc.)
     * @return string|null HMAC hash or null if token not configured
     */
    public static function generateIdentityHash(string $identifier): ?string
    {
        $hmacToken = config('services.chatwoot.hmac_token');
        
        if (empty($hmacToken)) {
            return null;
        }
        
        return hash_hmac('sha256', $identifier, $hmacToken);
    }
    
    /**
     * Get Chatwoot widget configuration with identity validation
     * 
     * @param \App\Models\User $user
     * @return array
     */
    public static function getWidgetConfig($user): array
    {
        $config = [
            'websiteToken' => config('services.chatwoot.website_token'),
            'baseUrl' => config('services.chatwoot.base_url'),
        ];
        
        // Add user information if authenticated
        if ($user) {
            $identifier = $user->email; // or $user->id
            $identifierHash = self::generateIdentityHash($identifier);
            
            $config['user'] = [
                'email' => $user->email,
                'name' => $user->name,
                'identifier' => $identifier,
            ];
            
            // Add identity hash if available
            if ($identifierHash) {
                $config['user']['identifier_hash'] = $identifierHash;
            }
        }
        
        return $config;
    }
}
