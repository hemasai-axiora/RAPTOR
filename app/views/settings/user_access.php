<?php
$csrf = $_SESSION['csrf_token'] ?? '';
?>

<div class="container-fluid px-4 py-4">
    <!-- Back Header -->
    <div class="d-flex align-items-center mb-4">
        <a href="index.php?route=settings/accessControl" class="btn btn-outline-secondary rounded-circle shadow-sm border-0 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: var(--surface-soft); color: var(--text-primary);">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color: var(--text-primary);">Configure Access: <?php echo htmlspecialchars($data['user']->name); ?></h1>
            <p style="color: var(--text-secondary); margin-bottom: 0; font-size: 0.9rem;">Change assigned role and set custom permission overrides to bypass role default scopes.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['settings_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background: rgba(16, 185, 129, 0.15); color: #059669;">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php echo $_SESSION['settings_success']; unset($_SESSION['settings_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['settings_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background: rgba(239, 68, 68, 0.15); color: #dc2626;">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?php echo $_SESSION['settings_error']; unset($_SESSION['settings_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="index.php?route=settings/saveUserAccess/<?php echo $data['user']->user_id; ?>" method="POST" id="user-access-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        
        <div class="row g-4">
            <!-- Left Column: User Info Card & Helper Legend -->
            <div class="col-lg-4 col-xl-3">
                <div class="pulse-card mb-4" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; box-shadow: var(--shadow-soft);">
                    <div class="text-center pb-3 border-bottom mb-4" style="border-bottom-color: var(--border-color) !important;">
                        <div class="avatar mx-auto d-flex align-items-center justify-content-center rounded-circle fw-bold" 
                             style="width: 64px; height: 64px; font-size: 22px; background: var(--primary-soft); color: var(--primary); border: 1px solid var(--border-color);">
                            <?php 
                                $words = explode(' ', $data['user']->name);
                                echo count($words) > 1 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($data['user']->name, 0, 2));
                            ?>
                        </div>
                        <h5 class="fw-bold mt-3 mb-1" style="color: var(--text-primary);"><?php echo htmlspecialchars($data['user']->name); ?></h5>
                        <span class="font-monospace text-muted small" style="word-break: break-all;"><?php echo htmlspecialchars($data['user']->email); ?></span>
                    </div>

                    <div class="mb-4">
                        <label for="role_id" class="form-label" style="display:block; font-size:0.75rem; font-weight:700; color: var(--text-secondary); text-transform:uppercase; letter-spacing:0.4px; margin-bottom:0.4rem;">System Role</label>
                        <select class="form-select" id="role_id" name="role_id" required style="border-radius: 8px; border-color: var(--border-strong);">
                            <?php
                                $db = Database::getInstance()->getConnection();
                                $roles = $db->query('SELECT role_id, role_name FROM roles ORDER BY role_name ASC')->fetchAll(PDO::FETCH_OBJ);
                                foreach ($roles as $role) {
                                    $selected = ($role->role_id == $data['user']->role_id) ? 'selected' : '';
                                    echo '<option value="' . $role->role_id . '" ' . $selected . '>' . ucwords(str_replace('_', ' ', $role->role_name)) . '</option>';
                                }
                            ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary py-2 w-100 fw-bold border-0 shadow-sm" style="background: var(--primary); border-radius: 8px;">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Access Rules
                    </button>
                </div>

                <!-- Color-Coded Helper Legend -->
                <div class="pulse-card" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.5rem; box-shadow: var(--shadow-soft);">
                    <h6 class="fw-bold text-uppercase mb-3" style="color: var(--text-secondary); font-size: 0.72rem; letter-spacing: 0.5px;">Override Guidelines</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-3 d-flex align-items-start gap-2">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:0.68rem; font-weight:700; width:72px; text-align:center;">INHERIT</span>
                            <span style="color: var(--text-secondary); font-size: 0.82rem; line-height: 1.25;">Uses the default permissions defined by user's role.</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-2">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:0.68rem; font-weight:700; width:72px; text-align:center;">REVOKE</span>
                            <span style="color: var(--text-secondary); font-size: 0.82rem; line-height: 1.25;">Blocks access explicitly, overriding role defaults.</span>
                        </li>
                        <li class="mb-0 d-flex align-items-start gap-2">
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.68rem; font-weight:700; width:72px; text-align:center;">GRANT</span>
                            <span style="color: var(--text-secondary); font-size: 0.82rem; line-height: 1.25;">Explicitly grants access with a custom scope range.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Column: Simpler Permission List with Search & Dropdowns -->
            <div class="col-lg-8 col-xl-9">
                <div class="pulse-card" style="background: var(--panel-dark); border: 1px solid var(--border-color); border-radius: 14px; padding: 1.75rem; box-shadow: var(--shadow-soft);">
                    
                    <!-- Search Header Panel -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-bottom pb-4 mb-4" style="border-bottom-color: var(--border-color) !important;">
                        <div class="flex-grow-1" style="max-width: 480px;">
                            <div class="input-group">
                                <span class="input-group-text" style="background: var(--surface-soft); border-color: var(--border-strong); color: var(--text-secondary);">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input type="text" id="permission-search" class="form-control" placeholder="Search permissions, modules, or actions..." style="border-color: var(--border-strong); border-radius: 0 8px 8px 0; background: var(--surface-soft); color: var(--text-primary);">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reset-all" style="border-color: var(--border-strong); color: var(--text-secondary); border-radius: 8px; font-weight: 600;">
                                <i class="fa-solid fa-undo me-1"></i> Reset overrides
                            </button>
                        </div>
                    </div>

                    <!-- Permission Modules list -->
                    <div id="permissions-container">
                        <?php foreach ($data['grouped_permissions'] as $module => $perms): ?>
                            <div class="module-group-card mb-4" data-module="<?php echo htmlspecialchars(strtolower($module)); ?>" style="border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--surface-soft);">
                                <div class="module-header px-3 py-2.5 d-flex align-items-center justify-content-between" style="background: var(--primary-soft); border-bottom: 1px solid var(--border-color);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-cubes text-primary"></i>
                                        <span class="fw-bold text-capitalize text-primary" style="font-size:0.92rem;"><?php echo str_replace('_', ' ', $module); ?> Module</span>
                                    </div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary small rounded-pill px-2.5" style="font-weight: 700;"><?php echo count($perms); ?> Action<?php echo count($perms) !== 1 ? 's' : ''; ?></span>
                                </div>
                                
                                <div class="module-items">
                                    <?php foreach ($perms as $i => $p): 
                                        $roleDefaultScope = $data['role_permissions'][(int)$p->permission_id] ?? 'none';
                                        if (isset($data['role_permissions'][(int)$p->permission_id]) && $data['role_permissions'][(int)$p->permission_id] === null) {
                                            $roleDefaultScope = 'all'; 
                                        }

                                        $overrideType = $data['overrides'][(int)$p->permission_id]['type'] ?? 'inherit';
                                        $overrideScope = $data['overrides'][(int)$p->permission_id]['scope'] ?? null;

                                        $activeVal = 'inherit';
                                        if ($overrideType === 'revoke') {
                                            $activeVal = 'revoke';
                                        } elseif ($overrideType === 'grant') {
                                            $activeVal = 'grant_' . $overrideScope;
                                        }
                                    ?>
                                        <div class="permission-item-row p-3 border-bottom" 
                                             data-action="<?php echo htmlspecialchars(strtolower($p->action)); ?>" 
                                             data-desc="<?php echo htmlspecialchars(strtolower($p->description ?? '')); ?>"
                                             style="border-bottom-color: var(--border-color) !important; transition: background 0.1s; background: var(--panel-dark);">
                                            
                                            <div class="row align-items-center">
                                                <div class="col-md-7 mb-2 mb-md-0">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size:0.72rem; font-weight:700;"><?php echo htmlspecialchars($p->action); ?></span>
                                                        <strong style="color: var(--text-primary); font-size: 0.88rem;"><?php echo ucwords(str_replace('_', ' ', $p->action)); ?></strong>
                                                    </div>
                                                    <div style="color: var(--text-secondary); font-size: 0.8rem; line-height: 1.35; margin-bottom: 0.35rem;"><?php echo htmlspecialchars($p->description ?? ''); ?></div>
                                                    <div class="small" style="font-size: 0.76rem; color: var(--text-muted);">
                                                        Role Default scope: <span class="badge bg-light text-dark text-capitalize px-1.5 py-0.5 border" style="font-weight:600;"><?php echo $roleDefaultScope; ?></span>
                                                    </div>
                                                </div>

                                                <div class="col-md-5">
                                                    <select name="overrides[<?php echo $p->permission_id; ?>]" 
                                                            class="form-select override-select shadow-none" 
                                                            data-default="<?php echo $roleDefaultScope; ?>"
                                                            style="font-size:0.85rem; font-weight:600; border-radius:8px; padding-top:0.45rem; padding-bottom:0.45rem;">
                                                        <option value="inherit" <?php echo $activeVal === 'inherit' ? 'selected' : ''; ?>>Use Role Default (Inherit)</option>
                                                        <option value="revoke" <?php echo $activeVal === 'revoke' ? 'selected' : ''; ?>>Block Access (Revoke)</option>
                                                        <option value="grant_own" <?php echo $activeVal === 'grant_own' ? 'selected' : ''; ?>>Access: Own Records Only</option>
                                                        <option value="grant_team" <?php echo $activeVal === 'grant_team' ? 'selected' : ''; ?>>Access: Entire Team</option>
                                                        <option value="grant_all" <?php echo $activeVal === 'grant_all' ? 'selected' : ''; ?>>Access: Entire Company</option>
                                                    </select>
                                                    <div class="override-help-text text-secondary mt-1 small font-italic" style="font-size: 0.76rem;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="no-results-msg" class="text-center py-5 d-none">
                        <i class="fa-solid fa-folder-open fa-2x mb-3 text-secondary"></i>
                        <p style="color: var(--text-secondary); margin-bottom: 0;">No matching permissions found.</p>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<!-- Custom CSS Variables for borders and state styles -->
<style>
/* Base dynamic select styles based on active selection value */
.override-select {
    transition: all 0.15s ease-in-out;
}
.select-state-inherit {
    background-color: var(--surface-soft) !important;
    border-color: var(--border-strong) !important;
    color: var(--text-primary) !important;
}
.select-state-revoke {
    background-color: #FEF2F2 !important;
    border-color: #FCA5A5 !important;
    color: #991B1B !important;
}
.select-state-grant_own {
    background-color: #FFFBEB !important;
    border-color: #FCD34D !important;
    color: #92400E !important;
}
.select-state-grant_team {
    background-color: #EFF6FF !important;
    border-color: #BFDBFE !important;
    color: #1E40AF !important;
}
.select-state-grant_all {
    background-color: #ECFDF5 !important;
    border-color: #A7F3D0 !important;
    color: #065F46 !important;
}

/* Dark mode compatibility adjustments */
html[data-theme="dark"] .select-state-revoke {
    background-color: rgba(239, 68, 68, 0.1) !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
    color: #EF4444 !important;
}
html[data-theme="dark"] .select-state-grant_own {
    background-color: rgba(245, 158, 11, 0.1) !important;
    border-color: rgba(245, 158, 11, 0.4) !important;
    color: #F59E0B !important;
}
html[data-theme="dark"] .select-state-grant_team {
    background-color: rgba(59, 130, 246, 0.1) !important;
    border-color: rgba(59, 130, 246, 0.4) !important;
    color: #3B82F6 !important;
}
html[data-theme="dark"] .select-state-grant_all {
    background-color: rgba(16, 185, 129, 0.1) !important;
    border-color: rgba(16, 185, 129, 0.4) !important;
    color: #10B981 !important;
}
</style>

<!-- Lightweight Javascript search and styling controller -->
<script>
$(function () {
    // 1. Dynamic select color state updates
    function updateSelectState($el) {
        const val = $el.val();
        $el.removeClass('select-state-inherit select-state-revoke select-state-grant_own select-state-grant_team select-state-grant_all');
        $el.addClass('select-state-' + val);

        // Update explanation help text dynamically
        const defaultScope = $el.data('default') || 'none';
        let helpText = '';
        switch(val) {
            case 'inherit':
                helpText = '✨ Inherited: Uses default role scope (' + defaultScope.toUpperCase() + ')';
                break;
            case 'revoke':
                helpText = '🚫 Blocked: Access is explicitly revoked.';
                break;
            case 'grant_own':
                helpText = '👤 Restricted: Own records access only.';
                break;
            case 'grant_team':
                helpText = '👥 Team Level: Access to team records.';
                break;
            case 'grant_all':
                helpText = '🌐 Full Access: Access to all records.';
                break;
        }
        $el.siblings('.override-help-text').text(helpText);
    }

    $('.override-select').each(function () {
        updateSelectState($(this));
    }).on('change', function () {
        updateSelectState($(this));
    });

    // 2. Client-side instant permissions filter search
    $('#permission-search').on('input', function () {
        const q = $(this).val().toLowerCase().trim();
        let visibleModules = 0;

        $('.module-group-card').each(function () {
            const $modCard = $(this);
            const modName = $modCard.data('module');
            let matchedInMod = 0;

            $modCard.find('.permission-item-row').each(function () {
                const $row = $(this);
                const action = $row.data('action');
                const desc = $row.data('desc');

                if (q === '' || modName.includes(q) || action.includes(q) || desc.includes(q)) {
                    $row.removeClass('d-none');
                    matchedInMod++;
                } else {
                    $row.addClass('d-none');
                }
            });

            if (matchedInMod > 0) {
                $modCard.removeClass('d-none');
                visibleModules++;
            } else {
                $modCard.addClass('d-none');
            }
        });

        if (visibleModules === 0) {
            $('#no-results-msg').removeClass('d-none');
        } else {
            $('#no-results-msg').addClass('d-none');
        }
    });

    // 3. Reset all overrides to Inherit
    $('#btn-reset-all').on('click', function () {
        if (confirm('Are you sure you want to reset all overrides for this user? All rules will inherit the default role scopes.')) {
            $('.override-select').val('inherit').trigger('change');
        }
    });
});
</script>
