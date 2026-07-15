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
            // Sanitize POST data
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['email'] = trim($_POST['email']);
            $data['password'] = trim($_POST['password']);
            $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
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
                // Validate Email
                if (empty($data['email'])) {
                    $data['email_err'] = 'Please enter email.';
                }

                // Validate Password
                if (empty($data['password'])) {
                    $data['password_err'] = 'Please enter password.';
                }

                // If no validation errors, proceed to login
                if (empty($data['email_err']) && empty($data['password_err'])) {
                    // Authenticate User
                    $loggedInUser = $this->userModel->login($data['email'], $data['password']);

                    if ($loggedInUser) {
                        Security::recordLoginAttempt($data['email'], true);
                        session_regenerate_id(true);
                        // Create Session variables
                        $this->createUserSession($loggedInUser);
                        
                        // Log user activity in audit log (optional for now)
                        $this->logActivity($loggedInUser->user_id, 'User logged in');

                        // Redirect to role specific dashboard
                        $this->redirectByRole($loggedInUser->role_name);
                    } else {
                        Security::recordLoginAttempt($data['email'], false);
                        Security::logEvent('login_failed', 'warning', null, ['email' => $data['email']]);
                        $data['password_err'] = 'Invalid email or password.';
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
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
            $email = trim($_POST['email'] ?? '');
            $data['email'] = $email;

            if (empty($email)) {
                $data['email_err'] = 'Please enter your email.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['email_err'] = 'Please enter a valid email address.';
            } else {
                $user = $this->userModel->findUserByEmail($email);
                // Securely generate OTP
                $otp = (string) random_int(100000, 999999);
                if ($user) {
                    $_SESSION['forgot_otp'] = $otp;
                    $_SESSION['forgot_email'] = $email;
                    $_SESSION['forgot_expires'] = time() + 600; // 10 minutes
                } else {
                    // Set fake session data to prevent timing analysis & keep reset flow consistent
                    $_SESSION['forgot_otp'] = (string) random_int(100000, 999999);
                    $_SESSION['forgot_email'] = $email;
                    $_SESSION['forgot_expires'] = time() - 3600; // already expired
                }
                
                $_SESSION['login_success'] = 'If the email address is associated with an active account, an OTP has been sent.';
                $this->redirect('index.php?route=auth/reset_password&email=' . urlencode($email));
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

        $this->view('auth/reset_forced', $data);
    }

    // Logout Action
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'User logged out');
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = array();
        session_destroy();
        
        $this->redirect('index.php?route=auth/login');
    }

    // Helper to log in session
    private function createUserSession($user) {
        $_SESSION['user_id'] = $user->user_id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role_name;
        $_SESSION['force_password_reset'] = (int) ($user->force_password_reset ?? 0);
        
        // Fetch and cache role permissions
        $_SESSION['permissions'] = $this->userModel->getRolePermissions($user->role_id);
        $_SESSION['last_activity'] = time();
    }

    // Helper to redirect based on user role
    private function redirectByRole($role) {
        switch ($role) {
            case 'analyst':
                $this->redirect('index.php?route=dashboard/show/executive');
                break;
            case 'employee':
            case 'sales_person':
                // Employees start their day at Attendance (check-in gateway).
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
