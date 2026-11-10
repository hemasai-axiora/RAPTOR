<?php
/**
 * Raptor CRM — Internal Job Postings Management View (Sprint 3)
 * -----------------------------------------------------------------------------------
 * Design System: Bootstrap 5.3 + Glassmorphic Cards + Action Modals
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment/dashboard" class="text-decoration-none">Recruitment</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Job Postings</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-1 text-main">Job Postings & Career Site Listings</h1>
            <p class="text-muted mb-0">Manage active external vacancies, candidate traffic volume, and publishing states.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=careers" target="_blank" class="btn btn-outline-primary px-3 shadow-sm">
                <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Visit Career Portal
            </a>
        </div>
    </div>

    <!-- Approved Requisitions Pending Publication Banner -->
    <?php if (!empty($pending_publish)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-primary-subtle border-start border-primary border-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-primary">
                        <i class="fa-solid fa-bullhorn me-2"></i>Approved Requisitions Ready to Publish (<?php echo count($pending_publish); ?>)
                    </h5>
                    <p class="text-muted mb-0 small">These requisitions have received all executive approvals and can now be published to the public career site.</p>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <?php foreach ($pending_publish as $pReq): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="p-3 rounded-3 bg-card border border-light-subtle shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <code class="small fw-bold"><?php echo htmlspecialchars($pReq['requisition_code']); ?></code>
                                <span class="badge bg-success-subtle text-success">Approved</span>
                            </div>
                            <h6 class="fw-bold mb-1 text-main"><?php echo htmlspecialchars($pReq['title']); ?></h6>
                            <span class="text-muted small"><?php echo htmlspecialchars($pReq['department']); ?> &bull; <?php echo (int)$pReq['vacancies']; ?> Vacancies</span>
                        </div>
                        <form method="POST" action="index.php?route=recruitment_job/publish/<?php echo (int)$pReq['requisition_id']; ?>" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="requisition_id" value="<?php echo (int)$pReq['requisition_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                                <i class="fa-solid fa-globe me-1"></i>Publish to Career Site
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active Job Postings Table -->
    <div class="card border-0 shadow-sm rounded-4 bg-card">
        <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-main">Active Job Listings</h5>
                <span class="text-muted small">Live postings indexed on the careers portal</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($jobs)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-globe fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5 class="fw-bold text-main">No jobs published yet</h5>
                    <p class="text-muted mb-3">Approve a job requisition above to publish your first public career listing.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="text-muted small border-bottom">
                            <tr>
                                <th class="ps-4">Job Code</th>
                                <th>Position Title</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Model</th>
                                <th>Views</th>
                                <th>Applicants</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td class="ps-4">
                                    <code class="fw-bold"><?php echo htmlspecialchars($job['job_code']); ?></code>
                                </td>
                                <td>
                                    <a href="index.php?route=careers/job/<?php echo urlencode($job['slug']); ?>" target="_blank" class="fw-semibold text-main text-decoration-none">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square ms-1 small text-muted"></i>
                                    </a>
                                    <div class="text-muted small">Req: <?php echo htmlspecialchars($job['requisition_code']); ?></div>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($job['department']); ?></span></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($job['location']); ?></td>
                                <td><span class="badge bg-light text-muted border text-capitalize"><?php echo str_replace('_', ' ', $job['workplace_arrangement']); ?></span></td>
                                <td><span class="fw-bold text-main"><?php echo (int)$job['views_count']; ?></span></td>
                                <td>
                                    <a href="index.php?route=recruitment/applications&job_id=<?php echo (int)$job['job_id']; ?>" class="fw-bold text-primary text-decoration-none">
                                        <?php echo (int)$job['applications_count']; ?> Candidates
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $jst = $job['status'] ?? 'published';
                                    $jstBadge = match($jst) {
                                        'published' => 'bg-success text-white',
                                        'paused'    => 'bg-warning text-dark',
                                        'closed'    => 'bg-secondary text-white',
                                        default     => 'bg-light text-muted border'
                                    };
                                    ?>
                                    <span class="badge <?php echo $jstBadge; ?> text-capitalize px-2 py-1">
                                        <?php echo htmlspecialchars($jst); ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?route=careers/job/<?php echo urlencode($job['slug']); ?>" target="_blank" class="btn btn-outline-secondary" title="View Public Page">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if ($job['status'] === 'published'): ?>
                                        <a href="index.php?route=recruitment_job/pause/<?php echo (int)$job['job_id']; ?>" class="btn btn-outline-warning" title="Pause Applications">
                                            <i class="fa-solid fa-pause"></i>
                                        </a>
                                        <?php elseif ($job['status'] === 'paused'): ?>
                                        <a href="index.php?route=recruitment_job/publish/<?php echo (int)$job['requisition_id']; ?>" class="btn btn-outline-success" title="Resume Applications">
                                            <i class="fa-solid fa-play"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($job['status'] !== 'closed'): ?>
                                        <a href="index.php?route=recruitment_job/close/<?php echo (int)$job['job_id']; ?>" class="btn btn-outline-danger" title="Close Position">
                                            <i class="fa-solid fa-ban"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

