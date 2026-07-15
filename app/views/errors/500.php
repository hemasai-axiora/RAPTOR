<?php if (!isset($content)): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Server Error | RAPTOR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #090f1d;
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<?php endif; ?>

<div class="container-fluid py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div style="font-size: 100px; color: #ef4444; margin-bottom: 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h1 class="text-white fw-bold" style="font-size: 64px;">500</h1>
            <h3 class="text-secondary mb-4"><?php echo isset($title) ? htmlspecialchars($title) : 'Internal Server Error'; ?></h3>
            <p class="text-secondary mb-4">
                <?php echo isset($message) ? htmlspecialchars($message) : 'An unexpected error occurred. Please try again later.'; ?>
            </p>
            <a href="index.php" class="btn btn-outline-light px-4 py-2">
                <i class="fa-solid fa-house me-2"></i>Back to Home
            </a>
        </div>
    </div>
</div>

<?php if (!isset($content)): ?>
</body>
</html>
<?php endif; ?>
