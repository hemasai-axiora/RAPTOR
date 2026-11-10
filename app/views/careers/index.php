<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Careers | Raptor'); ?></title>
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
        }
        .career-card {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .career-card:hover {
            transform: translateY(-3px);
            border-color: #3b82f6;
        }
        .hero-banner {
            background: radial-gradient(circle at 50% 0%, rgba(37, 99, 235, 0.25) 0%, transparent 70%);
            padding: 80px 20px 50px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg border-bottom border-secondary border-opacity-25 py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php?route=careers">
                <i class="fa-solid fa-bolt text-primary"></i> RAPTOR CAREERS
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php?route=auth/login" class="btn btn-sm btn-outline-light px-3">Employee Portal</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-banner text-center">
        <div class="container">
            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill mb-3">
                <i class="fa-solid fa-sparkles me-1"></i> We are hiring top talent
            </span>
            <h1 class="display-5 fw-bold mb-3 text-white">Build the Future of Enterprise Software</h1>
            <p class="lead text-muted mx-auto mb-4" style="max-width: 650px;">
                Join our multidisciplinary engineering, product, and sales teams driving mission-critical business automation.
            </p>

            <!-- Search Bar -->
            <div class="card career-card border-0 shadow-lg p-2 rounded-4 mx-auto" style="max-width: 750px;">
                <form method="GET" action="index.php" class="row g-2 align-items-center p-2">
                    <input type="hidden" name="route" value="careers">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control bg-transparent text-white border-0" placeholder="Job title, keywords, skills..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="department" class="form-select bg-dark text-white border-secondary border-opacity-25">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (($filters['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Search Jobs</button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- Job Listings Grid -->
    <main class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0 text-white">Current Openings (<?php echo count($jobs); ?>)</h4>
            <?php if (!empty($filters['search']) || !empty($filters['department'])): ?>
            <a href="index.php?route=careers" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
            <?php endif; ?>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="text-center py-5 career-card rounded-4 p-5">
                <i class="fa-solid fa-briefcase fa-3x text-muted mb-3 opacity-50"></i>
                <h5 class="fw-bold text-white">No matching job vacancies</h5>
                <p class="text-muted">There are currently no open positions matching your search criteria. Check back soon!</p>
                <a href="index.php?route=careers" class="btn btn-primary btn-sm px-4">View All Positions</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($jobs as $job): ?>
                <div class="col-lg-6 col-md-12">
                    <div class="card career-card rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    <?php echo htmlspecialchars($job['department']); ?>
                                </span>
                                <span class="text-muted small">
                                    <i class="fa-regular fa-clock me-1"></i><?php echo !empty($job['published_at']) ? date('M d, Y', strtotime($job['published_at'])) : 'Active'; ?>
                                </span>
                            </div>

                            <h4 class="fw-bold mb-2">
                                <a href="index.php?route=careers/job/<?php echo urlencode($job['slug']); ?>" class="text-white text-decoration-none">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </a>
                            </h4>

                            <div class="d-flex flex-wrap gap-2 mb-3 text-muted small">
                                <span><i class="fa-solid fa-location-dot me-1 text-danger"></i><?php echo htmlspecialchars($job['location']); ?></span> &bull; 
                                <span class="text-capitalize"><i class="fa-solid fa-building me-1 text-info"></i><?php echo str_replace('_', ' ', $job['workplace_arrangement']); ?></span> &bull; 
                                <span class="text-capitalize"><i class="fa-solid fa-briefcase me-1 text-warning"></i><?php echo str_replace('_', ' ', $job['employment_type']); ?></span>
                            </div>

                            <?php if (!empty($job['show_salary_publicly']) && !empty($job['min_salary'])): ?>
                            <div class="text-success small fw-semibold mb-3">
                                <i class="fa-solid fa-indian-rupee-sign me-1"></i>
                                <?php echo number_format((float)$job['min_salary']) . ' - ' . number_format((float)$job['max_salary']) . ' / Year'; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary border-opacity-25">
                            <span class="text-muted small"><?php echo (int) $job['applications_count']; ?> applicants</span>
                            <a href="index.php?route=careers/job/<?php echo urlencode($job['slug']); ?>" class="btn btn-sm btn-primary px-3 fw-semibold">
                                View & Apply <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="border-top border-secondary border-opacity-25 py-4 text-center text-muted small">
        <div class="container">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> Raptor Platform. All rights reserved. Data processed in compliance with DPDP Act 2023.</p>
        </div>
    </footer>
</body>
</html>

