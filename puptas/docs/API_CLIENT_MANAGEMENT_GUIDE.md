# Internal Guide: API Client Management

The **API Client** feature is a highly secure, administrative module within the PUPTAS Super Admin Dashboard. It allows authorized personnel (Super Admins) to generate and manage OAuth 2.0 Client Credentials for external systems (e.g., Medical Clinics, University Guidance Systems, Microsites) that need to communicate with the PUPTAS backend.

This system is built on top of **Laravel Passport** and implements a **Zero-Trust Machine-to-Machine (M2M) Architecture**.

---

## Key Features & Security Concepts

### 1. UUID-Based Identification
Instead of traditional auto-incrementing integers (which are vulnerable to enumeration attacks), PUPTAS API Clients use **Universally Unique Identifiers (UUIDs)**. This ensures that attackers cannot guess active client IDs.

### 2. Scope-Based Access Authorization
Every API Client must be assigned strict "Scopes" upon creation. Scopes define exactly what an external system is allowed to do.
*   `medical-read`: Permits fetching eligible medical applicants.
*   `medical-write`: Permits pushing medical statuses (cleared/failed) back to PUPTAS.
*   `student-read`: Permits reading officially enrolled student data.
*   `program-read`: Permits fetching the list of active academic programs.

*Note: If a client tries to perform an action outside its assigned scope, the system immediately returns a `403 Forbidden`.*

### 3. One-Time Secret Display
When an API Client is successfully created, the **Client Secret** is displayed on the screen **only once**. 
*   PUPTAS stores a cryptographically hashed version of the secret.
*   If the Administrator loses the secret, it cannot be retrieved. The old client must be revoked/deleted and a new one generated.

### 4. Comprehensive Audit Logging
Every action taken in the API Client Manager is strictly recorded in the `audit_logs` table under the **System Config** category.
*   **Logged events include:** Client creation, scope assignment, and client deletion.
*   Logs capture the Admin's name, ID, IP address, and timestamp.

---

## How to Manage API Clients

### Creating a New Client
1. Log in to PUPTAS using a **Super Admin** account.
2. Navigate to **API Clients** in the sidebar.
3. Click the **"Create New Client"** button.
4. Fill out the application details:
   *   **Name**: A recognizable name for the external system (e.g., `Ospital ng Maynila - Medical API`).
   *   **Scopes**: Check the boxes corresponding to the exact permissions the system requires.
5. Click **"Create"**.
6. **CRITICAL**: A modal will appear displaying the new `Client ID` and the unencrypted `Client Secret`. **Copy these and securely transmit them to the external developer immediately.** Once this window is closed, the secret is gone forever.

### Distributing Credentials
Never send the `Client ID` and `Client Secret` in the same medium. 
*   *Best Practice*: Send the Client ID via Email, and the Client Secret via a secure, self-destructing message app or a direct physical/virtual meeting. 
*   Direct developers to the appropriate **Developer Handoff Guide** (located in the `/docs` folder) based on their assigned scopes.

### Revoking/Deleting a Client
If an external system is decommissioned, or if you suspect a Client Secret has been compromised:
1. Navigate to the **API Clients** page.
2. Locate the compromised/old client in the table.
3. Click **"Revoke"** or **"Delete"**.
4. The system will immediately invalidate any active OAuth tokens associated with that client, instantly cutting off their access to PUPTAS.

---

## Advanced Configurations & Security

### 1. Medical Webhook Secret (HMAC)
While generating an API Client grants OAuth access, the **Medical System** requires an additional layer of security to submit results (`medical-write`). 
*   The Super Admin must generate a cryptographically strong string (e.g., using `openssl rand -hex 32`).
*   This string must be placed in the PUPTAS `.env` file under `MEDICAL_WEBHOOK_SECRET`.
*   This exact same string must be shared securely with the Medical System developers. They will use it to hash their JSON payload.
*   **Without this matching HMAC secret, the API Client's OAuth token will still result in a `403 Invalid Signature` error when submitting medical results.**

### 2. API Rate Limits by Scope
The API Clients are strictly bound to system-level rate limits to protect PUPTAS from brute-force or runaway-script attacks:
*   **Medical System (`medical-read` / `medical-write`)**: 5 req/second, 80 req/minute, 100 req/day.
*   **Guidance System (`student-read`)**: 5 req/second, 1000 req/minute, 2000 req/day.
*   **Program System (`program-read`)**: 5 req/second, 50 req/day (Highly cached).

### 3. Disaster Recovery (Global Key Rotation)
If you suspect that the Core PUPTAS server itself has been compromised, or if you need to perform an annual global security rotation, you must rotate the underlying Passport encryption keys.
1. Run `php artisan passport:keys --force` on the production server.
2. This generates fresh `oauth-private.key` and `oauth-public.key` files in the `storage/` directory.
3. **Warning**: Doing this will instantly invalidate **ALL** currently active access tokens across all systems. External systems will automatically request new tokens using their existing Client ID and Secret if they are programmed correctly.
