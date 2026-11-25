<?php
// Raptor CRM Auth Controller

class AuthController extends Controller {
    private $userModel;

    public function __construct() {
        $this->userModel = $this->model('User');
    }

    // Login Action
    public function login() {
        // Redirect if already logged in
        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['user_role']);
        }

        $data = [
            'title' => 'Login | Raptor CRM',
            'email' => '',
            'password' => '',
            'email_err' => '',
            'password_err' => '',
            'csrf_token' => $this->generateCsrfToken()
        ];

        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identifier = trim($_POST['email'] ?? $_POST['identifier'] ?? '');
            $data['email'] = $identifier;
            $data['password'] = (string)($_POST['password'] ?? '');
            $csrf = (string)($_POST['csrf_token'] ?? '');
            $loginLimit = (int) Security::setting('rate.login_limit', 20);
            $loginWindow = (int) Security::setting('rate.login_window_seconds', 300);

            // Validate CSRF
            if (!$this->validateCsrfToken($csrf)) {
                $data['password_err'] = 'Security validation failed (CSRF token mismatch).';
                Security::logEvent('login_csrf_failed', 'warning', null, ['email' => $data['email']]);
            } elseif (!Security::rateLimit('login:' . Security::clientIp(), $loginLimit, $loginWindow)) {
                $data['password_err'] = 'Too many login attempts. Please wait a few minutes and try again.';
            } elseif (!empty($data['email']) && Security::loginLocked($data['email'])) {
                $data['password_err'] = 'This login is temporarily locked. Please try again later.';
                Security::logEvent('login_locked', 'warning', null, ['email' => $data['email']]);
            } else {
                // Validate Identifier
                if (empty($identifier)) {
                    $data['email_err'] = 'Please enter Employee ID or Email.';
                }

                // Validate Password
                if (empty($data['password'])) {
                    $data['password_err'] = 'Please enter password.';
                }

                // If no validation errors, proceed to login
                if (empty($data['email_err']) && empty($data['password_err'])) {
                    // Authenticate User by Employee ID or Email
                    $loggedInUser = $this->userModel->login($identifier, $data['password']);

                    if ($loggedInUser) {
                        Security::recordLoginAttempt($data['email'], true);
                        session_regenerate_id(true);
                        // Create Session variables
                        $this->createUserSession($loggedInUser);
                        
                        // Log user activity in audit log
                        $this->logActivity($loggedInUser->user_id, 'User logged in');

                        // Redirect to role specific dashboard
                        $this->redirectByRole($loggedInUser->role_name);
                    } else {
                        Security::recordLoginAttempt($data['email'], false);
                        Security::logEvent('login_failed', 'warning', null, ['email' => $data['email']]);
                        // Generic error message to prevent user enumeration
                        $data['password_err'] = 'Invalid Employee ID/email or password.';
                    }
                }
            }
        }

        // Render login view without layout
        $this->view('auth/login', $data);
    }

    // Forgot Password Action
    public function forgot() {
        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['user_role']);
        }

        $data = [
            'title' => 'Forgot Password | Raptor CRM',
            'email' => '',
            'email_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $identifier = trim($_POST['email'] ?? $_POST['identifier'] ?? '');
            $data['email'] = $identifier;

            if (empty($identifier)) {
                $data['email_err'] = 'Please enter Employee ID or Email.';
            } else {
                $user = $this->userModel->resolveEmployeeByIdentifier($identifier);
                // Securely generate OTP
                $otp = (string) random_int(100000, 999999);
                if ($user && isset($user->status) && $user->status === 'active') {
                    $_SESSION['forgot_otp'] = $otp;
                    $_SESSION['forgot_email'] = $user->email;
                    $_SESSION['forgot_expires'] = time() + 600; // 10 minutes

                    if (class_exists('EmailService') && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $otpSubject = "Your Raptor CRM Password Reset Verification Code";
                        $otpPlain = "Hello " . ($user->name ?? 'User') . ",\n\n" .
                                    "Your password reset verification code is: {$otp}\n\n" .
                                    "This code will expire in 10 minutes.\n" .
                                    "If you did not request this password reset, please ignore this email or contact support.\n\n" .
                                    "Best regards,\nAxiora Security Team";
                        $otpHtml = '<div style="max-width:550px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;color:#1e293b;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
                                 . '<div style="background:#0f172a;padding:20px 24px;color:#ffffff;"><h2 style="margin:0;font-size:18px;">Axiora Global Solutions</h2></div>'
                                 . '<div style="padding:24px;line-height:1.6;">'
                                 . '<p>Hello ' . htmlspecialchars($user->name ?? 'User') . ',</p>'
                                 . '<p>We received a request to reset your password for Raptor CRM. Use the verification code below to complete your password reset:</p>'
                                 . '<div style="background:#f1f5f9;border-radius:6px;padding:16px;text-align:center;margin:20px 0;"><span style="font-size:28px;font-weight:bold;letter-spacing:4px;color:#0f172a;">' . htmlspecialchars($otp) . '</span></div>'
                                 . '<p style="font-size:13px;color:#64748b;">This verification code is valid for <strong>10 minutes</strong>. If you did not initiate this request, no action is needed and your account remains secure.</p>'
                                 . '<p style="margin-top:24px;color:#64748b;font-size:13px;">Best regards,<br><strong style="color:#0f172a;">Axiora Security Team</strong></p>'
                                 . '</div>'
                                 . '<div style="background:#f8fafc;padding:12px 24px;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;text-align:center;">Axiora Global Solutions, Cyber City, Hyderabad, India</div>'
                                 . '</div>';
                        try {
                            EmailService::send($user->email, $user->name ?? 'User', $otpSubject, $otpHtml, $otpPlain);
                        } catch (Throwable $eMail) {
                            // Non-blocking: session OTP remains available for UI demo/fallback
                        }
                    }
                } else {
                    // Set fake session data to prevent timing analysis & keep reset flow consistent
                    $_SESSION['forgot_otp'] = (string) random_int(100000, 999999);
                    $_SESSION['forgot_email'] = $identifier;
                    $_SESSION['forgot_expires'] = time() - 3600; // already expired
                }
                
                $_SESSION['login_success'] = 'If the Employee ID or email address is associated with an active account, an OTP has been sent.';
                $this->redirect('index.php?route=auth/reset_password&email=' . urlencode($user ? $user->email : $identifier));
            }
        }

        $this->view('auth/forgot', $data);
    }

    // Reset Password Action
    public function reset_password() {
        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['user_role']);
        }

        $email = $_GET['email'] ?? $_POST['email'] ?? '';

        $data = [
            'title' => 'Reset Password | Raptor CRM',
            'email' => $email,
            'otp_err' => '',
            'password_err' => '',
            'confirm_password_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
            $otp = trim($_POST['otp'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            // Validate OTP
            if (empty($otp)) {
                $data['otp_err'] = 'Please enter the OTP.';
            } elseif (!isset($_SESSION['forgot_otp']) || $_SESSION['forgot_otp'] !== $otp || $_SESSION['forgot_email'] !== $email) {
                $data['otp_err'] = 'Invalid OTP or email mismatch.';
            } elseif (time() > $_SESSION['forgot_expires']) {
                $data['otp_err'] = 'OTP has expired. Please request a new one.';
            }

            // Validate password
            if (empty($password)) {
                $data['password_err'] = 'Please enter a new password.';
            } elseif (strlen($password) < 8) {
                $data['password_err'] = 'Password must be at least 8 characters long.';
            }

            if (empty($confirmPassword)) {
                $data['confirm_password_err'] = 'Please confirm your password.';
            } elseif ($password !== $confirmPassword) {
                $data['confirm_password_err'] = 'Passwords do not match.';
            }

            if (empty($data['otp_err']) && empty($data['password_err']) && empty($data['confirm_password_err'])) {
                $user = $this->userModel->findUserByEmail($email);
                if ($user && password_verify($password, $user->password)) {
                    $data['password_err'] = 'New password cannot be identical to your current password.';
                } else {
                    if ($this->userModel->resetPassword($email, $password)) {
                        // Clear session OTP
                        unset($_SESSION['forgot_otp']);
                        unset($_SESSION['forgot_email']);
                        unset($_SESSION['forgot_expires']);

                        $_SESSION['login_success'] = 'Password reset successfully. You can now log in.';
                        $this->redirect('index.php?route=auth/login');
                    } else {
                        $data['password_err'] = 'Failed to reset password. Please try again.';
                    }
                }
            }
        }

        $this->view('auth/reset', $data);
    }

    // Forced Password Reset Action
    public function reset_forced_password() {
        if (!$this->isLoggedIn()) {
            $this->redirect('index.php?route=auth/login');
        }

        $data = [
            'title' => 'Change Password | Raptor CRM',
            'password_err' => '',
            'confirm_password_err' => '',
            'csrf_token' => $this->generateCsrfToken()
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
            $password = trim($_POST['password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            $csrf = $_POST['csrf_token'] ?? '';

            if (!$this->validateCsrfToken($csrf)) {
                $data['password_err'] = 'Security validation failed (CSRF mismatch).';
            } else {
                if (empty($password)) {
                    $data['password_err'] = 'Please enter a new password.';
                } elseif (strlen($password) < 8) {
                    $data['password_err'] = 'Password must be at least 8 characters long.';
                }

                if (empty($confirmPassword)) {
                    $data['confirm_password_err'] = 'Please confirm your password.';
                } elseif ($password !== $confirmPassword) {
                    $data['confirm_password_err'] = 'Passwords do not match.';
                }

                if (empty($data['password_err']) && empty($data['confirm_password_err'])) {
                    $user = $this->userModel->getUserById($_SESSION['user_id']);
                    if ($user && password_verify($password, $user->password)) {
                        $data['password_err'] = 'New password cannot be identical to your current password.';
                    } else {
                        $db = Database::getInstance()->getConnection();
                        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                        $stmt = $db->prepare('UPDATE users SET password = :pass, force_password_reset = 0 WHERE user_id = :id');
                        if ($stmt->execute([':pass' => $hashed, ':id' => $_SESSION['user_id']])) {
                            $_SESSION['force_password_reset'] = 0;
                            $_SESSION['user_success'] = 'Password updated successfully. You are now logged in.';
                            $this->redirectByRole($_SESSION['user_role']);
                        } else {
                            $data['password_err'] = 'Failed to update password. Please try again.';
                        }
                    }
                }
            }
        }

        $this->view('auth/reset_forced', $data);
    }

    // Logout Action
    public function logout() {
        $reason = $_GET['reason'] ?? $_POST['reason'] ?? 'user_logout';
        $auditAction = ($reason === 'session_timeout') ? 'SESSION_LOGOUT_TIMEOUT' : 'SESSION_LOGOUT_USER';
        
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], $auditAction);
            try {
                $sid = session_id();
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('DELETE FROM sessions WHERE session_id = :sid OR user_id = :uid');
                $stmt->execute([':sid' => $sid, ':uid' => (int) $_SESSION['user_id']]);
            } catch (Exception $e) {
                // Fail safe
            }
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array();
        session_destroy();
        
        $this->redirect('index.php?route=auth/login&reason=' . urlencode($reason));
    }

    // Helper to log in session
    private function createUserSession($user) {
        $_SESSION['user_id']              = $user->user_id;
        $_SESSION['user_email']           = $user->email;
        $_SESSION['user_name']            = $user->name;
        $_SESSION['user_role']            = $user->role_name;
        $_SESSION['user_status']          = $user->status ?? 'active';
        $_SESSION['user_photo']           = $user->profile_photo ?? null;
        $_SESSION['force_password_reset'] = (int) ($user->force_password_reset ?? 0);

        // Legacy flat permissions list (backward compat with hasPermission())
        $_SESSION['permissions'] = $this->userModel->getRolePermissions($user->role_id);

        // Granular permissions: ['module.action' => 'scope_or_null']
        $_SESSION['rbac_permissions'] = PermissionService::loadForUser($user->user_id, $user->role_id);

        $_SESSION['last_activity'] = time();

        // 10-hour initial session checkpoint setup
        $now = date('Y-m-d H:i:s');
        $expiryTime = date('Y-m-d H:i:s', time() + (10 * 3600));

        $_SESSION['login_time']     = $now;
        $_SESSION['session_expiry'] = $expiryTime;

        // Persist session to database table `sessions`
        try {
            $sid = session_id();
            $db  = Database::getInstance()->getConnection();
            $stmt = $db->prepare('INSERT INTO sessions (session_id, user_id, login_time, session_expiry, last_confirmed_at, created_at, updated_at) 
                VALUES (:sid, :uid, :login, :expiry, NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE user_id = :uid, login_time = :login, session_expiry = :expiry, updated_at = NOW()');
            $stmt->execute([
                ':sid'    => $sid,
                ':uid'    => (int) $user->user_id,
                ':login'  => $now,
                ':expiry' => $expiryTime
            ]);
        } catch (Exception $e) {
            // Fail safe
        }
    }

    /**
     * Session Status API Endpoint (JSON)
     */
    public function sessionStatus() {
        header('Content-Type: application/json');
        if (!$this->isLoggedIn()) {
            echo json_encode([
                'success' => false,
                'is_expired' => false,
                'show_popup' => false,
                'remaining_seconds' => 0
            ]);
            exit();
        }

        if (empty($_SESSION['session_expiry'])) {
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            $_SESSION['session_expiry'] = date('Y-m-d H:i:s', time() + (10 * 3600));
        }

        $nowTs = time();
        $expiryTs = strtotime($_SESSION['session_expiry']);
        $remainingSec = $expiryTs - $nowTs;
        $showPopup = ($remainingSec <= 0 && $remainingSec >= -300);

        echo json_encode([
            'success' => true,
            'session_expiry' => $_SESSION['session_expiry'],
            'server_now' => date('Y-m-d H:i:s', $nowTs),
            'remaining_seconds' => $remainingSec,
            'popup_grace_seconds' => 300,
            'show_popup' => $showPopup,
            'is_expired' => ($remainingSec < -300)
        ]);
        exit();
    }

    /**
     * Session Extend API Endpoint (JSON)
     * Sets session_expiry to exactly NOW() + 2 hours.
     * Never accepts client-supplied timestamp.
     */
    public function sessionExtend() {
        header('Content-Type: application/json');
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }

        $nowTs = time();
        $nowStr = date('Y-m-d H:i:s', $nowTs);
        $newExpiryStr = date('Y-m-d H:i:s', $nowTs + (2 * 3600));

        $_SESSION['session_expiry'] = $newExpiryStr;
        $_SESSION['last_activity']  = $nowTs;

        try {
            $sid = session_id();
            $db  = Database::getInstance()->getConnection();
            $stmt = $db->prepare('UPDATE sessions SET session_expiry = :expiry, last_confirmed_at = :now, updated_at = NOW() WHERE session_id = :sid AND user_id = :uid');
            $stmt->execute([
                ':expiry' => $newExpiryStr,
                ':now'    => $nowStr,
                ':sid'    => $sid,
                ':uid'    => (int) $_SESSION['user_id']
            ]);
        } catch (Exception $e) {
            // Fail safe
        }

        $this->logActivity($_SESSION['user_id'], 'SESSION_EXTENDED');

        echo json_encode([
            'success' => true,
            'message' => 'Session extended by 2 hours.',
            'session_expiry' => $newExpiryStr,
            'remaining_seconds' => 7200
        ]);
        exit();
    }

    /**
     * Session Logout API Endpoint (JSON)
     */
    public function sessionLogout() {
        header('Content-Type: application/json');
        $rawTrigger = $_POST['trigger'] ?? $_GET['trigger'] ?? 'user_logout';
        $isTimeout  = ($rawTrigger === 'timeout' || $rawTrigger === 'session_timeout');
        $reason     = $isTimeout ? 'session_timeout' : 'user_logout';
        $auditType  = $isTimeout ? 'SESSION_LOGOUT_TIMEOUT' : 'SESSION_LOGOUT_USER';

        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], $auditType);
            try {
                $sid = session_id();
                $db  = Database::getInstance()->getConnection();
                $stmt = $db->prepare('DELETE FROM sessions WHERE session_id = :sid OR user_id = :uid');
                $stmt->execute([':sid' => $sid, ':uid' => (int) $_SESSION['user_id']]);
            } catch (Exception $e) {
                // Fail safe
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array();
        session_destroy();

        echo json_encode([
            'success'  => true,
            'redirect' => 'index.php?route=auth/login&reason=' . urlencode($reason)
        ]);
        exit();
    }

    // Helper to redirect based on user role
    private function redirectByRole($role) {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if (!in_array($role, ['admin', 'ceo'], true)) {
            try {
                $db = Database::getInstance()->getConnection();
                $todayStr = date('Y-m-d');
                $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = :uid AND work_date = :d AND login_at IS NOT NULL");
                $stmt->execute([':uid' => $uid, ':d' => $todayStr]);
                $hasClockedIn = (int)$stmt->fetchColumn() > 0;
                if (!$hasClockedIn) {
                    $this->redirect('index.php?route=attendance/index');
                    return;
                }
            } catch (Throwable $e) {
                // fallback
            }
        }

        switch ($role) {
            case 'analyst':
                $this->redirect('index.php?route=dashboard/show/executive');
                break;
            case 'employee':
            case 'sales_person':
                $this->redirect('index.php?route=attendance/index');
                break;
            case 'hr':
                $this->redirect('index.php?route=users/index');
                break;
            case 'team_leader':
                $this->redirect('index.php?route=dashboard/show/sales_command');
                break;
            case 'admin':
            case 'manager':
            case 'ceo':
                $this->redirect('index.php?route=dashboard/index');
                break;
            case 'employer':
            default:
                $this->redirect('index.php?route=dashboard/show/executive');
                break;
        }
    }

    // Helper to write activity logs
    private function logActivity($userId, $action) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (:uid, :act, :ip, :ua)');
            $stmt->execute([
                ':uid' => $userId,
                ':act' => $action,
                ':ip' => $ip,
                ':ua' => $ua
            ]);
        } catch (Exception $e) {
            // Fail silently if table doesn't exist yet
        }
    }
}
