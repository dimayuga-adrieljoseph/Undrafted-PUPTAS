You are an expert full stack developer. The backend is built with Laravel and the frontend uses Vue. Your task is to implement comprehensive error handling across the application.

The goal is:

Show generic, user-friendly error messages to users.
Log detailed technical errors internally for debugging.

Do not change business logic unless necessary for stability.

Backend (Laravel)
1. Centralized Error Handling
Use Laravel’s global exception handler (app/Exceptions/Handler.php) to catch all unhandled exceptions.
Ensure all exceptions return a consistent JSON response format:
{
  "success": false,
  "message": "Something went wrong. Please try again later.",
  "errorCode": "INTERNAL_ERROR"
}
2. Logging
Log detailed technical information internally:
Exception message
Stack trace
Timestamp
Endpoint and HTTP method
Request data (excluding sensitive fields)
Authenticated user ID (if available)
Use structured logging and avoid logging passwords, tokens, or secrets.
3. Validation and Common Errors
Return safe, consistent responses for:
Validation errors (422)
Authentication and authorization failures (401, 403)
Resource not found (404)
Server errors (500)
Validation responses should include field errors without exposing internal details.
4. Security and Environment
Ensure APP_DEBUG=false in production.
Do not expose stack traces, SQL errors, or system paths in API responses.
Frontend (Vue)
1. API Error Handling
Use a global HTTP interceptor (e.g., Axios interceptor) to catch API errors.
Display only the generic message returned from the backend.
Do not display raw error objects or stack traces.
2. User Experience
Show clear, generic error messages such as:
“Unable to load data. Please try again.”
“Your request could not be completed.”
Provide retry actions where appropriate.
Prevent UI crashes due to undefined or null data.
General Requirements
Maintain consistent error response structure across all endpoints.
Ensure the system fails gracefully.
Do not modify existing business logic unless required for error handling.

Output:

Updated Laravel backend code (global exception handler, response structure).
Vue frontend code (global error handling/interceptor).
Explanation of the changes.