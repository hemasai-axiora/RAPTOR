<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Job Details | Raptor Careers'); ?></title>
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
        }
    </style>

    <!-- Google Jobs Structured Data (JSON-LD) -->
    <?php if (!empty($json_ld)): ?>
    <script type="application/ld+json">
        <?php echo $json_ld; ?>
    </script>
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg border-bottom border-secondary border-opacity-25 py-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php?route=careers">
                <i class="fa-solid fa-bolt text-primary"></i> RAPTOR CAREERS
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="index.php?route=careers" class="btn btn-sm btn-outline-secondary px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> All Positions
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-4">
            <!-- Left Column: Job Description Details -->
            <div class="col-lg-7">
                <div class="card career-card rounded-4 p-4 mb-4 shadow-sm">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                            <?php echo htmlspecialchars($job['department']); ?>
                        </span>
                        <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary border-opacity-25 px-2 py-1 text-capitalize">
                            <?php echo str_replace('_', ' ', $job['employment_type']); ?>
                        </span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 text-capitalize">
                            <?php echo str_replace('_', ' ', $job['workplace_arrangement']); ?>
                        </span>
                    </div>

                    <h1 class="h2 fw-bold text-white mb-2"><?php echo htmlspecialchars($job['title']); ?></h1>
                    <div class="text-muted small mb-4">
                        <span><i class="fa-solid fa-location-dot me-1 text-danger"></i><?php echo htmlspecialchars($job['location']); ?></span> &bull; 
                        <span><i class="fa-solid fa-briefcase me-1 text-warning"></i>Min Experience: <?php echo (float)$job['min_experience_years']; ?> Yrs</span>
                    </div>

                    <?php if (!empty($job['show_salary_publicly']) && !empty($job['min_salary'])): ?>
                    <div class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 text-success fw-bold mb-4">
                        <i class="fa-solid fa-indian-rupee-sign me-1"></i>
                        Annual Compensation: ₹ <?php echo number_format((float)$job['min_salary']) . ' - ₹ ' . number_format((float)$job['max_salary']); ?>
                    </div>
                    <?php endif; ?>

                    <h5 class="fw-bold text-white mb-2">Role Overview</h5>
                    <div class="text-light mb-4" style="white-space: pre-line; line-height: 1.7;">
                        <?php echo htmlspecialchars($job['job_description']); ?>
                    </div>

                    <?php if (!empty($job['responsibilities'])): ?>
                    <h5 class="fw-bold text-white mb-2">Key Responsibilities</h5>
                    <div class="text-light mb-4" style="white-space: pre-line; line-height: 1.7;">
                        <?php echo htmlspecialchars($job['responsibilities']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($job['required_skills'])): ?>
                    <h5 class="fw-bold text-white mb-2">Required Skills & Expertise</h5>
                    <div class="text-light" style="white-space: pre-line; line-height: 1.7;">
                        <?php echo htmlspecialchars($job['required_skills']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Direct Candidate Application Intake Form -->
            <div class="col-lg-5">
                <div class="card career-card rounded-4 p-4 shadow-lg sticky-top" style="top: 20px;">
                    <h4 class="fw-bold text-white mb-1">Apply for this Position</h4>
                    <p class="text-muted small mb-4">Submit your profile and resume directly to our hiring team.</p>

                    <form method="POST" action="index.php?route=careers/apply/<?php echo (int)$job['job_id']; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="job_id" value="<?php echo (int)$job['job_id']; ?>">

                        <!-- Honeypot anti-spam trap (must remain empty) -->
                        <input type="text" name="company_url_hp" style="display:none;" tabindex="-1" autocomplete="off">

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-light">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="John" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-light">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="Doe" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold text-light">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="john.doe@example.com" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold text-light">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="+91 98765 43210" required>
                            </div>

                            <div class="col-6">
                                <label class="form-label small fw-semibold text-light">Current Company</label>
                                <input type="text" name="current_company" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="TechCorp">
                            </div>

                            <div class="col-6">
                                <label class="form-label small fw-semibold text-light">Experience (Yrs)</label>
                                <input type="number" name="total_experience_years" class="form-control bg-dark text-white border-secondary border-opacity-25" step="0.5" value="0.0">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold text-light">Key Skills / Technologies</label>
                                <input type="text" name="skills" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="e.g. PHP, MySQL, Docker, REST">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold text-light">Upload Resume (PDF, DOC, DOCX) <span class="text-danger">*</span></label>
                                <input type="file" name="resume" class="form-control bg-dark text-white border-secondary border-opacity-25" accept=".pdf,.doc,.docx" required>
                                <div class="form-text text-muted small">Max file size: 5MB. Evaluated in private encrypted storage.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consent_granted" value="1" id="consentCheck" required>
                                    <label class="form-check-label small text-muted" for="consentCheck">
                                        I consent to Raptor processing my personal data for talent acquisition and recruitment communication under the DPDP Act 2023.
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm">
                                    <i class="fa-solid fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-top border-secondary border-opacity-25 py-4 text-center text-muted small">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Raptor Platform. All applications processed securely.</p>
        </div>
    </footer>
</body>
</html>

