# OWASP ASVS Level 2 Security Controls & Implementation Matrix

```
Document:             SECURITY-CONTROLS.md
Directory:            RAPTOR-V2.2-BLUEPRINT/SECURITY/
Classification:       Application Security Controls Specification
Target Release:       Raptor v2.2
Standard:             OWASP ASVS v4.0.3 Level 2
```

---

## 1. Authentication & Session Security (ASVS V2 & V3)

| Control ID | ASVS Item | Implementation Specification | Implementation File | Verification Test |
|---|---|---|---|---|
| **SEC-AUTH-01** | **V2.1 Password Security** | Enforce minimum 10 characters with uppercase, lowercase, digit, and special symbol. Passwords hashed using `PASSWORD_BCRYPT` with cost factor 12. | `app/core/Security.php` | Attempt password change with weak strings; verify reject. |
| **SEC-AUTH-02** | **V3.1 Session Fixation** | `session_regenerate_id(true)` invoked immediately upon successful authentication. Old session file wiped. | `app/controllers/AuthController.php` | Compare session ID before and after login; confirm change. |
| **SEC-AUTH-03** | **V3.2 Cookie Flags** | Session cookie issued with `Secure = true` (on HTTPS), `HttpOnly = true`, and `SameSite = 'Lax'`. | `public/index.php` | Inspect Set-Cookie header on all responses. |
| **SEC-AUTH-04** | **V3.3 Inactivity Timeout** | Inactivity timeout enforced at 30 minutes (configurable via `SESSION_TIMEOUT`). Hard timeout at 12 hours. | `app/core/Controller.php` | Sleep session for 31 minutes; verify automatic redirect to login. |
| **SEC-AUTH-05** | **V2.2 Brute Force Lock** | Account locks after 5 consecutive failed login attempts for 15 minutes; tracked in `login_attempts`. | `app/core/Security.php` | Send 6 invalid passwords; verify account status `locked`. |

---

## 2. Authorization & Tenant Isolation (ASVS V4)

| Control ID | ASVS Item | Implementation Specification | Implementation File | Verification Test |
|---|---|---|---|---|
| **SEC-AUTHZ-01**| **V4.1 RBAC Enforcement** | Server-side permission verification via `PermissionService::can($module, $action)` on every controller dispatch. | `app/core/Controller.php` | Craft direct HTTP request to `/payroll/run` as `employee`; verify 403 Forbidden. |
| **SEC-AUTHZ-02**| **V4.2 Team-Subtree Scoping** | Managers can only query records of employees in their reporting hierarchy via `Policy::getTeamSubtreeUserIds()`. | `app/core/Policy.php` | Manager queries `/users` and `/leaves`; verify only subtree records returned. |
| **SEC-AUTHZ-03**| **V4.3 Object-Level Auth** | Verifies ownership before updating deals, tasks, or edit requests. ID parameter checked against current user subtree. | `app/controllers/*Controller.php` | POST `/tasks/update/52` where task 52 belongs to another team; verify 403 Forbidden. |

---

## 3. Injection Prevention & Output Sanitization (ASVS V5)

| Control ID | ASVS Item | Implementation Specification | Implementation File | Verification Test |
|---|---|---|---|---|
| **SEC-INJ-01** | **V5.1 SQL Injection** | 100% of SQL queries executed through PDO prepared statements with parameterized binds. No raw SQL concatenation. | `app/core/Database.php` | Automated SQL injection fuzzing on all query parameters. |
| **SEC-INJ-02** | **V5.2 Cross-Site Scripting** | All dynamic view outputs escaped via `htmlspecialchars($val, ENT_QUOTES \| ENT_HTML5, 'UTF-8')`. | `app/views/**/*.php` | Inject `<script>alert(1)</script>` into lead name; verify escaped in DOM. |
| **SEC-INJ-03** | **V5.3 CSRF Protection** | Cryptographically secure 256-bit token stored in session; validated on every state-changing POST/PUT/DELETE request. | `app/core/Controller.php` | Send POST request without `csrf_token` header or field; verify 403 Security Error. |
| **SEC-INJ-04** | **V5.4 Server-Side Request Forgery** | External webhook URLs and domain preflights restricted to validated public IPs. Private IP ranges (RFC 1918) blocked. | `app/services/PreflightEngine.php`| Attempt preflight on `http://169.254.169.254` (cloud metadata); verify blocked. |

---

## 4. AI & Generative Model Security

| Control ID | Target Area | Implementation Specification | Implementation File | Verification Test |
|---|---|---|---|---|
| **SEC-AI-01** | **Prompt Injection** | User-supplied context (e.g. email subject, incoming WhatsApp message) wrapped in strict XML delimiters: `<user_input>`. System instructions instruct model to never follow instructions inside input tags. | `app/services/AiService.php` | Test with jailbreak prompts (`"Ignore previous instructions"`); verify model ignores command. |
| **SEC-AI-02** | **Sensitive Data Leakage**| Sensitive customer fields (passwords, salary figures, bank account numbers) stripped before passing text to third-party LLMs. | `app/services/AiService.php` | Verify payroll amounts are redacted prior to generating AI salary summaries. |
| **SEC-AI-03** | **Key Exposure** | API keys for Gemini, OpenAI, and Anthropic stored in database settings with optional encryption or environment variables. Never logged in plain text or rendered in client HTML. | `app/controllers/SettingsController.php`| Inspect DOM source and network payloads; verify API keys masked. |

---

## 5. Storage & File Security

| Control ID | Target Area | Implementation Specification | Implementation File | Verification Test |
|---|---|---|---|---|
| **SEC-FILE-01** | **Upload Validation** | MIME type validated via `finfo_file` (not client extension). File renamed to random UUID. Extension strictly allowlisted (PDF, PNG, JPG). | `app/core/Storage.php` | Attempt uploading `shell.php.jpg` with PHP code; verify rejected. |
| **SEC-FILE-02** | **Private Delivery** | Files stored outside web root (`STORAGE_PATH`). Delivered exclusively through `FileController::download()` with auth and permission checks. | `app/controllers/FileController.php`| Direct URL access to `/storage/...` returns 404/403. |
| **SEC-FILE-03** | **Executable Guard** | `.htaccess` placed in storage directories containing `php_flag engine off` and `Deny from all` as defense-in-depth. | `storage/.htaccess` | Direct script execution test returns 403 Forbidden. |

---

## 6. Deployment Runner Hardening (`public/update.php`)

```php
// Enhanced Security Specification for public/update.php
$deployKey = env('RAPTOR_DEPLOY_KEY', 'raptor_deploy_2026');
$incomingKey = $_GET['key'] ?? '';

// Constant-time timing attack protection
if (!hash_equals($deployKey, $incomingKey)) {
    http_response_code(403);
    die('Unauthorized: Deployment key invalid.');
}

// IP Whitelist Check (Optional if server IP is configured)
$allowedIps = array_filter(explode(',', env('RAPTOR_DEPLOY_ALLOWED_IPS', '')));
if (!empty($allowedIps) && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIps, true)) {
    http_response_code(403);
    die('Unauthorized: IP address not permitted to run deployment.');
}
```

