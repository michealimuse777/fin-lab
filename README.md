# FinBank Security Lab

FinBank Security Lab is a deliberately small Laravel fintech API for learning defensive web application security.

This is not a production fintech system. It uses fake users, fake transfers, fake credentials, and beginner-readable Laravel code.

## Purpose

This lab teaches:

- Laravel routes, middleware, controllers, validation, authentication, authorization, Eloquent, migrations, seeders, policies, and logging.
- Where common Laravel security problems happen.
- What evidence a defensive team should log.
- How application logs can later be collected by Wazuh on Ubuntu.

Do not put SSH detection, Ubuntu detection, malware, persistence, or exploit tooling inside Laravel. Laravel produces application telemetry. Ubuntu, Nginx, SSH, and Wazuh are later layers.

## Current Versions

This workspace was built with:

- Laravel Framework 13.25.0
- PHP 8.4.24 portable CLI in `tools/php`
- Composer 2.10.2 local `composer.phar`
- Laravel Sanctum 4.3.3

The application `.env` is configured for MySQL. The automated tests use in-memory SQLite from `phpunit.xml` so the API logic can be verified even when MySQL is not running.

## Fake Lab Password

All seeded lab users use:

```text
FinBankLab123!
```

Passwords are hashed with Laravel before storage:

```php
$user->password = Hash::make($password);
```

What this means:

- `$user` is one User object.
- `->password` accesses the password property on that object.
- `Hash` is Laravel's hashing class.
- `::` accesses something directly from a class.
- `make()` returns a password hash.
- The database stores the hash, not the plaintext password.

Login verifies the submitted password with:

```php
Hash::check($validated['password'], $user->password)
```

Never log passwords or API tokens.

## Seeded Users

| ID | Name | Email | Role |
| --- | --- | --- | --- |
| 1 | Michael | michael@finbank.test | user |
| 2 | David | david@finbank.test | user |
| 3 | Sarah | sarah@finbank.test | user |
| 4 | James | james@finbank.test | user |
| 5 | Grace | grace@finbank.test | user |
| 6 | Admin One | admin1@finbank.test | admin |
| 7 | Admin Two | admin2@finbank.test | admin |
| 8 | Admin Three | admin3@finbank.test | admin |

## Project Structure

```text
app/
  Http/
    Controllers/
      AuthController.php
      UserController.php
      TransactionController.php
      AdminController.php
    Middleware/
      AdminMiddleware.php
  Models/
    User.php
    Transaction.php
  Policies/
    TransactionPolicy.php

config/
  finbank.php
  logging.php

database/
  migrations/
    0001_01_01_000000_create_users_table.php
    2026_08_16_193645_create_personal_access_tokens_table.php
    2026_08_16_200000_create_transactions_table.php
  seeders/
    DatabaseSeeder.php

routes/
  api.php

storage/
  logs/
    laravel.log
    security.log
```

## Request Flow

```text
HTTP request
  -> Route
  -> Middleware
  -> Controller
  -> Validation
  -> Authentication
  -> Authorization
  -> Eloquent
  -> Database
  -> Security log
  -> JSON response
```

Example:

```text
POST /api/transactions
  -> routes/api.php
  -> auth:sanctum middleware
  -> TransactionController@store
  -> validate recipient_id, amount, currency, description
  -> get sender from $request->user()
  -> create Transaction with Eloquent
  -> write transaction_created to security.log
  -> return 201 Created
```

## PHP Syntax Notes

Variable:

```php
$name = 'Michael';
```

`$name` means a variable called `name`.

Array:

```php
$user = [
    'name' => 'Michael',
    'email' => 'michael@finbank.test',
];
```

`'name' => 'Michael'` means key `name` has value `Michael`.

Class-level access:

```php
$user = User::find(1);
```

- `User` is the class.
- `::` means access something from the class.
- `find(1)` asks Eloquent for the user with ID 1.

Object-level access:

```php
$user->email
$user->update(['name' => 'Michael Updated']);
```

- `$user` is one object.
- `->email` reads a property.
- `->update()` runs a method.

`$this`:

```php
public function sentTransactions()
{
    return $this->hasMany(Transaction::class, 'sender_id');
}
```

`$this` means the current object running the method.

## Routes

Routes live in `routes/api.php`.

Example:

