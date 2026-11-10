<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Application Submitted | Raptor Careers'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/raptor-tokens.css">
    <link rel="stylesheet" href="assets/css/raptor-glass.css">
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-card {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            max-width: 600px;
        }
    </style>
</head>
<body>
    <div class="container p-4">
        <div class="card success-card rounded-4 p-5 text-center shadow-lg mx-auto">
            <div class="rounded-circle bg-success bg-opacity-25 text-success d-inline-flex p-4 mx-auto mb-4" style="width: 90px; height: 90px; align-items: center; justify-content: center;">
                <i class="fa-solid fa-check fa-3x"></i>
            </div>

            <h2 class="fw-bold text-white mb-2">Application Received!</h2>
            <p class="text-muted mb-4">
                Thank you for applying to Raptor. Your submission has been securely recorded and dispatched to our talent acquisition team.
            </p>

            <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25 mb-4">
                <span class="text-muted small d-block">Your Application Reference Code</span>
                <code class="fs-4 fw-bold text-primary"><?php echo htmlspecialchars($application_code ?? 'APP-2026-00001'); ?></code>
            </div>

            <p class="text-muted small mb-4">
                An acknowledgement confirmation has been queued for delivery to your email address. Please keep your reference code handy for future communication.
            </p>

            <div>
                <a href="index.php?route=careers" class="btn btn-primary px-4 py-2 fw-semibold">
                    <i class="fa-solid fa-arrow-left me-2"></i>View Other Openings
                </a>
            </div>
        </div>
    </div>
</body>
</html>

