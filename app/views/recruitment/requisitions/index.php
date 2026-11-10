<?php
/**
 * Raptor CRM — Job Requisitions Listing View (Sprint 2)
 * -----------------------------------------------------------------------------------
 * Design System: Bootstrap 5.3 + Glassmorphic Tokens + Filter Pills
 */
?>

<div class="container-fluid px-4 py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php?route=recruitment/dashboard" class="text-decoration-none">Recruitment</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Requisitions</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-1 text-main">Job Requisitions</h1>
            <p class="text-muted mb-0">Authorized organizational headcount demands and approval lifecycles.</p>
        </div>
        <div>
            <a href="index.php?route=recruitment_requisition/create" class="btn btn-primary px-3 shadow-sm">
                <i class="fa-solid fa-plus me-2"></i>Create Requisition
            </a>
        </div>
    </div>

    <!-- Filter Pills Row -->
    <?php
    $currentStatus = $filters['status'] ?? 'all';
    $statusPills = [
        'all'              => ['label' => 'All Requisitions', 'icon' => 'fa-list'],
        'pending_approval' => ['label' => 'Pending Approval', 'icon' => 'fa-clock'],
        'approved'         => ['label' => 'Approved',         'icon' => 'fa-check'],
        'open'             => ['label' => 'Open / Active',    'icon' => 'fa-door-open'],
        'draft'            => ['label' => 'Drafts',           'icon' => 'fa-pen'],
        'closed'           => ['label' => 'Closed',           'icon' => 'fa-lock']
    ];
    ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($statusPills as $sKey => $sInfo): 
            $isActive = ($currentStatus === $sKey);
        ?>
        <a href="index.php?route=recruitment/requisitions&status=<?php echo urlencode($sKey); ?>&department=<?php echo urlencode($filters['department'] ?? ''); ?>&search=<?php echo urlencode($filters['search'] ?? ''); ?>" 
           class="btn btn-sm rounded-pill px-3 <?php echo $isActive ? 'btn-primary' : 'btn-outline-secondary'; ?>">
            <i class="fa-solid <?php echo $sInfo['icon']; ?> me-1"></i><?php echo $sInfo['label']; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Department Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-card">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="route" value="recruitment/requisitions">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">

                <div class="col-md-6 col-sm-12">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by requisition code, title..." 
                               value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (($filters['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <?php if (!empty($filters['search']) || !empty($filters['department']) || $currentStatus !== 'all'): ?>
                    <a href="index.php?route=recruitment/requisitions" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Requisitions Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-card">
        <div class="card-body p-0">
            <?php if (empty($requisitions)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-clipboard-question fa-3x mb-3 text-secondary opacity-50"></i>
                    <h5 class="fw-bold text-main">No requisitions found</h5>
                    <p class="text-muted mb-3">No job requisitions match your selected status or filter criteria.</p>
                    <a href="index.php?route=recruitment_requisition/create" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-plus me-1"></i>Create First Requisition
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="text-muted small border-bottom">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Position Title</th>
                                <th>Department</th>
                                <th>Hiring Manager</th>
                                <th>Vacancies</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Target Date</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requisitions as $req): ?>
                            <tr>
                                <td class="ps-4">
                                    <code class="fw-bold"><?php echo htmlspecialchars($req['requisition_code']); ?></code>
                                </td>
                                <td>
                                    <a href="index.php?route=recruitment_requisition/show/<?php echo (int)$req['requisition_id']; ?>" 
                                       class="fw-semibold text-main text-decoration-none">
                                        <?php echo htmlspecialchars($req['title']); ?>
                                    </a>
                                    <div class="text-muted small">
                                        <span class="text-capitalize"><?php echo str_replace('_', ' ', $req['employment_type']); ?></span> &bull; 
                                        <span class="text-capitalize"><?php echo str_replace('_', ' ', $req['workplace_arrangement']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                                        <?php echo htmlspecialchars($req['department']); ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo htmlspecialchars($req['hiring_manager_name'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo (int) $req['vacancies']; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $prio = $req['hiring_priority'] ?? 'medium';
                                    $prioBadge = match($prio) {
                                        'urgent' => 'bg-danger text-white',
                                        'high'   => 'bg-warning text-dark',
                                        'low'    => 'bg-light text-muted border',
                                        default  => 'bg-info-subtle text-info'
                                    };
                                    ?>
                                    <span class="badge <?php echo $prioBadge; ?> text-capitalize px-2 py-1">
                                        <?php echo htmlspecialchars($prio); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $st = $req['status'] ?? 'draft';
                                    $stBadge = match($st) {
                                        'approved'          => 'bg-success text-white',
                                        'open'              => 'bg-primary text-white',
                                        'pending_approval'  => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                        'changes_requested' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                        'rejected'          => 'bg-danger-subtle text-danger',
                                        'closed', 'filled'  => 'bg-secondary text-white',
                                        default             => 'bg-light text-dark border'
                                    };
                                    ?>
                                    <span class="badge <?php echo $stBadge; ?> text-capitalize px-2 py-1">
                                        <?php echo str_replace('_', ' ', $st); ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo !empty($req['target_hire_date']) ? date('M d, Y', strtotime($req['target_hire_date'])) : '—'; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="index.php?route=recruitment_requisition/show/<?php echo (int)$req['requisition_id']; ?>" 
                                       class="btn btn-sm btn-outline-primary px-3">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (($pages ?? 1) > 1): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <span class="text-muted small">Showing page <?php echo $page; ?> of <?php echo $pages; ?> (<?php echo $total; ?> total)</span>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="index.php?route=recruitment/requisitions&page=<?php echo $i; ?>&status=<?php echo urlencode($currentStatus); ?>&department=<?php echo urlencode($filters['department'] ?? ''); ?>&search=<?php echo urlencode($filters['search'] ?? ''); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
