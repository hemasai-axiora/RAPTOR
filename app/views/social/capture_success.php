<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You | Raptor CRM</title>
    <!-- FontAwesome & Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .success-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 520px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-20 text-success rounded-circle mb-4" style="width: 80px; height: 80px; font-size: 2.2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h2 class="fw-bold text-white mb-2">Thank You, <?php echo htmlspecialchars($name ?? 'Valued Customer'); ?>!</h2>
        <p class="text-secondary mb-4">
            Your inquiry has been registered successfully. A dedicated account manager from our team will contact you shortly.
        </p>
        <a href="javascript:window.close()" class="btn btn-outline-light px-4 py-2 rounded-pill fw-semibold">
            <i class="fa-solid fa-xmark me-2"></i>Close Window
        </a>
    </div>
</body>
</html>
