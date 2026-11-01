<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preferences Updated</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            max-width: 480px;
            width: 100%;
            border: 1px solid #e2e8f0;
            padding: 40px 30px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <?php if (!empty($success)): ?>
            <div class="mb-3 text-success">
                <i class="fa-solid fa-circle-check" style="font-size: 3.5rem;"></i>
            </div>
            <h4 class="fw-bold mb-2">You Have Been Unsubscribed</h4>
            <p class="text-secondary mb-4">
                <?php echo htmlspecialchars($message); ?>
            </p>
            <?php if (!empty($email)): ?>
                <div class="badge bg-light text-dark border p-2 mb-4 font-monospace">
                    <?php echo htmlspecialchars($email); ?>
                </div>
            <?php endif; ?>
            <p class="small text-muted mb-0">
                Your request has taken effect immediately across our delivery systems. You will not receive any further emails matching your selection.
            </p>
        <?php else: ?>
            <div class="mb-3 text-danger">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 3.5rem;"></i>
            </div>
            <h4 class="fw-bold mb-2">Request Error</h4>
            <p class="text-secondary mb-4">
                <?php echo htmlspecialchars($message); ?>
            </p>
            <p class="small text-muted mb-0">
                If you continue having issues unsubscribing, please reply directly to the email or contact support.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>