```php
Route::post('/transactions', [TransactionController::class, 'store']);
```

Meaning:

- `Route` is Laravel's routing class.
- `::` accesses something from the class.
- `post()` means HTTP POST.
- `'/transactions'` is the URL after `/api`.
- `[TransactionController::class, 'store']` tells Laravel which controller method to run.

## API Endpoint Documentation

All endpoints begin with `/api`.

### POST `/api/register`

Purpose: create a fake lab account.

Auth: no token required.

Input:

```json
{
  "name": "Learner",
  "email": "learner@finbank.test",
  "password": "FinBankLab123!",
  "password_confirmation": "FinBankLab123!"
}
```

Expected response: `201 Created` with user and token.

Security: validates input, hashes password, logs `account_registered`, never logs password or token.

### POST `/api/login`

Purpose: authenticate and receive a Sanctum token.

Auth: no token required.

Input:

```json
{
  "email": "michael@finbank.test",
  "password": "FinBankLab123!"
}
```

Expected response: `200 OK` with token, or `401 Unauthorized` for bad credentials.

Security: logs `login_success` or `login_failed`, checks `is_active`, never logs password or token.

### POST `/api/logout`

Purpose: revoke the current API token.

Auth: required.

Expected response: `200 OK`.

Security: logs `logout`.

### GET `/api/profile`

Purpose: show the authenticated user's profile.

Auth: required.

Authorization: current user only.

Expected response: `200 OK`.

Security: uses `$request->user()` so the client cannot choose another identity.

### PUT `/api/profile`

Purpose: update name and email.

Auth: required.

Input:

```json
{
  "name": "Michael Updated",
  "email": "michael.updated@finbank.test"
}
```

Expected response: `200 OK`.

Security: only validated profile fields are updated. `role` and `is_active` are ignored if sent by the client. Logs `profile_updated`.

### GET `/api/users/{id}`

Purpose: view a user by ID.

Auth: required.

Authorization: the same user or an admin.

Expected response: `200 OK`, `403 Forbidden`, or `404 Not Found`.

Security: logs `authorization_denied` if a normal user tries to read another user's profile.

### POST `/api/transactions`

Purpose: create a simulated transfer.

Auth: required.

Input:

```json
{
  "recipient_id": 2,
  "amount": 50000,
  "currency": "NGN",
  "description": "Lab transfer"
}
```

Expected response: `201 Created`.

Security: sender is always `$request->user()`. The client cannot choose `sender_id`. Logs `transaction_created`. If amount is above `LARGE_TRANSACTION_THRESHOLD`, logs `large_transaction_detected`.

### GET `/api/transactions`

Purpose: list transactions where the authenticated user is sender or recipient.

Auth: required.

Expected response: `200 OK`.

Security: normal users do not receive unrelated transactions.

### GET `/api/transactions/{id}`

Purpose: view one transaction.

Auth: required.

Authorization: sender, recipient, or admin.

Expected response: `200 OK`, `403 Forbidden`, or `404 Not Found`.

Security: `TransactionPolicy` blocks IDOR/BOLA. Denials log `authorization_denied`.

### GET `/api/admin/users`

Purpose: list all users.

Auth: required.

Authorization: admin only.

Expected response: `200 OK` or `403 Forbidden`.

Security: `AdminMiddleware` enforces server-side authorization.

### GET `/api/admin/transactions`

Purpose: list all transactions.

Auth: required.

Authorization: admin only.

Expected response: `200 OK` or `403 Forbidden`.

Security: `AdminMiddleware` required.

### PUT `/api/admin/users/{id}/role`

Purpose: change a user's role.

Auth: required.

Authorization: admin only.

Input:

```json
{
  "role": "admin"
}
```

Expected response: `200 OK`.

Security: logs `role_changed` with actor ID, target user ID, old role, and new role.

### PUT `/api/admin/users/{id}/status`

Purpose: activate or deactivate an account.

Auth: required.

Authorization: admin only.

Input:

```json
{
  "is_active": false
}
```

Expected response: `200 OK`.

Security: logs `account_status_changed` with actor ID, target user ID, old status, and new status.

## Authentication vs Authorization

Authentication asks:

```text
Who are you?
```

Authorization asks:

```text
What are you allowed to do?
```

A valid token only proves identity. It does not allow access to every transaction or admin endpoint.

