# Privacy Consent Management

This guide explains how to manage privacy consent for users in the PUPT Admission Portal system.

## Overview

The privacy consent system ensures that all users accept the Terms and Conditions and Privacy Policy before using the application. Users who haven't accepted the terms will see a modal dialog on login that requires acceptance before they can proceed.

## How It Works

### Database Fields

The system uses two fields in the `users` table:
- `privacy_consent` (boolean): Whether the user has accepted the terms
- `privacy_consent_at` (timestamp): When the user accepted the terms

### Backend Logic

The `HandleInertiaRequests` middleware automatically shares privacy consent status with all Inertia pages:

```php
'privacy_consent' => [
    'required' => $request->user() ? !$request->user()->privacy_consent : false,
],
```

### Frontend Display

All layout components (AppLayout, ApplicantLayout, InterviewerLayout, etc.) watch for the privacy consent status and automatically display the Terms and Conditions modal when required.

## Privacy Consent Management Command

A dedicated Artisan command allows you to check and reset privacy consent status for users.

### Check Current Status

To view the current privacy consent status of all users:

```bash
php artisan privacy:reset
```

This displays:
- Total number of users
- Number of users with consent
- Number of users without consent
- List of up to 10 recent users without consent

**Example Output:**
```
Privacy Consent Status:

+-----------------+-------+
| Status          | Count |
+-----------------+-------+
| Total Users     | 7     |
| With Consent    | 3     |
| Without Consent | 4     |
+-----------------+-------+

Recent users without consent:
+----+---------------------+-----------------+-----------------+---------------------+
| ID | Email               | Name            | Privacy Consent | Created At          |
+----+---------------------+-----------------+-----------------+---------------------+
| 7  | applicant@gmail.com | John Esperanza  | No              | 2026-02-02 16:41:35 |
| 6  | dummyjm15@gmail.com | John Managbanag | No              | 2026-02-02 14:06:04 |
+----+---------------------+-----------------+-----------------+---------------------+
```

### Reset Privacy Consent for All Users

To reset privacy consent for **all users** (they'll need to accept terms again):

```bash
php artisan privacy:reset --all
```

**Warning:** This command requires confirmation before proceeding.

**What happens:**
- All users' `privacy_consent` field is set to `false`
- All users' `privacy_consent_at` field is set to `null`
- Users will see the Terms and Conditions modal on their next login

**Use cases:**
- Updated terms and conditions that require re-acceptance
- Privacy policy changes requiring new consent
- Compliance audits requiring fresh consent tracking

### Reset Privacy Consent for Specific User

To reset privacy consent for a single user:

```bash
php artisan privacy:reset --user-id=5
```

**Example:**
```bash
php artisan privacy:reset --user-id=7
# Output: Privacy consent reset for user: applicant@gmail.com
# Output: This user will see the terms and conditions modal on their next login.
```

**Use cases:**
- User requests to review terms again
- Testing the modal functionality
- Addressing specific user concerns

## Terms and Conditions Modal

### User Experience

When a user without privacy consent logs in:

1. **Modal Appears**: A non-dismissible modal overlays the entire interface
2. **Content Display**: Shows full terms and conditions and privacy policy
3. **Checkbox Required**: User must check "I Agree and acknowledge the Terms and Conditions"
4. **Actions Available**:
   - **Continue Button**: Accepts terms (only enabled when checkbox is checked)
   - **Cancel Button**: Logs the user out

### Modal Features

- **Z-index**: 9999 (highest priority, appears above all other elements)
- **Non-dismissible**: Users cannot close with ESC key or by clicking outside
- **Scrollable Content**: Terms content is scrollable for easy reading
- **Responsive Design**: Works on all screen sizes
- **Dark Mode Support**: Adapts to user's dark mode preference

## Registration Process

New users automatically accept privacy consent during registration:

```php
'privacy_consent' => true,
'privacy_consent_at' => now(),
```

This means newly registered users won't see the modal on their first login.

## API Endpoints

### Accept Privacy Consent

**Endpoint:** `POST /privacy-consent/accept`

**Authentication:** Required

**Response:**
```json
{
    "success": true
}
```

**Action:** Updates user's privacy_consent to true and records acceptance timestamp

### Check Privacy Consent

**Endpoint:** `GET /privacy-consent/check`

**Authentication:** Required

**Response:**
```json
{
    "has_consent": true,
    "consent_at": "2026-03-06 10:30:00"
}
```

## Troubleshooting

### Modal Not Appearing

If the terms modal isn't showing when expected:

1. **Check user's consent status:**
   ```bash
   php artisan privacy:reset
   ```

2. **Verify database migration:**
   ```bash
   php artisan migrate:status
   ```
   Look for: `2026_03_05_000000_add_privacy_consent_to_users_table`

3. **Check browser console** for JavaScript errors

4. **Clear browser cache** and rebuild assets:
   ```bash
   npm run build
   ```

5. **Reset specific user's consent** for testing:
   ```bash
   php artisan privacy:reset --user-id=1
   ```

### Modal Appearing When It Shouldn't

If the modal shows for users who have already accepted:

1. **Check database directly:**
   ```bash
   php artisan tinker
   >>> User::find(1)->privacy_consent
   >>> User::find(1)->privacy_consent_at
   ```

2. **Verify middleware is sharing correct data** in `HandleInertiaRequests.php`

3. **Check frontend watch logic** in layout components

## Compliance Notes

### Data Privacy Act of 2012 (Philippines)

The system is designed to comply with the Data Privacy Act of 2012 by:
- Obtaining explicit consent before data collection
- Recording timestamp of consent
- Allowing users to decline (by logging out)
- Providing clear information about data usage

### Consent Tracking

The `privacy_consent_at` timestamp field maintains an audit trail of when each user accepted the terms, which is important for:
- Legal compliance
- Audit requirements
- Proving consent in case of disputes

## Best Practices

1. **Regular Consent Reviews**: Reset consent when terms are updated significantly
2. **Clear Communication**: Notify users via email before resetting all consents
3. **Version Tracking**: Consider adding a terms version field for future enhancements
4. **Audit Logs**: The system already logs consent acceptance in the audit_logs table
5. **Testing**: Always test with `--user-id` before using `--all` flag

## File Locations

- **Command:** `app/Console/Commands/ResetPrivacyConsent.php`
- **Controller:** `app/Http/Controllers/PrivacyConsentController.php`
- **Middleware:** `app/Http/Middleware/HandleInertiaRequests.php`
- **Modal Component:** `resources/js/Pages/Modal/TermsandConditionsModal.vue`
- **Layouts:** `resources/js/Layouts/*Layout.vue`
- **Migration:** `database/migrations/2026_03_05_000000_add_privacy_consent_to_users_table.php`
- **Routes:** `routes/web.php` (search for privacy-consent)

## Future Enhancements

Potential improvements to consider:

1. **Version Tracking**: Add `terms_version` field to track which version user accepted
2. **Partial Consent**: Allow granular consent for different data uses
3. **Consent History**: Store historical consent records in separate table
4. **Email Notifications**: Send email when terms are updated
5. **Admin Dashboard**: Visual interface for consent management
6. **Export Reports**: Generate consent compliance reports

## Support

For issues or questions about privacy consent management, contact the development team or refer to the main project documentation.

---

**Last Updated:** March 6, 2026
**Version:** 1.0
