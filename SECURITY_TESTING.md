# Security Testing Plan

These tests are safe for a local lab. They are meant to teach how Laravel behavior becomes security telemetry.

Before running manual tests, migrate and seed MySQL:

```powershell
.\tools\php\php.exe artisan migrate:fresh --seed
```

Then start Laravel:

```powershell
.\tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Inspect security logs:

```powershell
Get-Content .\storage\logs\security.log -Tail 50
```

All seeded users use this fake password:

```text
FinBankLab123!
```

## 1. Failed Login

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/login `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d "{\"email\":\"michael@finbank.test\",\"password\":\"wrong-password\"}"
```

Expected response: `401 Unauthorized`.

Expected log event: `login_failed`.

Security significance: one failed login may be user error. Many failed logins from the same IP or against the same account may indicate brute force.

## 2. Successful Login

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/login `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d "{\"email\":\"michael@finbank.test\",\"password\":\"FinBankLab123!\"}"
```

Expected response: `200 OK` with a token.

Expected log event: `login_success`.

Security significance: useful for tracking account activity and detecting successful login after many failures.

## 3. Repeated Failed Logins

Request: run the failed login request several times quickly.

Expected response: repeated `401 Unauthorized`, then possibly `429 Too Many Requests`.

Expected log events: `login_failed`, then `rate_limit_triggered` if the limiter blocks the request.

Security significance: repeated failures may indicate password guessing or credential stuffing.

## 4. Successful Login After Failures

Request: run several failed logins, then run a successful login.

Expected response: `200 OK` for the successful login.

Expected log events: multiple `login_failed`, then `login_success`.

Security significance: Wazuh can later correlate this as a possible compromised account pattern.

## 5. Normal User Attempts Admin Endpoint

First log in as Michael and save the token.

Request:

```powershell
curl.exe http://127.0.0.1:8000/api/admin/users `
  -H "Accept: application/json" `
  -H "Authorization: Bearer USER_TOKEN"
```

Expected response: `403 Forbidden`.

Expected log event: `authorization_denied`.

Security significance: proves the server enforces authorization. Hidden UI buttons are not security controls.

## 6. Admin Lists Users

First log in as Admin One.

Request:

```powershell
curl.exe http://127.0.0.1:8000/api/admin/users `
  -H "Accept: application/json" `
  -H "Authorization: Bearer ADMIN_TOKEN"
```

Expected response: `200 OK`.

Expected log event: none required for normal read.

Security significance: admin access is allowed only after authentication and admin authorization.

## 7. User Attempts Another User's Transaction

Create a transaction as Michael to David. Then log in as Grace and request that transaction ID.

Request:

```powershell
curl.exe http://127.0.0.1:8000/api/transactions/TRANSACTION_ID `
  -H "Accept: application/json" `
  -H "Authorization: Bearer GRACE_TOKEN"
```

Expected response: `403 Forbidden`.

Expected log event: `authorization_denied`.

Security significance: this tests IDOR/BOLA defense. A valid token does not allow access to every object.

## 8. User Attempts Mass Assignment Through Profile Update

Request:

```powershell
curl.exe -X PUT http://127.0.0.1:8000/api/profile `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer USER_TOKEN" `
  -d "{\"name\":\"Michael Updated\",\"email\":\"michael.updated@finbank.test\",\"role\":\"admin\",\"is_active\":false}"
```

Expected response: `200 OK`.

Expected log event: `profile_updated`.

Expected database result: name/email change, but role remains `user` and `is_active` remains true.

Security significance: demonstrates why ordinary update endpoints must not trust `$request->all()`.

## 9. Large Transaction

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/transactions `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer USER_TOKEN" `
  -d "{\"recipient_id\":2,\"amount\":1500000,\"currency\":\"NGN\",\"description\":\"Large lab transfer\"}"
```

Expected response: `201 Created`.

Expected log events: `transaction_created` and `large_transaction_detected`.

Security significance: not real fraud detection. This is a simple signal for SIEM detection practice.

## 10. Invalid Transaction Input

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/transactions `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer USER_TOKEN" `
  -d "{\"recipient_id\":2,\"amount\":-10,\"currency\":\"USD\"}"
```

Expected response: `422 Validation Error`.

Expected log event: `invalid_input`.

Security significance: invalid input may be a mistake, probing, or abuse. The log records fields, not secrets.

## 11. Role Change

Request:

```powershell
curl.exe -X PUT http://127.0.0.1:8000/api/admin/users/1/role `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer ADMIN_TOKEN" `
  -d "{\"role\":\"admin\"}"
```

Expected response: `200 OK`.

Expected log event: `role_changed`.

Security significance: role changes are high-value audit events. Logs should include actor, target, old role, and new role.

## 12. Account Disable

Request:

```powershell
curl.exe -X PUT http://127.0.0.1:8000/api/admin/users/1/status `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -H "Authorization: Bearer ADMIN_TOKEN" `
  -d "{\"is_active\":false}"
```

Expected response: `200 OK`.

Expected log event: `account_status_changed`.

Security significance: account disabling can be a security response or suspicious admin activity.

## 13. Disabled Account Login

After disabling Michael, try to log in as Michael.

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/login `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d "{\"email\":\"michael@finbank.test\",\"password\":\"FinBankLab123!\"}"
```

Expected response: `403 Forbidden`.

Expected log event: `login_failed` with result `account_inactive`.

Security significance: a correct password should not be enough when the account is disabled.

## 14. Rate Limit Trigger

Request: send more than five login attempts in one minute for the same email and IP.

Expected response: `429 Too Many Requests`.

Expected log event: `rate_limit_triggered`.

Security significance: rate limiting slows brute force and creates telemetry for detection.

## 15. Repeated 404 Requests

Request:

```powershell
curl.exe http://127.0.0.1:8000/api/not-real-1 -H "Accept: application/json"
curl.exe http://127.0.0.1:8000/api/not-real-2 -H "Accept: application/json"
curl.exe http://127.0.0.1:8000/api/not-real-3 -H "Accept: application/json"
```

Expected response: `404 Not Found`.

Expected log event: no Laravel security event required by default.

Security significance: repeated 404s are usually better detected from web server logs such as Nginx access logs.

## 16. Sensitive File Probing

Request:

```powershell
curl.exe http://127.0.0.1:8000/.env
curl.exe http://127.0.0.1:8000/.git/config
curl.exe http://127.0.0.1:8000/config/.env
curl.exe http://127.0.0.1:8000/backup.zip
curl.exe http://127.0.0.1:8000/database.sql
```

Expected response: `404 Not Found` or `403 Forbidden`.

Expected log event: no Laravel application security event required by default.

Security significance: these probes should later be detected from Nginx and Wazuh logs. The app must never return real secrets.

## 17. Logout

Request:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/logout `
  -H "Accept: application/json" `
  -H "Authorization: Bearer USER_TOKEN"
```

Expected response: `200 OK`.

Expected log event: `logout`.

Security significance: logout revokes the current token and records the session ending.

## 18. Application Error Handling

Do not intentionally break production-like systems for testing. In this local lab, a developer can temporarily create a route that throws an exception, verify the API returns a safe `500`, then remove the test route.

Expected response:

```json
{
  "message": "Internal server error"
}
```

Expected log event: `application_error`.

Security significance: clients should not receive stack traces, `.env` values, database credentials, or secrets.