## Middleware

Middleware sits between the request and the controller.

```text
Request
  -> auth:sanctum
  -> admin middleware
  -> controller
  -> response
```

`auth:sanctum` checks for a valid API token.

`AdminMiddleware` checks:

```php
if ($user->role !== 'admin') {
    return response()->json([
        'message' => 'Forbidden',
    ], 403);
}
```

Security significance: admin routes are protected by the server, not merely hidden from normal users.

## Validation

Validation asks:

```text
Is this input acceptable?
```

Example:

```php
$validated = $request->validate([
    'recipient_id' => ['required', 'integer', 'exists:users,id'],
    'amount' => ['required', 'numeric', 'min:1'],
    'currency' => ['required', 'string', 'in:NGN'],
]);
```

Security significance: the server does not trust client input. Invalid input returns `422 Validation Error` and logs `invalid_input`.

## Authorization and IDOR/BOLA Defense

The policy rule is in `app/Policies/TransactionPolicy.php`.

It allows a transaction view only if:

- the user is an admin,
- or the user is the sender,
- or the user is the recipient.

If user 5 requests transaction 10 that belongs to users 1 and 2, the app returns `403 Forbidden` and logs `authorization_denied`.

## Mass Assignment

Safe update:

```php
$user->update($validated);
```

Dangerous pattern:

```php
$user->update($request->all());
```

Why it is dangerous:

- `$request->all()` contains every field the client sent.
- An attacker might send `role=admin`.
- An attacker might send `is_active=true`.

The `User` model allows mass assignment only for:

```php
protected $fillable = [
    'name',
    'email',
    'password',
];
```

Admin code changes `role` and `is_active` explicitly.

## Eloquent Relationships

User:

```php
public function sentTransactions()
{
    return $this->hasMany(Transaction::class, 'sender_id');
}
```

Transaction:

```php
public function sender()
{
    return $this->belongsTo(User::class, 'sender_id');
}
```

Mental model:

```text
User
  has many
Transactions

Transaction
  belongs to
User
```

Eloquent:

```php
$user = User::find(1);
```

Conceptually similar SQL:

```sql
SELECT * FROM users WHERE id = 1 LIMIT 1;
```

## Database Tables

### users

- `id`
- `name`
- `email`
- `password`
- `role`
- `is_active`
- `created_at`
- `updated_at`

### transactions

- `id`
- `sender_id`
- `recipient_id`
- `amount`
- `currency`
- `status`
- `description`
- `created_at`
- `updated_at`

### personal_access_tokens

Sanctum stores hashed API token records here. Plaintext tokens are shown only once in the API response and are never logged.

## Security Logging

Security logs go to:

```text
storage/logs/security.log
```

The channel is configured in `config/logging.php` and uses JSON formatting.

Implemented events:

- `account_registered`
- `login_success`
- `login_failed`
- `logout`
- `authorization_denied`
- `role_changed`
- `account_status_changed`
- `transaction_created`
- `large_transaction_detected`
- `profile_updated`
- `invalid_input`
- `application_error`
- `rate_limit_triggered`

Common fields:

- `event`
- `user_id`
- `actor_id`
- `target_user_id`
- `resource`
- `resource_id`
- `ip`
- `endpoint`
- `method`
- `status`
- `user_agent`
- `request_id`
- `timestamp`

Sensitive values intentionally not logged:

- passwords
- API tokens
- session secrets
- API keys
- database passwords
- `.env` contents

Example security log line:

```json
{"message":"authorization_denied","context":{"event":"authorization_denied","user_id":5,"resource":"transaction","resource_id":1,"ip":"127.0.0.1","endpoint":"/api/transactions/1","method":"GET","status":403}}
```

## Rate Limiting

Login is protected by a `login` limiter.

Authenticated API routes use a `sensitive` limiter.

When a request is blocked, the app returns `429 Too Many Requests` and logs `rate_limit_triggered`.

## Error Handling

API validation errors return safe JSON with status `422`.

Unexpected API errors return:

```json
{
  "message": "Internal server error"
}
```

Detailed errors remain server-side. The app logs `application_error` for server-side API failures.

## HTTP Status Codes

