<?php
// Raptor CRM - Email Campaigns Dashboard
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Email Campaigns & Broadcasts</h3>
            <p class="text-secondary small mb-0">High-deliverability email broadcasts with A/B testing, pre-send quality gates, and warm-up rate throttling.</p>
        </div>
        <div>
            <a href="index.php?route=email_campaigns/campaignCreate" class="btn btn-primary btn-sm px-3">
                <i class="fa-solid fa-plus me-1"></i>Create Campaign
            </a>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/campaigns">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns (<?php echo number_format($total_count); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/templates">
                <i class="fa-solid fa-file-code me-1"></i>Templates
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/contacts">
                <i class="fa-solid fa-users me-1"></i>Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/lists">
                <i class="fa-solid fa-list me-1"></i>Lists & Payroll Sync
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium" href="index.php?route=email_campaigns/segments">
                <i class="fa-solid fa-filter me-1"></i>Segments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression
            </a>
        </li>
    </ul>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="route" value="email_campaigns/campaigns">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Search campaign name or subject..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="queued" <?php echo $filters['status'] === 'queued' ? 'selected' : ''; ?>>Queued</option>
                        <option value="sending" <?php echo $filters['status'] === 'sending' ? 'selected' : ''; ?>>Sending</option>
                        <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="paused" <?php echo $filters['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    <a href="index.php?route=email_campaigns/campaigns" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-arrow-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Campaigns Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th>Campaign</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Quality Gate</th>
                        <th>Recipients</th>
                        <th>Performance</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-paper-plane fs-1 mb-2 d-block"></i>
                                No campaigns found. Create and launch your first email broadcast.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <a href="index.php?route=email_campaigns/campaignEdit&id=<?php echo $c->campaign_id; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($c->name); ?>
                                        </a>
                                    </div>
                                    <div class="small text-secondary">
                                        <i class="fa-solid fa-heading text-muted me-1"></i><?php echo htmlspecialchars($c->subject_a); ?>
                                        <?php if (!empty($c->subject_b)): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1">A/B Test</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c->segment_name)): ?>
                                        <span class="badge bg-primary-subtle text-primary border"><i class="fa-solid fa-filter me-1"></i><?php echo htmlspecialchars($c->segment_name); ?></span>
                                    <?php elseif (!empty($c->list_name)): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border"><i class="fa-solid fa-list me-1"></i><?php echo htmlspecialchars($c->list_name); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = match($c->status) {
                                        'draft' => 'secondary',
                                        'scheduled' => 'info',
                                        'queued' => 'primary',
                                        'sending' => 'warning',
                                        'completed' => 'success',
                                        'paused' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>-subtle text-<?php echo $badge; ?> border border-<?php echo $badge; ?>-subtle">
                                        <?php echo ucfirst($c->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c->pre_send_checklist_passed): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>Checklist Passed</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fa-solid fa-hourglass-half me-1"></i>Pending Audit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo number_format($c->total_recipients); ?></span>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-semibold"><i class="fa-solid fa-envelope-circle-check me-1"></i><?php echo number_format($c->delivered_count); ?></span> &bull; 
                                        <span class="text-primary"><i class="fa-solid fa-envelope-open me-1"></i><?php echo number_format($c->opened_count); ?></span> &bull; 
                                        <span class="text-info"><i class="fa-solid fa-arrow-pointer me-1"></i><?php echo number_format($c->clicked_count); ?></span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="index.php?route=email_campaigns/campaignEdit&id=<?php echo $c->campaign_id; ?>" class="btn btn-sm btn-outline-primary" title="Edit Campaign & Pre-Send Gate">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if ($c->status === 'draft'): ?>
                                            <form method="POST" action="index.php?route=email_campaigns/campaignDelete" onsubmit="return confirm('Delete this campaign draft?');" class="d-inline">
                                                <input type="hidden" name="campaign_id" value="<?php echo $c->campaign_id; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Draft">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted">Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_count); ?> total)</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == 1 || $p == $total_pages || abs($p - $current_page) <= 2): ?>
                                <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="index.php?route=email_campaigns/campaigns&page=<?php echo $p; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php elseif (abs($p - $current_page) == 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

