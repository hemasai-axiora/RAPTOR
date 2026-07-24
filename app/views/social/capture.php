<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Get in Touch | Raptor CRM'; ?></title>
    <!-- FontAwesome & Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --card-bg: rgba(30, 41, 59, 0.85);
            --border-color: rgba(255, 255, 255, 0.12);
        }
        body {
            background: var(--bg-gradient);
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .capture-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 580px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }
        .form-control, .form-select {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }
        .form-control::placeholder {
            color: #94a3b8;
        }
        .btn-submit {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
            opacity: 0.95;
        }
    </style>
</head>
<body>
    <div class="capture-card">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-20 text-primary rounded-circle mb-3" style="width: 70px; height: 70px; font-size: 1.8rem;">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <h2 class="fw-bold text-white mb-1">Connect With Us</h2>
            <p class="text-secondary small mb-0">
                <?php if (!empty($account)): ?>
                    Inquiry via <?php echo htmlspecialchars($account->platform_name ?? 'Social Media'); ?> (<?php echo htmlspecialchars($account->profile_name); ?>)
                <?php else: ?>
                    Fill in your details below and our team will get in touch with you right away.
                <?php endif; ?>
            </p>
        </div>

        <form action="index.php?route=social/submitPublicLead" method="POST">
            <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($account->account_id ?? 0); ?>">

            <div class="mb-3">
                <label for="name" class="form-label text-secondary small fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. Sarah Jenkins">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label text-secondary small fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" required placeholder="sarah@company.com">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label text-secondary small fw-semibold">Phone / Mobile <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" id="phone" class="form-control" required placeholder="+1 555-0199">
                </div>
            </div>

            <div class="mb-3">
                <label for="company_name" class="form-label text-secondary small fw-semibold">Company / Business Name</label>
                <input type="text" name="company_name" id="company_name" class="form-control" placeholder="Company Name (Optional)">
            </div>

            <div class="mb-4">
                <label for="notes" class="form-label text-secondary small fw-semibold">How can we help you?</label>
                <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Tell us about your project, requirement, or service inquiry..."></textarea>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-submit">
                    <i class="fa-solid fa-check-circle me-2"></i>Submit Inquiry &amp; Request Callback
                </button>
            </div>
        </form>
    </div>
</body>
</html>
