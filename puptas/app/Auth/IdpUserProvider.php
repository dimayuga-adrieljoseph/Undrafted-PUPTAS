<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class IdpUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     * Instead of a DB, we simply recreate the IdpUser from the id stored in the Laravel session.
     * To be safe, we retrieve the full user profile stored during token exchange.
     */
    public function retrieveById($identifier)
    {
        $idpProfile = session('idp_user_profile');

        if ($idpProfile && isset($idpProfile['idp_user_id']) && $idpProfile['idp_user_id'] === $identifier) {
            return new IdpUser($idpProfile);
        }

        return null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token)
    {
        // We don't use remember tokens locally with IDP auth
        return null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Not used
    }

    /**
     * Retrieve a user by the given credentials.
     * Only used if manually attempting to log in with an array of credentials (e.g. email/password).
     * Since login happens strictly via IDP callback, this method is largely unused.
     */
    public function retrieveByCredentials(array $credentials)
    {
        return null;
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return false;
    }

    /**
     * Rehash the user's password if required.
     * (Required by Laravel 11+ UserProvider interface)
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // Not used in IDP auth
    }
}
