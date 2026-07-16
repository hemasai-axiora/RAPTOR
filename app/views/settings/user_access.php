<div class="container-fluid px-4 py-4">
    <!-- Back Header -->
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?route=settings/accessControl" class="btn btn-light rounded-circle shadow-sm border-0 me-3 text-secondary" style="width: 40px; height: 40px; line-height: 26px;">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">Configure Access: <?php echo htmlspecialchars($data['user']->name); ?></h1>
            <p class="text-muted mb-0">Change assigned role and set custom permission overrides to bypass role default scopes.</p>
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

    <form action="index.php?route=settings/saveUserAccess/<?php echo $data['user']->user_id; ?>" method="POST">
        <div class="row">
            <!-- Left Panel: User Info & Role Assignment -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="text-center mb-4 pb-3 border-bottom">
                            <div class="avatar bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 60px; height: 60px; font-size: 20px;">
                                <?php 
                                    $words = explode(' ', $data['user']->name);
                                    echo count($words) > 1 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($data['user']->name, 0, 2));
                                ?>
                            </div>
                            <h5 class="fw-bold text-dark mt-3 mb-1"><?php echo htmlspecialchars($data['user']->name); ?></h5>
                            <span class="font-monospace text-muted small"><?php echo htmlspecialchars($data['user']->email); ?></span>
                        </div>

                        <div class="mb-4">
                            <label for="role_id" class="form-label small fw-bold text-muted text-uppercase">System Role</label>
                            <select class="form-select py-2 shadow-none border-gray" id="role_id" name="role_id" required>
                                <?php
                                    // Fetch roles list from DB or via global
                                    $db = Database::getInstance()->getConnection();
                                    $roles = $db->query('SELECT role_id, role_name FROM roles ORDER BY role_name ASC')->fetchAll(PDO::FETCH_OBJ);
                                    foreach ($roles as $role) {
                                        $selected = ($role->role_id == $data['user']->role_id) ? 'selected' : '';
                                        echo '<option value="' . $role->role_id . '" ' . $selected . '>' . ucwords(str_replace('_', ' ', $role->role_name)) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary py-2 w-100 fw-bold border-0 shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Save Changes
                        </button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm bg-dark text-white p-4">
                    <h6 class="fw-bold text-uppercase text-muted-custom mb-3">Override Rules</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-3">
                            <strong class="text-secondary"><i class="fa-solid fa-rotate-left me-1"></i> Inherit:</strong> 
                            <span class="text-light-50">Uses the default scope defined by the user's role.</span>
                        </li>
                        <li class="mb-3">
                            <strong class="text-danger"><i class="fa-solid fa-ban me-1"></i> Revoke:</strong> 
                            <span class="text-light-50">Explicitly blocks access, overriding any role defaults.</span>
                        </li>
                        <li class="mb-0">
                            <strong class="text-success"><i class="fa-solid fa-plus-circle me-1"></i> Grant:</strong> 
                            <span class="text-light-50">Explicitly overrides the role scope (Own, Team, All) for this action.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Panel: Override Matrix -->
            <div class="col-lg-9 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="m-0 font-weight-bold text-dark fw-bold">Permission Overrides</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="d-flex align-items-start">
                            <!-- Module Tabs -->
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

                            <!-- Override Radios -->
                            <div class="tab-content flex-grow-1 p-4" id="v-pills-tabContent">
                                <?php $first = true; foreach ($data['grouped_permissions'] as $module => $perms): ?>
                                    <div class="tab-pane fade show <?php echo $first ? 'active' : ''; ?>" id="tab-<?php echo $module; ?>" role="tabpanel" aria-labelledby="tab-<?php echo $module; ?>-tab">
                                        <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                                            <h5 class="fw-bold text-capitalize text-dark mb-0">Module: <?php echo str_replace('_', ' ', $module); ?></h5>
                                            <span class="badge bg-primary bg-opacity-10 text-primary ms-3 rounded-pill"><?php echo count($perms); ?> Overrides</span>
                                        </div>

                                        <?php foreach ($perms as $p): ?>
                                            <?php 
                                                // Role default configuration
                                                $roleDefaultScope = $data['role_permissions'][(int)$p->permission_id] ?? 'none';
                                                if (isset($data['role_permissions'][(int)$p->permission_id]) && $data['role_permissions'][(int)$p->permission_id] === null) {
                                                    $roleDefaultScope = 'all'; // simple grant
                                                }

                                                // Override configuration
                                                $overrideType = $data['overrides'][(int)$p->permission_id]['type'] ?? 'inherit';
                                                $overrideScope = $data['overrides'][(int)$p->permission_id]['scope'] ?? null;

                                                $activeVal = 'inherit';
                                                if ($overrideType === 'revoke') {
                                                    $activeVal = 'revoke';
                                                } elseif ($overrideType === 'grant') {
                                                    $activeVal = 'grant_' . $overrideScope;
                                                }
                                            ?>
                                            <div class="border-bottom py-3.5 px-2">
                                                <div class="row align-items-center">
                                                    <!-- Permission description -->
                                                    <div class="col-xl-5 mb-3 mb-xl-0">
                                                        <span class="badge bg-secondary font-monospace text-uppercase mb-1 small"><?php echo htmlspecialchars($p->action); ?></span>
                                                        <div class="fw-bold text-dark mb-0.5 text-capitalize"><?php echo str_replace('_', ' ', $p->action) . ' ' . str_replace('_', ' ', $p->module); ?></div>
                                                        <div class="small text-muted mb-1" style="font-size: 11px;"><?php echo htmlspecialchars($p->description ?? ''); ?></div>
                                                        <div class="text-xs text-muted">
                                                            Role default: 
                                                            <span class="badge bg-light text-dark text-capitalize px-1.5 py-0.5">
                                                                <?php echo $roleDefaultScope; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Overrides radios selection -->
                                                    <div class="col-xl-7">
                                                        <div class="btn-group w-100" role="group" aria-label="Permission Overrides selection">
                                                            <!-- Inherit radio -->
                                                            <input type="radio" class="btn-check" name="overrides[<?php echo $p->permission_id; ?>]" 
                                                                   id="ov-<?php echo $p->permission_id; ?>-inherit" value="inherit" 
                                                                   <?php echo $activeVal === 'inherit' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-secondary btn-sm border-gray" for="ov-<?php echo $p->permission_id; ?>-inherit">
                                                                Inherit
                                                            </label>

                                                            <!-- Revoke radio -->
                                                            <input type="radio" class="btn-check" name="overrides[<?php echo $p->permission_id; ?>]" 
                                                                   id="ov-<?php echo $p->permission_id; ?>-revoke" value="revoke" 
                                                                   <?php echo $activeVal === 'revoke' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-danger btn-sm border-gray" for="ov-<?php echo $p->permission_id; ?>-revoke">
                                                                Revoke
                                                            </label>

                                                            <!-- Grant Own radio -->
                                                            <input type="radio" class="btn-check" name="overrides[<?php echo $p->permission_id; ?>]" 
                                                                   id="ov-<?php echo $p->permission_id; ?>-own" value="grant_own" 
                                                                   <?php echo $activeVal === 'grant_own' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-warning btn-sm border-gray" for="ov-<?php echo $p->permission_id; ?>-own">
                                                                Grant Own
                                                            </label>

                                                            <!-- Grant Team radio -->
                                                            <input type="radio" class="btn-check" name="overrides[<?php echo $p->permission_id; ?>]" 
                                                                   id="ov-<?php echo $p->permission_id; ?>-team" value="grant_team" 
                                                                   <?php echo $activeVal === 'grant_team' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-info btn-sm border-gray" for="ov-<?php echo $p->permission_id; ?>-team">
                                                                Grant Team
                                                            </label>

                                                            <!-- Grant All radio -->
                                                            <input type="radio" class="btn-check" name="overrides[<?php echo $p->permission_id; ?>]" 
                                                                   id="ov-<?php echo $p->permission_id; ?>-all" value="grant_all" 
                                                                   <?php echo $activeVal === 'grant_all' ? 'checked' : ''; ?>>
                                                            <label class="btn btn-outline-success btn-sm border-gray" for="ov-<?php echo $p->permission_id; ?>-all">
                                                                Grant All
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
.text-xs {
    font-size: 10px !important;
}
.px-1.5 {
    padding-left: 0.35rem !important;
    padding-right: 0.35rem !important;
}
</style>
