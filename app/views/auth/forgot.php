<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | RAPTOR</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo URLROOT; ?>/logo.png">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        (function () {
            var savedTheme = localStorage.getItem('raptor_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --page-bg: #f5f9ff;
            --card-bg: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(31, 95, 174, 0.14);
            --accent-glow: linear-gradient(135deg, #1F5FAE 0%, #174887 100%);
            --text-primary: #0f172a;
            --text-secondary: #52647a;
            --field-bg: #f8fbff;
            --field-border: rgba(31, 95, 174, 0.16);
            --shadow: 0 24px 70px rgba(31, 95, 174, 0.16);
        }

        html[data-theme="dark"] {
            color-scheme: dark;
            --page-bg: #090f1d;
            --card-bg: rgba(17, 24, 39, 0.88);
            --glass-border: rgba(255, 255, 255, 0.08);
            --accent-glow: linear-gradient(135deg, #4a8ddb 0%, #1F5FAE 100%);
            --text-primary: #f8fafc;
            --text-secondary: #a8b3c7;
            --field-bg: #111827;
            --field-border: rgba(255, 255, 255, 0.08);
            --shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
        }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at 18% 14%, rgba(13, 110, 253, 0.16), transparent 32%),
                radial-gradient(circle at 82% 78%, rgba(0, 180, 216, 0.14), transparent 34%),
                var(--page-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        .blob {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(13, 110, 253, 0.12) 0%, rgba(13, 110, 253, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }
        .blob-1 { top: -100px; left: -100px; }
        .blob-2 { bottom: -100px; right: -100px; }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow);
            z-index: 10;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-text {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .form-control {
            background: var(--field-bg);
            border: 1px solid var(--field-border);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: var(--field-bg);
            border-color: #1F5FAE;
            color: var(--text-primary);
            box-shadow: 0 0 0 4px rgba(31, 95, 174, 0.14);
            outline: none;
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .btn-submit {
            background: var(--accent-glow);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            margin-top: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(31, 95, 174, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(31, 95, 174, 0.5);
            opacity: 0.95;
        }

        .invalid-feedback {
            font-size: 0.8rem;
            margin-top: 0.35rem;
        }

        .back-link {
            color: #1F5FAE;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        .theme-toggle-login {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 20;
            border: 1px solid var(--glass-border);
            background: var(--card-bg);
            color: var(--text-primary);
            border-radius: 999px;
            padding: 0.55rem 0.85rem;
            box-shadow: 0 12px 30px rgba(13, 110, 253, 0.12);
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle-login" id="theme-toggle-login" title="Toggle dark theme">
        <i class="fa-solid fa-moon"></i>
    </button>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="login-card">
        <div class="logo-area d-flex flex-column align-items-center mb-4">
            <img src="<?php echo URLROOT; ?>/logo.png" alt="Raptor Logo" style="height: 70px; width: auto; object-fit: contain; margin-bottom: 0.75rem;">
            <div class="logo-text text-white fw-bold">RESET PASSWORD</div>
            <div class="logo-sub text-secondary small">Enter your email to request an OTP.</div>
        </div>

        <form action="index.php?route=auth/forgot" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

            <div class="mb-4">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" 
                       class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                       placeholder="name@company.com" required autocomplete="email">
                <div class="invalid-feedback"><?php echo $email_err ?? ''; ?></div>
            </div>

            <button type="submit" class="btn btn-submit mb-3">
                <i class="fa-solid fa-paper-plane me-2"></i>Send Reset OTP
            </button>

            <div class="text-center mt-3">
                <a href="index.php?route=auth/login" class="back-link"><i class="fa-solid fa-arrow-left me-2"></i>Back to Sign In</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var btn = document.getElementById('theme-toggle-login');
            function sync() {
                var theme = document.documentElement.getAttribute('data-theme') || 'light';
                btn.querySelector('i').className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            }
            btn.addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('raptor_theme', next);
                sync();
            });
            sync();
        })();
    </script>
</body>
</html>
