<!-- Raptor CRM Access Denied Error View -->
<div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center p-5 shadow-lg rounded bg-dark text-white border border-secondary" style="max-width: 500px; backdrop-filter: blur(10px);">
        <h1 class="display-1 text-danger font-weight-bold mb-4">403</h1>
        <h3 class="mb-3"><?php echo isset($title) ? htmlspecialchars($title) : 'Access Denied'; ?></h3>
        <p class="text-muted mb-4"><?php echo isset($message) ? htmlspecialchars($message) : 'You do not have authorization to view this page.'; ?></p>
        <a href="index.php" class="btn btn-outline-light btn-lg px-4 py-2">Go to Home</a>
    </div>
</div>
