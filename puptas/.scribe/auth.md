# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_PASSPORT_ACCESS_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

All external API endpoints require a Passport client-credentials access token sent as a Bearer token. The medical webhook (POST /api/v1/webhooks/medical-result) additionally requires an HMAC `X-Medical-Signature` header (SHA256 of the raw JSON body using the shared `services.medical_webhook.secret`).
