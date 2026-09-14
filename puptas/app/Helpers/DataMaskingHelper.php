<?php

namespace App\Helpers;

use App\Models\User;
use App\Enums\RoleId;

class DataMaskingHelper
{
    /**
     * Determine if presentation data should be masked for the given user.
     * Default is TRUE (Privacy by Default).
     */
    public static function shouldMask(?User $user = null, bool $unmaskRequested = false): bool
    {
        if ($unmaskRequested && static::canUnmask($user)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the given user has role clearance to unmask sensitive personal data.
     * Enforces strict server-side RBAC: Only SuperAdmin (7) and Admin (2) are permitted.
     */
    public static function canUnmask(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        return in_array((int) $user->role_id, [
            RoleId::SuperAdmin->value,
            RoleId::Admin->value,
        ], true);
    }

    /**
     * Mask a full name or single name (e.g. "Juan Carlos Dela Cruz" -> "J*** C*** D*** C***").
     */
    public static function maskName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $parts = preg_split('/\s+/', trim($name));
        $maskedParts = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $firstChar = mb_substr($part, 0, 1);
            $maskedParts[] = $firstChar . '***';
        }

        return implode(' ', $maskedParts);
    }

    /**
     * Mask an email address (e.g. "student.applicant@pup.edu.ph" -> "st****nt@pup.edu.ph").
     */
    public static function maskEmail(?string $email): string
    {
        if ($email === null || trim($email) === '') {
            return '';
        }

        $email = trim($email);
        $atPos = strrpos($email, '@');

        if ($atPos === false) {
            return substr($email, 0, 1) . '***';
        }

        $localPart = substr($email, 0, $atPos);
        $domainPart = substr($email, $atPos + 1);

        $len = strlen($localPart);
        if ($len <= 2) {
            $maskedLocal = substr($localPart, 0, 1) . '***';
        } elseif ($len <= 4) {
            $maskedLocal = substr($localPart, 0, 1) . '***' . substr($localPart, -1);
        } else {
            $maskedLocal = substr($localPart, 0, 2) . '****' . substr($localPart, -2);
        }

        return $maskedLocal . '@' . $domainPart;
    }

    /**
     * Mask a phone / contact number (e.g. "09123456789" -> "0912****789").
     */
    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $phone = trim($phone);
        $len = strlen($phone);

        if ($len <= 6) {
            return substr($phone, 0, 2) . '****';
        }

        return substr($phone, 0, 4) . '****' . substr($phone, -3);
    }

    /**
     * Mask a reference number (e.g. "REF-2026-12345" -> "REF-****-12345").
     */
    public static function maskReferenceNumber(?string $ref): string
    {
        if ($ref === null || trim($ref) === '') {
            return '';
        }

        $ref = trim($ref);
        $parts = explode('-', $ref);

        if (count($parts) >= 3) {
            // Keep prefix and suffix, mask middle segment
            $parts[1] = '****';
            return implode('-', $parts);
        }

        $len = strlen($ref);
        if ($len <= 6) {
            return substr($ref, 0, 2) . '****';
        }

        return substr($ref, 0, 3) . '****' . substr($ref, -4);
    }
}
