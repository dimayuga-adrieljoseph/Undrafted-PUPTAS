<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;

class IdpUser implements Authenticatable, Arrayable
{
    /**
     * The unique identifier for the user from the IDP.
     *
     * @var string
     */
    protected $id;

    /**
     * All attributes returned by the IDP (name, email, role, etc).
     *
     * @var array
     */
    protected $attributes;

    public function __construct(array $attributes)
    {
        // Require the specific IDP ID or fallback to the generic 'id'
        $this->id = $attributes['idp_user_id'] ?? ($attributes['id'] ?? null);
        $this->attributes = $attributes;
    }

    /**
     * Dynamically retrieve attributes on the user.
     */
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get the name of the unique identifier for the user.
     * We don't have a database column, but Auth checks this internally.
     */
    public function getAuthIdentifierName()
    {
        return 'idp_user_id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier()
    {
        return $this->id;
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName()
    {
        return 'password';
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    /**
     * Get the password for the user.
     * There are no local passwords in IDP-only auth.
     */
    public function getAuthPassword()
    {
        return ''; // No password
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken()
    {
        return null; // Remember me handled by IDP/Refresh tokens
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value)
    {
        // Not used
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName()
    {
        return '';
    }
}
