<?php
// Raptor CRM - Email Lists & Internal Payroll Sync View
?>
<div class="container-fluid px-0">
    <!-- Header & Subnav -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-layer-group text-primary me-2"></i>Email Lists & Audiences</h3>
            <p class="text-secondary small mb-0">Organize subscribers into targeted lists and sync internal corporate employees safely.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#createListModal">
                <i class="fa-solid fa-plus me-1"></i>Create List
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
            <a class="nav-link active fw-medium" href="index.php?route=email_campaigns/lists">
                <i class="fa-solid fa-list me-1"></i>Lists & Payroll Sync (<?php echo count($lists); ?>)
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

    <!-- Internal Payroll Sync Card -->
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="fw-bold mb-1 text-info"><i class="fa-solid fa-building-user me-2"></i>Internal Corporate Employee Synchronization</h6>
                    <p class="text-secondary small mb-0">
                        Automatically import active company employees from the HR/Payroll directory into an internal audience list.
                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1"><i class="fa-solid fa-lock me-1"></i>Zero Salary Exposure Guaranteed</span>
                        (Strict read-only projection of <code>u.name</code> and <code>u.email</code> only).
                    </p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#syncPayrollModal">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Sync Payroll Employees
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lists Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-uppercase text-secondary">
                        <th>List Name</th>
                        <th>Type</th>
                        <th>Opt-in Policy</th>
                        <th>Active Subscribers</th>
                        <th>Total Contacts</th>
                        <th>Created By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lists)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-list-check fs-2 mb-2 d-block"></i>
                                No email lists created yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lists as $l): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold">
                                        <a href="index.php?route=email_campaigns/contacts&list_id=<?php echo $l->list_id; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($l->name); ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($l->description)): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($l->description); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($l->is_internal): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa-solid fa-building me-1"></i>Internal Corporate</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">External / Marketing</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php echo $l->optin_mode === 'double' ? 'Double Opt-in (Confirmed)' : 'Single Opt-in'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?php echo number_format($l->active_subscribers); ?></span>
                                </td>
                                <td>
                                    <span class="fw-medium text-muted"><?php echo number_format($l->total_contacts); ?></span>
                                </td>
                                <td>
                                    <span class="small text-secondary"><?php echo htmlspecialchars($l->creator_name ?? 'System'); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="index.php?route=email_campaigns/contacts&list_id=<?php echo $l->list_id; ?>">
                                                    <i class="fa-solid fa-users me-2 text-primary"></i>View Subscribers
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" action="index.php?route=email_campaigns/listDelete" onsubmit="return confirm('Delete this list? Contacts assigned to other lists or global suppression will remain intact.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                                                    <input type="hidden" name="list_id" value="<?php echo $l->list_id; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fa-solid fa-trash me-2"></i>Delete List
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
    </div>
</div>

<!-- Modal: Create List -->
<div class="modal fade" id="createListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/listSave">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create Email Audience List</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">List Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Monthly Product Newsletter" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Purpose and target audience of this list"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Opt-in Compliance Mode</label>
                        <select name="optin_mode" class="form-select">
                            <option value="single">Single Opt-in</option>
                            <option value="double">Double Opt-in (Recommended for public signup)</option>
                        </select>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_internal" id="is_internal_check" value="1">
                        <label class="form-check-label fw-semibold" for="is_internal_check">Mark as Internal Corporate List</label>
                        <div class="form-text small">Used for internal announcements and staff notifications. Can be synced directly from payroll.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i>Create List</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Sync Payroll Employees -->
<div class="modal fade" id="syncPayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="index.php?route=email_campaigns/syncInternal">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-info"><i class="fa-solid fa-building-user me-2"></i>Sync Active Payroll Employees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fa-solid fa-lock me-2"></i><strong>Security & Privacy Safeguard:</strong> This operation strictly queries active users attached to an employee profile and reads only <code>name</code> and <code>email</code>. Absolutely no salary, bank accounts, tax brackets, or payroll calculation data can ever be read or transferred.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Target List <span class="text-danger">*</span></label>
                        <select name="list_id" class="form-select" required>
                            <?php foreach ($lists as $l): ?>
                                <option value="<?php echo $l->list_id; ?>" <?php echo $l->is_internal ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l->name); ?> <?php echo $l->is_internal ? '(Internal List)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="small text-muted mb-0">Employees will be marked with consent type <code>internal_employee</code> and source <code>internal_payroll</code>.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-arrows-rotate me-1"></i>Start Sync</button>
                </div>
            </div>
        </form>
    </div>
</div>

