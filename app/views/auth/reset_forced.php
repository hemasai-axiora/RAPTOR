<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | RAPTOR</title>
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
            var savedTheme = localStorage.getItem('raptor_theme') || 'dark';
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
            color: var(--text-primary) !important;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        .form-control:focus {
            background: var(--field-bg);
            border-color: #1F5FAE;
            box-shadow: 0 0 0 3px rgba(31, 95, 174, 0.25);
        }

        .btn-primary {
            background: var(--accent-glow);
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-area">
            <img src="<?php echo URLROOT; ?>/logo.png" alt="Raptor" style="height:48px; margin-bottom:12px;">
            <div class="logo-text text-white">RAPTOR</div>
            <div class="text-secondary small mt-2">First Login Required Password Update</div>
        </div>

        <form action="index.php?route=auth/reset_forced_password" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="mb-3">
                <label class="form-label text-secondary small">New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Minimum 8 characters" required>
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-secondary small">Confirm New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Re-enter new password" required>
                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3 text-white">Update Password</button>
            <a href="index.php?route=auth/logout" class="btn btn-outline-secondary w-100 text-secondary">Log Out</a>
        </form>
    </div>
</body>
</html>
