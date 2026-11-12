<?php
// Raptor CRM - Global Suppression List View
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-danger"><i class="fa-solid fa-ban me-2"></i>Global Suppression List</h3>
            <p class="text-secondary small mb-0">Critical deliverability firewall: Addresses on this list are permanently blocked from all campaign sends to safeguard sender domain reputation.</p>
        </div>
        <div>
            <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addSuppressionModal">
                <i class="fa-solid fa-plus me-1"></i>Add Suppression
            </button>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
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
            <a class="nav-link active fw-medium bg-danger text-white" href="index.php?route=email_campaigns/suppressions">
                <i class="fa-solid fa-ban me-1"></i>Global Suppression List (<?php echo number_format($total_count); ?>)
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

    <!-- Deliverability Architecture Callout -->
    <div class="alert alert-warning border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation fs-3 text-warning"></i>
            <div>
                <h6 class="fw-bold mb-1">Strict Deliverability Invariant:</h6>
                <p class="small mb-0">
                    Before any email is dispatched by the queue engine, the recipient address is verified against this table in real-time.
                    Once an email hard bounces, registers a spam complaint, or clicks unsubscribe, it is immediately suppressed across all present and future campaigns.
                </p>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="index.php" class="row g-2 align-items-center">
                <input type="hidden" name="route" value="email_campaigns/suppressions">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Search suppressed email address..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="reason" class="form-select form-select-sm">
                        <option value="">All Suppression Reasons</option>
                        <?php foreach ($reasons as $rKey => $rLabel): ?>
                            <option value="<?php echo htmlspecialchars($rKey); ?>" <?php echo ($filters['reason'] === $rKey) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    <a href="index.php?route=email_campaigns/suppressions" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-arrow-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Suppressions Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th>Suppressed Email</th>
                        <th>Reason</th>
                        <th>Suppressed At</th>
                        <th>Campaign Context</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppressions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-shield-heart fs-2 mb-2 text-success d-block"></i>
                                No suppressed addresses found matching criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suppressions as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($s->email); ?></div>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match($s->reason) {
                                        'unsubscribe' => 'warning',
                                        'hard_bounce' => 'danger',
                                        'spam_complaint' => 'dark',
                                        'soft_bounce_repeat' => 'secondary',
                                        default => 'primary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>-subtle text-<?php echo $badgeClass; ?> border border-<?php echo $badgeClass; ?>-subtle">
                                        <?php echo htmlspecialchars($reasons[$s->reason] ?? ucfirst(str_replace('_', ' ', $s->reason))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-secondary"><?php echo htmlspecialchars($s->created_at); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($s->campaign_name)): ?>
                                        <span class="small fw-medium"><?php echo htmlspecialchars($s->campaign_name); ?></span>
                                    <?php else: ?>
                                        <span class="small text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?php echo htmlspecialchars($s->notes ?? 'N/A'); ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if (in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo'], true)): ?>
                                        <form method="POST" action="index.php?route=email_campaigns/suppressionRemove" onsubmit="return confirm('WARNING: Removing an email from suppression can damage domain sender reputation if the recipient previously marked mail as spam or bounced. Proceed only if legitimate consent is re-verified.');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($s->email); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Un-suppress address">
                                                <i class="fa-solid fa-trash me-1"></i>Un-suppress
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small" title="Only Super Admin/CEO may un-suppress"><i class="fa-solid fa-lock"></i> Protected</span>
                                    <?php endif; ?>
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
                                    <a class="page-link" href="index.php?route=email_campaigns/suppressions&page=<?php echo $p; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $p; ?></a>
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

<!-- Modal: Add Suppression -->
<div class="modal fade" id="addSuppressionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/suppressionAdd">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-ban me-2"></i>Add Address to Global Suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Suppression Reason</label>
                        <select name="reason" class="form-select">
                            <?php foreach ($reasons as $rKey => $rLabel): ?>
                                <option value="<?php echo htmlspecialchars($rKey); ?>"><?php echo htmlspecialchars($rLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Audit Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Context or ticket reference for manual suppression"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-check me-1"></i>Suppress Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

