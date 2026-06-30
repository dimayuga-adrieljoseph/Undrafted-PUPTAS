# Guidance System Developer Handoff Guide

Welcome to the **PUPTAS Guidance API Integration Guide**! 

This guide is designed to help you securely connect the Guidance System to the PUPTAS Core System. We use a **Machine-to-Machine (M2M)** architecture, meaning your backend server will talk directly to our backend server without any user interaction required.

If you have any questions during your integration, please refer to this guide first.

---

## 🚀 Quick Overview

Before diving into the code, here are the key technical details you need to know:
- **Authentication Method**: OAuth 2.0 (Client Credentials Grant)
- **Base API URL**: `https://puptas.undraftedbsit2027.com/api/v1/`
- **Required Permission Scope**: `student-read`
- **Rate Limits**: 
  - 5 requests per second
  - 1,000 requests per minute
  - 2,000 requests per day

---

## 🔐 Step 1: Authentication (Getting your Access Token)

Because this is a server-to-server integration, we use the OAuth 2.0 **Client Credentials Grant**. 

You will be provided with two important credentials by the PUPTAS Administrator:
1. `client_id` (This looks like a UUID string)
2. `client_secret` (Keep this very safe and hidden!)

### How to request an Access Token
You need to make a `POST` request to our authentication server using your credentials to get a temporary "Access Token".

**Example Request:**
```http
POST /oauth/token
Host: puptas.undraftedbsit2027.com
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id={YOUR_UUID}&client_secret={YOUR_SECRET}&scope=student-read
```

**Example Response:**
```json
{
    "token_type": "Bearer",
    "expires_in": 31536000,
    "access_token": "eyJ0eXAiOi..."
}
```

> [!TIP]
> **Important:** Save the `access_token` string! You will need to attach it to the `Authorization` header of every API request you make moving forward. Tokens eventually expire, so be prepared to request a new one if you receive a `401 Unauthorized` error.

---

## 📡 Step 2: Fetching Student Data

The Guidance System requires granular read-access to student profiles. Currently, we support looking up students using two specific identifiers: their **Email Address** or their **Reference Number**.

> [!WARNING]
> We recently migrated away from using the IDP User ID for lookups. Please ensure you are using the new **Email** endpoint!

### The Endpoints

1. **Lookup by Email Address** (Recommended)
   - **Method:** `GET`
   - **Path:** `/api/v1/students/email/{email}`
   - **Example:** `/api/v1/students/email/juandelacruz@example.com`

2. **Lookup by Reference Number**
   - **Method:** `GET`
   - **Path:** `/api/v1/students/{referenceNumber}`
   - **Example:** `/api/v1/students/2026-GUI-5678`

3. **List All Students** *(Deprecated)*
   - **Method:** `GET`
   - **Path:** `/api/v1/students`
   - *Note: This endpoint is heavily paginated and will soon be removed. Please use the specific lookup endpoints above instead.*

---

### Example API Request (Using Email)

Here is how you make a request to fetch a student's data. Notice the `Authorization` header containing the token we got from Step 1.

```http
GET /api/v1/students/email/student@pup.edu.ph
Host: puptas.undraftedbsit2027.com
Accept: application/json
Authorization: Bearer eyJ0eXA...
```

### Example API Response (200 OK)

When a student is successfully found, you will receive a JSON payload that looks like this:

```json
{
    "data": {
        "idp_user_id": "b2c3d4e5...",
        "reference_number": "2026-GUI-5678",
        "email": "student@pup.edu.ph",
        "first_name": "Maria",
        "last_name": "Clara",
        "program": {
            "code": "BSPSY",
            "name": "Bachelor of Science in Psychology"
        },
        "lifecycle_status": "officially_enrolled"
    }
}
```

> [!IMPORTANT]
> **Who can be looked up?** 
> The API will **only** return data for students who are currently **officially enrolled**. If a student is still in the application process or their enrollment was cancelled, the API will return a `404 Not Found`.

---

## ❌ Error Handling & Troubleshooting

If something goes wrong, our API uses standard HTTP status codes to tell you why. Here is what they mean and how to fix them:

| Status Code | Error Message | What it means & How to fix it |
| :--- | :--- | :--- |
| **`401 Unauthorized`** | Missing or Expired Token | You either forgot to include the `Authorization` header, or your token has expired. You need to request a new token using your `client_id` and `client_secret`. |
| **`403 Forbidden`** | Invalid Scope | Your token is valid, but it doesn't have the `student-read` permission. Please contact the PUPTAS Admin to update your credentials. |
| **`404 Not Found`** | Student Not Found | The email or reference number provided does not match any student, OR the student exists but is **not officially enrolled** yet. |
| **`410 Gone`** | Deprecated Endpoint | You tried to use an endpoint that has been removed. Switch to the newer lookup endpoints. |
| **`429 Too Many Requests`** | Rate Limit Exceeded | You are sending requests too fast! Slow down and check the `X-RateLimit` headers in the response to see when you can try again. |

### Need Help?
If you are consistently getting errors or your credentials aren't working, please reach out to the PUPTAS Core Development team with your `client_id` and the exact error code you are receiving.
