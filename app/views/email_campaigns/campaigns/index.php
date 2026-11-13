<?php
// Raptor CRM - Email Campaigns Dashboard (Modern FlowMail & Marketigo Edition)
$stats = $stats ?? [
    'total_campaigns' => 0,
    'active_campaigns' => 0,
    'scheduled_campaigns' => 0,
    'completed_campaigns' => 0,
    'total_sent' => 0,
    'total_delivered' => 0,
    'total_opened' => 0,
    'total_clicked' => 0,
    'avg_open_rate' => 0.0,
    'avg_click_rate' => 0.0
];
?>
<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1 tracking-tight text-dark">Campaigns</h2>
            <p class="text-secondary small mb-0">Welcome back! Monitor broadcasts, track live deliverability, and optimize audience engagement.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php?route=email_campaigns/analytics" class="btn btn-outline-secondary btn-sm px-3 rounded-pill fw-medium">
                <i class="fa-solid fa-chart-pie me-1 text-primary"></i>Email Analytics
            </a>
            <a href="index.php?route=email_campaigns/campaignCreate" class="btn btn-primary btn-sm px-4 rounded-pill fw-medium shadow-sm" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                <i class="fa-solid fa-plus me-1"></i>New Campaign
            </a>
        </div>
    </div>

    <!-- Sub-navigation Pills -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link active fw-medium rounded-pill px-3 py-2" href="index.php?route=email_campaigns/campaigns">
                <i class="fa-solid fa-paper-plane me-1"></i>Campaigns (<?php echo number_format($total_count); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium rounded-pill px-3 py-2 text-secondary" href="index.php?route=email_campaigns/templates">
                <i class="fa-solid fa-file-code me-1"></i>Templates
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium rounded-pill px-3 py-2 text-secondary" href="index.php?route=email_campaigns/contacts">
                <i class="fa-solid fa-users me-1"></i>Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium rounded-pill px-3 py-2 text-secondary" href="index.php?route=email_campaigns/lists">
                <i class="fa-solid fa-layer-group me-1"></i>Contact Lists
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium rounded-pill px-3 py-2 text-secondary" href="index.php?route=email_campaigns/segments">
                <i class="fa-solid fa-filter me-1"></i>Segments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-medium rounded-pill px-3 py-2 text-danger" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression
            </a>
        </li>
    </ul>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
                <div><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-warning"></i>
                <div><?php echo $_SESSION['flash_warning']; unset($_SESSION['flash_warning']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-xmark fs-5 me-2 text-danger"></i>
                <div><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 4 Modern KPI Cards (FlowMail reference design) -->
    <div class="row g-3 mb-4">
        <!-- Total Campaigns -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-medium">Total Campaigns</span>
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="fa-regular fa-envelope"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['total_campaigns'] ?? 0); ?></h3>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 small fw-semibold">
                        <i class="fa-solid fa-arrow-trend-up me-1"></i>Active
                    </span>
                </div>
                <span class="text-muted small mt-1">All broadcasts in system</span>
            </div>
        </div>

        <!-- Active & Sending -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-medium">Active & Sending</span>
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['active_campaigns'] ?? 0); ?></h3>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 small fw-semibold">
                        <i class="fa-solid fa-bolt me-1"></i>Live
                    </span>
                </div>
                <span class="text-muted small mt-1">In dispatch or queue</span>
            </div>
        </div>

        <!-- Scheduled / Queued -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-medium">Scheduled</span>
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fa-regular fa-clock"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['scheduled_campaigns'] ?? 0); ?></h3>
                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1 small fw-semibold">
                        Pending
                    </span>
                </div>
                <span class="text-muted small mt-1">Automated send pipeline</span>
            </div>
        </div>

        <!-- Avg. Open Rate -->
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-medium">Avg. Open Rate</span>
                    <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark"><?php echo number_format($stats['avg_open_rate'] ?? 0, 1); ?>%</h3>
                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1 small fw-semibold">
                        <i class="fa-solid fa-bullseye me-1"></i>Benchmark
                    </span>
                </div>
                <span class="text-muted small mt-1">Total sent: <?php echo number_format($stats['total_sent'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar (FlowMail Layout) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="route" value="email_campaigns/campaigns">
                <div class="col-md-3">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fa-solid fa-layer-group text-primary me-2"></i>Recent Campaigns
                    </h5>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm bg-light border-0" placeholder="Search campaigns by name or subject..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm bg-light border-0">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="queued" <?php echo $filters['status'] === 'queued' ? 'selected' : ''; ?>>Queued</option>
                        <option value="sending" <?php echo $filters['status'] === 'sending' ? 'selected' : ''; ?>>Sending</option>
                        <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="paused" <?php echo $filters['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                        <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill">Filter</button>
                    <a href="index.php?route=email_campaigns/campaigns" class="btn btn-sm btn-light rounded-pill px-3" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Campaigns FlowMail Cards List -->
    <?php if (empty($campaigns)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="mb-3">
                <div class="d-inline-flex p-4 rounded-circle" style="background: rgba(99, 102, 241, 0.08); color: #6366f1;">
                    <i class="fa-solid fa-paper-plane fa-3x"></i>
                </div>
            </div>
            <h5 class="fw-bold text-dark">No email campaigns found</h5>
            <p class="text-secondary small mb-4">Start by creating your first broadcast with built-in A/B split testing and deliverability pre-flight checks.</p>
            <div>
                <a href="index.php?route=email_campaigns/campaignCreate" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i>Create First Campaign
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3 mb-4">
            <?php foreach ($campaigns as $c): ?>
                <?php
                $sent = (int)$c->sent_count;
                $delivered = (int)$c->delivered_count;
                $opened = (int)$c->opened_count;
                $clicked = (int)$c->clicked_count;
                $openRate = $sent > 0 ? round(($opened / $sent) * 100, 1) : 0;
                $clickRate = $opened > 0 ? round(($clicked / $opened) * 100, 1) : 0;

                // Status styling
                $statusConfig = match($c->status) {
                    'sending', 'queued' => ['badge' => 'success', 'icon' => 'fa-bolt', 'label' => 'Active'],
                    'completed' => ['badge' => 'primary', 'icon' => 'fa-check', 'label' => 'Completed'],
                    'scheduled' => ['badge' => 'warning', 'icon' => 'fa-clock', 'label' => 'Scheduled'],
                    'paused' => ['badge' => 'warning', 'icon' => 'fa-pause', 'label' => 'Paused'],
                    'failed' => ['badge' => 'danger', 'icon' => 'fa-triangle-exclamation', 'label' => 'Failed'],
                    default => ['badge' => 'secondary', 'icon' => 'fa-pencil', 'label' => 'Draft']
                };

                $hasDeliveryIssue = ($c->status === 'failed' || $c->status === 'paused' || (!empty($c->latest_queue_error) && $sent === 0));
                ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white transition-all campaign-card" style="border: 1px solid #f1f5f9 !important;">
                    <!-- Top Row: Icon, Title, Actions & Status -->
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 pb-3 border-bottom">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 48px; height: 48px; background: rgba(99, 102, 241, 0.1);">
                                <i class="fa-solid fa-envelope fs-5" style="color: #6366f1;"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h5 class="fw-bold mb-0">
                                        <a href="index.php?route=email_campaigns/campaignEdit&id=<?php echo $c->campaign_id; ?>" class="text-decoration-none text-dark text-hover-primary">
                                            <?php echo htmlspecialchars($c->name); ?>
                                        </a>
                                    </h5>
                                    <?php if (!empty($c->subject_b)): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill small">
                                            <i class="fa-solid fa-code-compare me-1"></i>A/B Test
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-secondary small mt-1">
                                    <i class="fa-regular fa-comment-dots text-muted me-1"></i><?php echo htmlspecialchars($c->subject_a); ?>
                                    <span class="mx-2 text-muted">&bull;</span>
                                    <i class="fa-regular fa-calendar text-muted me-1"></i><?php echo date('M j, Y \a\t g:i A', strtotime($c->created_at)); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Badges & Actions -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?php echo $statusConfig['badge']; ?>-subtle text-<?php echo $statusConfig['badge']; ?> border border-<?php echo $statusConfig['badge']; ?>-subtle rounded-pill px-3 py-2 fw-semibold">
                                <i class="fa-solid <?php echo $statusConfig['icon']; ?> me-1"></i><?php echo $statusConfig['label']; ?>
                            </span>

                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle p-2" type="button" data-bs-toggle="dropdown" style="width: 32px; height: 32px;">
                                    <i class="fa-solid fa-ellipsis-vertical text-muted"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                    <li>
                                        <a class="dropdown-item small" href="index.php?route=email_campaigns/campaignEdit&id=<?php echo $c->campaign_id; ?>">
                                            <i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit / Inspect
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item small" href="index.php?route=email_campaigns/analytics&campaign_id=<?php echo $c->campaign_id; ?>">
                                            <i class="fa-solid fa-chart-line me-2 text-info"></i>Detailed Analytics
                                        </a>
                                    </li>
                                    <?php if ($c->status === 'draft'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="index.php?route=email_campaigns/campaignDelete" onsubmit="return confirm('Delete this draft campaign permanently?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                                                <input type="hidden" name="campaign_id" value="<?php echo $c->campaign_id; ?>">
                                                <button type="submit" class="dropdown-item small text-danger">
                                                    <i class="fa-solid fa-trash me-2"></i>Delete Draft
                                                </button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Middle Row: Metrics Strip (FlowMail 5-column metric blocks) -->
                    <div class="row g-2 pt-3 pb-2 text-center text-sm-start">
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Sent</span>
                            <span class="fw-bold fs-5 text-dark"><?php echo number_format($sent); ?></span>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Delivered</span>
                            <span class="fw-bold fs-5 text-success"><?php echo number_format($delivered); ?></span>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Opens</span>
                            <span class="fw-bold fs-5 text-primary"><?php echo number_format($opened); ?></span>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Clicks</span>
                            <span class="fw-bold fs-5 text-info"><?php echo number_format($clicked); ?></span>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Open Rate</span>
                            <span class="fw-bold fs-5 text-dark"><?php echo $openRate; ?>%</span>
                        </div>
                        <div class="col-6 col-sm-4 col-md-2">
                            <span class="text-secondary small d-block">Click Rate</span>
                            <span class="fw-bold fs-5 text-dark"><?php echo $clickRate; ?>%</span>
                        </div>
                    </div>

                    <!-- Bottom Row: Audience Tag & Delivery Notices -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top mt-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if (!empty($c->segment_name)): ?>
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-1 small">
                                    <i class="fa-solid fa-filter text-primary me-1"></i>Segment: <?php echo htmlspecialchars($c->segment_name); ?>
                                </span>
                            <?php elseif (!empty($c->list_name)): ?>
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-1 small">
                                    <i class="fa-solid fa-users text-primary me-1"></i>Audience: <?php echo htmlspecialchars($c->list_name); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border rounded-pill px-3 py-1 small">
                                    <i class="fa-solid fa-address-book me-1"></i>All Eligible Contacts
                                </span>
                            <?php endif; ?>

                            <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small">
                                <i class="fa-regular fa-paper-plane me-1"></i><?php echo number_format($c->total_recipients); ?> Recipients
                            </span>

                            <?php if ($c->pre_send_checklist_passed): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">
                                    <i class="fa-solid fa-shield-check me-1"></i>Checklist Passed
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border rounded-pill px-2 py-1 small">
                                    <i class="fa-solid fa-hourglass-half me-1"></i>Audit Pending
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($hasDeliveryIssue): ?>
                            <?php 
                            $noticeMsg = !empty($c->latest_queue_error) ? $c->latest_queue_error : ($c->paused_reason ?: 'Messages deferred or rejected by provider');
                            // Clean notice snippet
                            $cleanSnippet = strlen($noticeMsg) > 75 ? substr($noticeMsg, 0, 75) . '...' : $noticeMsg;
                            ?>
                            <div class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2 small fw-normal d-flex align-items-center" title="<?php echo htmlspecialchars($noticeMsg); ?>">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Notice: <?php echo htmlspecialchars($cleanSnippet); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white d-flex flex-row justify-content-between align-items-center">
            <span class="small text-muted">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_count); ?> total campaigns)</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <?php if ($p == 1 || $p == $total_pages || abs($p - $current_page) <= 2): ?>
                            <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link rounded-circle mx-1" href="index.php?route=email_campaigns/campaigns&page=<?php echo $p; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php elseif (abs($p - $current_page) == 3): ?>
                            <li class="page-item disabled"><span class="page-link border-0">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<style>
.campaign-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.campaign-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01) !important;
}
.text-hover-primary:hover {
    color: #4f46e5 !important;
}
</style>
