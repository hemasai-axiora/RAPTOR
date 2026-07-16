<div class="container-fluid px-4 py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Roles & Permissions Management</h1>
            <p class="text-muted mb-0">Define system-wide roles and manage granular permission scopes.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['roles_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo $_SESSION['roles_success']; unset($_SESSION['roles_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['roles_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo $_SESSION['roles_error']; unset($_SESSION['roles_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Roles List Table (Left 8 Columns) -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                    <h5 class="m-0 font-weight-bold text-dark fw-bold">System Roles</h5>
                    <span class="badge bg-secondary rounded-pill"><?php echo count($data['roles']); ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 250px;">Role Key / Label</th>
                                    <th class="py-3">Description</th>
                                    <th class="py-3" style="width: 120px;">Type</th>
                                    <th class="pe-4 py-3 text-end" style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['roles'] as $role): ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-role me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 38px; height: 38px;">
                                                    <i class="fa-solid fa-shield-halved"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo ucwords(str_replace('_', ' ', $role->role_name)); ?></div>
                                                    <small class="text-muted font-monospace"><?php echo htmlspecialchars($role->role_name); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="text-secondary small"><?php echo htmlspecialchars($role->description ?? 'No description provided.'); ?></span>
                                        </td>
                                        <td class="py-3">
                                            <?php if ($role->is_system): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border-0">System</span>
                                            <?php else: ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border-0">Custom</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <a href="index.php?route=roles/edit/<?php echo $role->role_id; ?>" class="btn btn-sm btn-outline-primary border-0 me-2" title="Configure Matrix">
                                                <i class="fa-solid fa-sliders me-1"></i> Configure
                                            </a>
                                            <?php if (!$role->is_system): ?>
                                                <form action="index.php?route=roles/delete/<?php echo $role->role_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this custom role? This will unlink permissions.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Custom Role (Right 4 Columns) -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm border-top border-primary border-3">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Create Custom Role</h5>
                    <form action="index.php?route=roles/add" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <div class="mb-3">
                            <label for="role_name" class="form-label small fw-bold text-muted text-uppercase">Role Label</label>
                            <input type="text" class="form-control py-2 shadow-none border-gray" id="role_name" name="role_name" placeholder="e.g. Sales Specialist" required>
                            <div class="form-text small text-muted">Spaces are converted to underscores; letters lowercase.</div>
                        </div>
                        <div class="mb-4">
                            <label for="description" class="form-label small fw-bold text-muted text-uppercase">Description</label>
                            <textarea class="form-control shadow-none border-gray" id="description" name="description" rows="4" placeholder="Briefly describe the function and limits of this role..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary py-2 w-100 fw-bold border-0 shadow-sm">
                            <i class="fa-solid fa-circle-plus me-1"></i> Save Custom Role
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
