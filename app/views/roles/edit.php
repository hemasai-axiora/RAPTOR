<div class="container-fluid px-4 py-4">
    <!-- Back Header -->
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?route=roles/index" class="btn btn-light rounded-circle shadow-sm border-0 me-3 text-secondary" style="width: 40px; height: 40px; line-height: 26px;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Configure Role: <?php echo ucwords(str_replace('_', ' ', $data['role']->role_name)); ?></h1>
            <p class="text-muted mb-0">Set granular permission levels (Own, Team, All) for each system module.</p>
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

    <form action="index.php?route=roles/edit/<?php echo $data['role']->role_id; ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <div class="row">
            <!-- Left Panel: Role Meta Data -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="avatar bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width: 60px; height: 60px; font-size: 24px;">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <h5 class="fw-bold text-dark mt-3 mb-1"><?php echo ucwords(str_replace('_', ' ', $data['role']->role_name)); ?></h5>
                            <span class="font-monospace text-muted small"><?php echo htmlspecialchars($data['role']->role_name); ?></span>
                        </div>

                        <div class="mb-3">
                            <label for="role_name" class="form-label small fw-bold text-muted text-uppercase">Role Label</label>
                            <input type="text" class="form-control py-2 shadow-none border-gray bg-light" id="role_name" name="role_name" 
                                   value="<?php echo ucwords(str_replace('_', ' ', $data['role']->role_name)); ?>" 
                                   <?php echo $data['role']->is_system ? 'readonly' : ''; ?> required>
                            <?php if ($data['role']->is_system): ?>
                                <div class="form-text small text-danger"><i class="fa-solid fa-lock me-1"></i> System roles cannot be renamed.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label small fw-bold text-muted text-uppercase">Description</label>
                            <textarea class="form-control shadow-none border-gray" id="description" name="description" rows="4" required><?php echo htmlspecialchars($data['role']->description); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary py-2 w-100 fw-bold border-0 shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Save Changes
                        </button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm bg-dark text-white p-4">
                    <h6 class="fw-bold text-uppercase text-muted-custom mb-3">Understanding Scopes</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-3">
                            <strong class="text-danger"><i class="fa-solid fa-ban me-1"></i> None:</strong> 
                            <span class="text-light-50">No access whatsoever to the action.</span>
                        </li>
                        <li class="mb-3">
                            <strong class="text-warning"><i class="fa-solid fa-user me-1"></i> Own:</strong> 
                            <span class="text-light-50">Can act only on records belonging to or created by self.</span>
                        </li>
                        <li class="mb-3">
                            <strong class="text-info"><i class="fa-solid fa-users me-1"></i> Team:</strong> 
                            <span class="text-light-50">Can act on records belonging to their entire organizational team hierarchy.</span>
                        </li>
                        <li class="mb-0">
                            <strong class="text-success"><i class="fa-solid fa-globe me-1"></i> All:</strong> 
                            <span class="text-light-50">Unrestricted organizational visibility across all branches and users.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Panel: Permission Scopes Matrix -->
            <div class="col-lg-9 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="m-0 font-weight-bold text-dark fw-bold">Permissions Matrix</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="d-flex align-items-start">
                            <!-- Module Tabs (Left Column) -->
                            <div class="nav flex-column nav-pills border-end p-3 align-self-stretch" id="v-pills-tab" role="tablist" aria-orientation="vertical" style="width: 220px; min-height: 480px;">
                                <?php $first = true; foreach ($data['grouped_permissions'] as $module => $perms): ?>
                                    <button class="nav-link text-start py-2.5 px-3 mb-2 border-0 rounded-3 fw-bold small text-capitalize <?php echo $first ? 'active' : ''; ?>" 
                                            id="tab-<?php echo $module; ?>-tab" data-bs-toggle="pill" 
                                            data-bs-target="#tab-<?php echo $module; ?>" type="button" role="tab" 
                                            aria-controls="tab-<?php echo $module; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                        <i class="fa-solid fa-folder me-2"></i> <?php echo str_replace('_', ' ', $module); ?>
                                    </button>
                                <?php $first = false; endforeach; ?>
                            </div>

                            <!-- Permissions List (Right Column) -->
                            <div class="tab-content flex-grow-1 p-4" id="v-pills-tabContent">
                                <?php $first = true; foreach ($data['grouped_permissions'] as $module => $perms): ?>
                                    <div class="tab-pane fade show <?php echo $first ? 'active' : ''; ?>" id="tab-<?php echo $module; ?>" role="tabpanel" aria-labelledby="tab-<?php echo $module; ?>-tab">
                                        <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                                            <h5 class="fw-bold text-capitalize text-dark mb-0">Module: <?php echo str_replace('_', ' ', $module); ?></h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary ms-3 rounded-pill"><?php echo count($perms); ?> Actions</span>
                                        </div>

                                        <?php foreach ($perms as $p): ?>
                                            <?php 
                                                $activeScope = $data['role_permissions'][(int)$p->permission_id] ?? 'none';
                                                // If database value is NULL/empty scope (like dashboard.view which doesn't use scopes), treat as 'all' or 'none' based on existence
                                                if (isset($data['role_permissions'][(int)$p->permission_id]) && $data['role_permissions'][(int)$p->permission_id] === null) {
                                                    $activeScope = 'all'; // simple grant
                                                }
                                            ?>
                                            <div class="border-bottom py-3.5 px-2">
                                                <div class="row align-items-center">
                                                    <!-- Permission description -->
                                                    <div class="col-md-5 mb-3 mb-md-0">
                                                        <span class="badge bg-secondary font-monospace text-uppercase mb-1 small"><?php echo htmlspecialchars($p->action); ?></span>
                                                        <div class="fw-bold text-dark mb-0.5 text-capitalize"><?php echo str_replace('_', ' ', $p->action) . ' ' . str_replace('_', ' ', $p->module); ?></div>
                                                        <small class="text-muted text-secondary" style="font-size: 11px;"><?php echo htmlspecialchars($p->description ?? ''); ?></small>
                                                    </div>
                                                    
                                                    <!-- Scopes radios selection -->
                                                    <div class="col-md-7">
                                                        <div class="btn-group w-100" role="group" aria-label="Permission Scopes selection">
                                                            <!-- None radio -->
                                                            <input type="radio" class="btn-check" name="permission_scopes[<?php echo $p->permission_id; ?>]" 
                                                                   id="scope-<?php echo $p->permission_id; ?>-none" value="none" 
                                                                   <?php echo $activeScope === 'none' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-danger btn-sm border-gray" for="scope-<?php echo $p->permission_id; ?>-none">
                                                                <i class="fa-solid fa-ban me-1 d-none d-lg-inline"></i> None
                                                            </label>

                                                            <!-- Own radio -->
                                                            <input type="radio" class="btn-check" name="permission_scopes[<?php echo $p->permission_id; ?>]" 
                                                                   id="scope-<?php echo $p->permission_id; ?>-own" value="own" 
                                                                   <?php echo $activeScope === 'own' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-warning btn-sm border-gray" for="scope-<?php echo $p->permission_id; ?>-own">
                                                                <i class="fa-solid fa-user me-1 d-none d-lg-inline"></i> Own
                                                            </label>

                                                            <!-- Team radio -->
                                                            <input type="radio" class="btn-check" name="permission_scopes[<?php echo $p->permission_id; ?>]" 
                                                                   id="scope-<?php echo $p->permission_id; ?>-team" value="team" 
                                                                   <?php echo $activeScope === 'team' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-info btn-sm border-gray" for="scope-<?php echo $p->permission_id; ?>-team">
                                                                <i class="fa-solid fa-users me-1 d-none d-lg-inline"></i> Team
                                                            </label>

                                                            <!-- All radio -->
                                                            <input type="radio" class="btn-check" name="permission_scopes[<?php echo $p->permission_id; ?>]" 
                                                                   id="scope-<?php echo $p->permission_id; ?>-all" value="all" 
                                                                   <?php echo $activeScope === 'all' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-success btn-sm border-gray" for="scope-<?php echo $p->permission_id; ?>-all">
                                                                <i class="fa-solid fa-globe me-1 d-none d-lg-inline"></i> All
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php $first = false; endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.py-3.5 {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.nav-pills .nav-link.active {
    background-color: var(--bs-primary) !important;
    color: #fff !important;
}
.nav-pills .nav-link {
    color: var(--bs-gray-700);
}
.btn-group .btn-outline-danger:hover, .btn-check:checked + .btn-outline-danger {
    background-color: #dc3545 !important;
    color: #fff !important;
    border-color: #dc3545 !important;
}
.btn-group .btn-outline-warning:hover, .btn-check:checked + .btn-outline-warning {
    background-color: #ffc107 !important;
    color: #000 !important;
    border-color: #ffc107 !important;
}
.btn-group .btn-outline-info:hover, .btn-check:checked + .btn-outline-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
    border-color: #0dcaf0 !important;
}
.btn-group .btn-outline-success:hover, .btn-check:checked + .btn-outline-success {
    background-color: #198754 !important;
    color: #fff !important;
    border-color: #198754 !important;
}
.border-gray {
    border-color: #dee2e6 !important;
}
.text-muted-custom {
    color: #adb5bd !important;
}
.text-light-50 {
    color: rgba(255,255,255,0.6) !important;
}
</style>
