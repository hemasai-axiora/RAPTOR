<?php
// Raptor CRM - Email Contacts & Audiences Directory
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-envelope-open-text text-primary me-2"></i>Email Campaigns: Contacts & Audiences</h3>
            <p class="text-secondary small mb-0">Manage email subscribers, tags, consent metadata, and deliverability compliance.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?route=email_campaigns/import" class="btn btn-outline-primary btn-sm px-3">
                <i class="fa-solid fa-file-csv me-1"></i>Import CSV
            </a>
            <a href="index.php?route=email_campaigns/contactCreate" class="btn btn-primary btn-sm px-3">
                <i class="fa-solid fa-user-plus me-1"></i>Add Contact
            </a>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/contacts">
                <i class="fa-solid fa-users me-1"></i>Contacts (<?php echo number_format($total_count); ?>)
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
                <i class="fa-solid fa-ban me-1"></i>Global Suppression List
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
                <input type="hidden" name="route" value="email_campaigns/contacts">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="search" class="form-control form-control-sm border-start-0" placeholder="Search email, name..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="subscribed" <?php echo $filters['status'] === 'subscribed' ? 'selected' : ''; ?>>Subscribed</option>
                        <option value="unsubscribed" <?php echo $filters['status'] === 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed</option>
                        <option value="bounced" <?php echo $filters['status'] === 'bounced' ? 'selected' : ''; ?>>Bounced</option>
                        <option value="complained" <?php echo $filters['status'] === 'complained' ? 'selected' : ''; ?>>Complained</option>
                        <option value="suppressed" <?php echo $filters['status'] === 'suppressed' ? 'selected' : ''; ?>>Suppressed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="list_id" class="form-select form-select-sm">
                        <option value="">All Lists</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?php echo $l->list_id; ?>" <?php echo ($filters['list_id'] == $l->list_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l->name); ?> <?php echo $l->is_internal ? '(Internal)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="tag_id" class="form-select form-select-sm">
                        <option value="">All Tags</option>
                        <?php foreach ($tags as $t): ?>
                            <option value="<?php echo $t->tag_id; ?>" <?php echo ($filters['tag_id'] == $t->tag_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="source" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        <option value="manual_entry" <?php echo $filters['source'] === 'manual_entry' ? 'selected' : ''; ?>>Manual Entry</option>
                        <option value="csv_import" <?php echo $filters['source'] === 'csv_import' ? 'selected' : ''; ?>>CSV Import</option>
                        <option value="internal_payroll" <?php echo $filters['source'] === 'internal_payroll' ? 'selected' : ''; ?>>Internal Payroll</option>
                        <option value="webform_optin" <?php echo $filters['source'] === 'webform_optin' ? 'selected' : ''; ?>>Webform Opt-in</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    <a href="index.php?route=email_campaigns/contacts" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-arrow-rotate-left"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th>Email & Contact</th>
                        <th>Lists</th>
                        <th>Tags</th>
                        <th>Status</th>
                        <th>Consent & Source</th>
                        <th>Deliverability Flags</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-envelope-open fs-2 mb-2 d-block"></i>
                                No contacts found matching criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contacts as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($c->email); ?></div>
                                    <div class="small text-secondary">
                                        <?php echo htmlspecialchars(trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'No Name'); ?>
                                        <?php if (!empty($c->company_name)): ?>
                                            &bull; <span class="text-muted"><?php echo htmlspecialchars($c->company_name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c->list_names)): ?>
                                        <?php foreach (explode(', ', $c->list_names) as $ln): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border me-1"><?php echo htmlspecialchars($ln); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c->tag_names)): ?>
                                        <?php foreach (explode(', ', $c->tag_names) as $tn): ?>
                                            <span class="badge bg-primary-subtle text-primary border me-1"><i class="fa-solid fa-tag me-1 small"></i><?php echo htmlspecialchars($tn); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match($c->status) {
                                        'subscribed' => 'success',
                                        'unsubscribed' => 'warning',
                                        'bounced' => 'danger',
                                        'complained' => 'dark',
                                        'suppressed' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>-subtle text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?>-subtle">
                                        <?php echo ucfirst($c->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-medium text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $c->source))); ?></div>
                                    <div class="small text-muted" title="Consent recorded at <?php echo htmlspecialchars($c->consent_timestamp ?? 'N/A'); ?>">
                                        <i class="fa-solid fa-shield-halved text-success me-1"></i><?php echo htmlspecialchars($c->consent_type); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($c->is_role_account): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle me-1" title="Generic role account (info@, admin@). May lower deliverability.">Role Account</span>
                                    <?php endif; ?>
                                    <?php if ($c->is_disposable): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Known disposable / burner domain.">Disposable</span>
                                    <?php endif; ?>
                                    <?php if (!$c->is_role_account && !$c->is_disposable): ?>
                                        <span class="badge bg-light text-muted border">Clean</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="index.php?route=email_campaigns/contactExport&id=<?php echo $c->contact_id; ?>">
                                                    <i class="fa-solid fa-file-arrow-down me-2 text-primary"></i>GDPR Data Export
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" action="index.php?route=email_campaigns/contactDelete" onsubmit="return confirm('Are you sure? This will delete the contact and all associated logs per GDPR Right to be Forgotten.');">
                                                    <input type="hidden" name="contact_id" value="<?php echo $c->contact_id; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash me-2"></i>Delete Contact
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
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
                                    <a class="page-link" href="index.php?route=email_campaigns/contacts&page=<?php echo $p; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $p; ?></a>
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