| Code | Meaning | Example |
| --- | --- | --- |
| 200 | OK | Profile returned |
| 201 | Created | Transaction created |
| 400 | Bad Request | Malformed request |
| 401 | Unauthorized | Missing or bad authentication |
| 403 | Forbidden | Authenticated but not allowed |
| 404 | Not Found | User or transaction missing |
| 422 | Validation Error | Invalid request body |
| 429 | Too Many Requests | Rate limit triggered |
| 500 | Internal Server Error | Safe response for server failure |

`401` means authentication is missing or failed.

`403` means authentication worked but authorization failed.

## Ubuntu Quick Setup

After cloning the repository on Ubuntu, run:

```bash
bash scripts/setup-ubuntu.sh
```

The script installs PHP 8.4, Composer, and MySQL, creates `.env`, creates the MySQL database/user, writes the database settings, installs Composer dependencies, generates the app key, runs migrations/seeders, and clears caches. It does not install or configure Wazuh.

Optional verification afterward:

```bash
php artisan test
```

Start the lab afterward with:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## MySQL Setup

The app expects:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=finbank
DB_USERNAME=finbank_lab
DB_PASSWORD=<local lab database password>
```

Example local MySQL setup:

```sql
CREATE DATABASE finbank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'finbank_lab'@'127.0.0.1' IDENTIFIED BY '<local lab database password>';
GRANT ALL PRIVILEGES ON finbank.* TO 'finbank_lab'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Then run:

```powershell
.\tools\php\php.exe artisan migrate:fresh --seed
```

This workspace also has a local no-install MySQL server under `tools/mysql-9.7.1-winx64`.

Start it from the project root:

```powershell
Start-Process -FilePath "C:\Users\Zala\fin-lab\tools\mysql-9.7.1-winx64\bin\mysqld.exe" `
  -ArgumentList "--defaults-file=C:\Users\Zala\fin-lab\tools\mysql-lab.ini --console" `
  -WorkingDirectory "C:\Users\Zala\fin-lab" `
  -WindowStyle Hidden
```

Check Laravel's active database connection:

```powershell
.\tools\php\php.exe artisan db:show --counts
```

## Run the App

Use the local PHP runtime:

```powershell
.\tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Health check:

```powershell
curl.exe http://127.0.0.1:8000/up
```

Login:

```powershell
curl.exe -X POST http://127.0.0.1:8000/api/login `
  -H "Accept: application/json" `
  -H "Content-Type: application/json" `
  -d "{\"email\":\"michael@finbank.test\",\"password\":\"FinBankLab123!\"}"
```

Use the token:

```powershell
curl.exe http://127.0.0.1:8000/api/profile `
  -H "Accept: application/json" `
  -H "Authorization: Bearer TOKEN_HERE"
```

## Run Tests

```powershell
.\tools\php\php.exe artisan test
```

The tests verify:

- seeded users
- login and token creation
- admin middleware denial
- transaction ownership denial
- mass assignment protection
- large transaction logging
- admin role and status changes
- invalid input logging

## Inspect Logs

```powershell
Get-Content .\storage\logs\security.log -Tail 20
```

```powershell
Get-Content .\storage\logs\laravel.log -Tail 20
```

## Wazuh Compatibility

Later Ubuntu deployment should look like:

```text
Ubuntu
  -> Nginx
  -> Laravel
  -> PHP
  -> MySQL

Wazuh Agent monitors:
  -> Laravel security.log
  -> Laravel application log
  -> Nginx logs
  -> Ubuntu logs
  -> SSH/journald logs
```

Laravel should help detect:

- repeated failed logins
- successful login after many failures
- repeated `401`
- repeated `403`
- endpoint enumeration
- IDOR/BOLA attempts
- role changes
- account status changes
- large transactions
- rate limit triggers
- application errors

Nginx and Wazuh should later help detect:

- `GET /.env`
- `GET /.git/config`
- `GET /config/.env`
- `GET /backup.zip`
- `GET /database.sql`
- repeated 404 enumeration

Ubuntu and Wazuh should later detect:

- SSH authentication failures
- successful SSH login
- sudo activity
- new SSH keys
- SSH config changes
- suspicious system activity

## Security File Probing

Do not intentionally expose `.env`, `.git`, backups, or database dumps.

Later, a web server should safely return `403` or `404` for probes. The goal is to observe the web server logs and build Wazuh detections, not to expose real secrets.

## More Security Tests

See `SECURITY_TESTING.md`.
