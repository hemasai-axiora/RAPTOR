<?php
/**
 * Raptor CRM — Candidate Directory View (Sprint 4)
 * -------------------------------------------------------------------------
 */
?>
<div class="container-fluid py-4">
    <!-- Header Breadcrumb & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment">Recruitment ATS</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Candidate Master Directory</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-0">Candidate Master Directory</h2>
            <p class="text-muted small mb-0">Central repository of applicants, talent pools, and candidate master profiles.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=recruitment_application/index" class="btn btn-outline-primary">
                <i class="bi bi-kanban me-1"></i> ATS Kanban Pipeline
            </a>
            <a href="index.php?route=recruitment_candidate/create" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i> Add Candidate
            </a>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="recruitment_candidate/index">

                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Search Candidates</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Search by name, email, phone, or skill..." 
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Application Source</label>
                    <select name="source" class="form-select">
                        <option value="">All Sources</option>
                        <option value="career_portal" <?= ($filters['source'] ?? '') === 'career_portal' ? 'selected' : '' ?>>Career Portal</option>
                        <option value="linkedin" <?= ($filters['source'] ?? '') === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                        <option value="referral" <?= ($filters['source'] ?? '') === 'referral' ? 'selected' : '' ?>>Referral</option>
                        <option value="direct" <?= ($filters['source'] ?? '') === 'direct' ? 'selected' : '' ?>>Direct Intake</option>
                        <option value="agency" <?= ($filters['source'] ?? '') === 'agency' ? 'selected' : '' ?>>Agency</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Min Experience (Yrs)</label>
                    <input type="number" step="0.5" min="0" name="min_exp" class="form-control" 
                           placeholder="e.g. 3.0" value="<?= htmlspecialchars((string)($filters['min_exp'] ?? '')) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="is_blacklisted" class="form-select">
                        <option value="">All Candidates</option>
                        <option value="0" <?= ($filters['is_blacklisted'] ?? '') === '0' ? 'selected' : '' ?>>Active Candidates</option>
                        <option value="1" <?= ($filters['is_blacklisted'] ?? '') === '1' ? 'selected' : '' ?>>Blacklisted Only</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="index.php?route=recruitment_candidate/index" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Candidate Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title fw-bold mb-0">Total Profiles: <span class="badge bg-secondary"><?= (int)$total ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Candidate</th>
                        <th>Current Role & Company</th>
                        <th>Experience</th>
                        <th>Primary Skills</th>
                        <th>Source</th>
                        <th>Applications</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-6 d-block mb-2"></i>
                                No candidate profiles found matching current filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($candidates as $c): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3 bg-primary text-white fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <?= strtoupper(substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="index.php?route=recruitment_candidate/show/<?= $c['candidate_id'] ?>" class="fw-bold text-decoration-none text-dark">
                                                <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                            </a>
                                            <?php if ((int)$c['is_blacklisted'] === 1): ?>
                                                <span class="badge bg-danger ms-1">Blacklisted</span>
                                            <?php endif; ?>
                                            <div class="small text-muted"><?= htmlspecialchars($c['candidate_code']) ?> &bull; <?= htmlspecialchars($c['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($c['current_designation'] ?: 'Applicant') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($c['current_company'] ?: 'Self-Employed / Independent') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= (float)$c['total_experience_years'] ?> Yrs</span>
                                </td>
                                <td style="max-width: 250px;">
                                    <?php 
                                    $skills = array_filter(array_map('trim', explode(',', (string)$c['primary_skills'])));
                                    foreach (array_slice($skills, 0, 3) as $sk): 
                                    ?>
                                        <span class="badge bg-secondary-subtle text-secondary me-1 mb-1"><?= htmlspecialchars($sk) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($skills) > 3): ?>
                                        <span class="badge bg-light text-muted">+<?= count($skills) - 3 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle text-capitalize">
                                        <?= htmlspecialchars(str_replace('_', ' ', $c['source'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= (int)$c['applications_count'] ?> active
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="index.php?route=recruitment_candidate/show/<?= $c['candidate_id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View Candidate 360">
                                        <i class="bi bi-eye me-1"></i> Profile
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

