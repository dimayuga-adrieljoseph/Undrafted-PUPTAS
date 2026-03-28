# Guidance System Handoff: PUPTAS Internal Programs API

Welcome! This guide outlines how the Guidance System (or any external integrated system) can autonomously fetch the list of officially active Programs from the PUPTAS admission system.

### Base Requirements
- **Format:** JSON
- **Authentication:** Bearer Token
- **Rate Limit:** 50 requests per day per IP address (also heavily limited per-minute and per-second to prevent abuse).

---

## 🚀 Endpoint Overview
**`GET /api/v1/programs`**

This endpoint returns a clean list of all programs in the system. It strictly returns only the `id`, `code`, and `name` of the program. 

### 🔐 Authentication (Required)
You must pass your securely provided API token in the **Authorization** header of your HTTP request. 

*If you do not pass a token, or if the token is invalid, you will receive a `401 Unauthorized` response. Failed authentication attempts are permanently audited.*

---

## 💻 Example Request (cURL)
```bash
curl -X GET "https://puptas.undraftedbsit2027.com/api/v1/programs" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer YOUR_PROVIDED_TOKEN_HERE"
```

---

## 📄 Example Response (200 OK)
The response contains a `data` array with objects for every program.

```json
{
    "data": [
        {
            "id": 1,
            "code": "BSIT",
            "name": "Bachelor of Science in Information Technology"
        },
        {
            "id": 2,
            "code": "BSBA-HRM",
            "name": "Bachelor of Science in Business Administration - Human Resource Management"
        }
    ]
}
```

---

## 🚫 Error Responses
* **`401 Unauthorized`:** You did not provide a token, or the token provided is incorrect.
* **`429 Too Many Requests`:** You have exceeded the 50-request daily limit, or you are hitting the endpoint too fast (exceeding the strict per-minute/per-second limits).

---

## 📞 Support & Debugging
If you are consistently receiving `401 Unauthorized` despite passing the token correctly as a `Bearer` token in the `Authorization` header, please verify that your token has not been rotated or revoked by the PUPTAS Administrators.
