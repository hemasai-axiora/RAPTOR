<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">User Access Management</h1>
            <p class="text-muted mb-0">Assign roles and configure granular per-user permission overrides.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['settings_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo $_SESSION['settings_success']; unset($_SESSION['settings_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['settings_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo $_SESSION['settings_error']; unset($_SESSION['settings_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- User list table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
            <h5 class="m-0 font-weight-bold text-dark fw-bold">Active Employee Accounts</h5>
            <span class="badge bg-primary rounded-pill"><?php echo count($data['users']); ?> Accounts</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3" style="width: 250px;">Employee</th>
                            <th class="py-3">Email Address</th>
                            <th class="py-3">Assigned Role</th>
                            <th class="py-3" style="width: 120px;">Status</th>
                            <th class="pe-4 py-3 text-end" style="width: 180px;">Access Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['users'] as $user): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-light text-secondary d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 38px; height: 38px; font-size: 13px;">
                                            <?php 
                                                $words = explode(' ', $user->name);
                                                echo count($words) > 1 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($user->name, 0, 2));
                                            ?>
                                        </div>
                                        <div class="ms-3">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($user->name); ?></div>
                                            <small class="text-muted font-monospace small"><?php echo htmlspecialchars($user->employee_code ?? 'EMP-N/A'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="text-secondary small"><?php echo htmlspecialchars($user->email); ?></span>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border-0 text-capitalize">
                                        <?php echo ucwords(str_replace('_', ' ', $user->role_name)); ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <?php if (($user->status ?? 'active') === 'active'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border-0">Active</span>
                                    <?php elseif ($user->status === 'suspended'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border-0">Suspended</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border-0">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <a href="index.php?route=settings/userAccess/<?php echo $user->user_id; ?>" class="btn btn-sm btn-outline-primary border-0" title="Manage Overrides">
                                        <i class="fa-solid fa-user-lock me-1"></i> Edit Permissions
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
