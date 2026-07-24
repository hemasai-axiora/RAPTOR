<?php
// Social Accounts Management & Credentials View
$isEmployee = !empty($is_employee);

// Compute Summary Metrics for Dashboard
$totalAccountsCount = count($accounts);
$assignedCount = 0;
$uniqueTeam = [];
foreach ($accounts as $acc) {
    if (!empty($acc->assigned_employees)) {
        $assignedCount++;
        $names = array_map('trim', explode(',', $acc->assigned_employees));
        foreach ($names as $n) {
            if ($n) $uniqueTeam[$n] = true;
        }
    }
}
$handlingTeamCount = count($uniqueTeam);
$platformsCount = count($platforms);
?>
<style>
/* Theme-Adaptive Navigation Pills */
.social-nav-pills .nav-link {
    color: #334155 !important;
    background: #f8fafc;
    border: 1px solid #cbd5e1 !important;
    font-weight: 600;
    font-size: 0.88rem;
    white-space: nowrap;
    transition: all 0.2s ease-in-out;
    padding: 0.75rem 0.6rem;
}
.social-nav-pills .nav-link:hover {
    background: #e2e8f0;
    color: #0f172a !important;
}
.social-nav-pills .nav-link.active {
    color: #ffffff !important;
    background: var(--primary, #2563eb) !important;
    border-color: var(--primary, #2563eb) !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Dark Theme Overrides */
body.dark-mode .social-nav-pills .nav-link,
[data-bs-theme="dark"] .social-nav-pills .nav-link {
    color: #cbd5e1 !important;
    background: #1e293b;
    border-color: #334155 !important;
}
body.dark-mode .social-nav-pills .nav-link:hover,
[data-bs-theme="dark"] .social-nav-pills .nav-link:hover {
    background: #334155;
    color: #ffffff !important;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0" style="color: var(--text-color, #1e293b);"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Social Accounts Management</h1>
            <p class="text-secondary mb-0">
                <?php if ($isEmployee): ?>
                    View your assigned social media accounts, handles, login credentials, and manager review remarks.
                <?php else: ?>
                    Store social media account credentials, manage platforms, assign accounts to team members, and monitor account handling.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-3" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-3" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics Dashboard Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="pulse-card d-flex align-middle align-items-center p-3 shadow-sm">
                <div class="rounded-3 text-white me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); width: 54px; height: 54px;">
                    <span style="font-size: 1.6rem;">📱</span>
                </div>
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Total Accounts</span>
                    <h3 class="mb-0 mt-1 fw-bold" style="color: var(--text-color, #1e293b);"><?php echo $totalAccountsCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pulse-card d-flex align-middle align-items-center p-3 shadow-sm">
                <div class="rounded-3 text-white me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669); width: 54px; height: 54px;">
                    <span style="font-size: 1.6rem;">📋</span>
                </div>
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Assigned Accounts</span>
                    <h3 class="mb-0 mt-1 fw-bold" style="color: var(--text-color, #1e293b);"><?php echo $assignedCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pulse-card d-flex align-middle align-items-center p-3 shadow-sm">
                <div class="rounded-3 text-white me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #06b6d4, #0891b2); width: 54px; height: 54px;">
                    <span style="font-size: 1.6rem;">👥</span>
                </div>
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Handling Team Members</span>
                    <h3 class="mb-0 mt-1 fw-bold" style="color: var(--text-color, #1e293b);"><?php echo $handlingTeamCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pulse-card d-flex align-middle align-items-center p-3 shadow-sm">
                <div class="rounded-3 text-white me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 54px; height: 54px;">
                    <span style="font-size: 1.6rem;">🌐</span>
                </div>
                <div>
                    <span class="text-secondary small fw-semibold text-uppercase">Active Platforms</span>
                    <h3 class="mb-0 mt-1 fw-bold" style="color: var(--text-color, #1e293b);"><?php echo $platformsCount; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Side-by-Side 4 Tab Options Navigation Bar -->
    <ul class="nav nav-pills social-nav-pills nav-justified mb-4 gap-2 p-2 rounded-3 border" id="accountsTabs" role="tablist" style="background: var(--card-bg, #ffffff); border-color: var(--border-color, #e2e8f0) !important;">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 w-100 text-nowrap" id="directory-tab" data-bs-toggle="pill" data-bs-target="#tab-directory" type="button" role="tab" aria-controls="tab-directory" aria-selected="true">
                <i class="fa-solid fa-address-book me-2"></i>Accounts Directory &amp; Vault
            </button>
        </li>
        <?php if (!$isEmployee): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 w-100 text-nowrap" id="assign-tab" data-bs-toggle="pill" data-bs-target="#tab-assign" type="button" role="tab" aria-controls="tab-assign" aria-selected="false">
                <i class="fa-solid fa-user-plus me-2"></i>Assign Account to Employee
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 w-100 text-nowrap" id="create-tab" data-bs-toggle="pill" data-bs-target="#tab-create" type="button" role="tab" aria-controls="tab-create" aria-selected="false">
                <i class="fa-solid fa-key me-2"></i>Create &amp; Store Credentials
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 w-100 text-nowrap" id="platforms-tab" data-bs-toggle="pill" data-bs-target="#tab-platforms" type="button" role="tab" aria-controls="tab-platforms" aria-selected="false">
                <i class="fa-solid fa-folder-plus me-2"></i>Manage Platforms
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Tab Content Sections -->
    <div class="tab-content" id="accountsTabsContent">
        
        <!-- OPTION 1: Accounts Directory & Credentials Vault -->
        <div class="tab-pane fade show active" id="tab-directory" role="tabpanel" aria-labelledby="directory-tab">
            <div class="pulse-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h5 class="text-white mb-0">
                        <i class="fa-solid fa-address-book me-2 text-primary"></i>
                        <?php echo $isEmployee ? 'My Assigned Social Accounts & Credentials' : 'Accounts Directory & Credentials Vault'; ?>
                    </h5>
                </div>

                <!-- Search Bar & Filtering Bar -->
                <div class="row g-2 mb-4 p-3 rounded border border-secondary border-opacity-25" style="background: rgba(0, 0, 0, 0.15);">
                    <div class="col-md-5">
                        <label for="socialSearchInput" class="form-label text-secondary small mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i>Search Accounts</label>
                        <input type="text" id="socialSearchInput" class="form-control form-control-sm" placeholder="Search by account profile, handle, platform, or team member...">
                    </div>
                    <?php if (!$isEmployee): ?>
                    <div class="col-md-3">
                        <label for="employeeFilter" class="form-label text-secondary small mb-1"><i class="fa-solid fa-filter me-1"></i>Filter by Team Member</label>
                        <select id="employeeFilter" class="form-select form-select-sm style-select">
                            <option value="">All Team Members</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars(strtolower($emp->name)); ?>"><?php echo htmlspecialchars($emp->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-<?php echo $isEmployee ? '7' : '4'; ?>">
                        <label for="platformFilter" class="form-label text-secondary small mb-1"><i class="fa-solid fa-icons me-1"></i>Filter by Platform</label>
                        <select id="platformFilter" class="form-select form-select-sm style-select">
                            <option value="">All Social Media Platforms</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?php echo htmlspecialchars(strtolower($p->name)); ?>"><?php echo htmlspecialchars($p->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Platform &amp; Profile</th>
                                <th>Username / Handle</th>
                                <th>Credentials</th>
                                <th>Assigned Handler / Team</th>
                                <th>Client Company</th>
                                <th style="min-width: 200px;">Manager Review &amp; Remarks</th>
                                <th>Status</th>
                                <?php if (!$isEmployee): ?>
                                    <th class="text-end">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="accountsTableBody">
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="<?php echo $isEmployee ? 7 : 8; ?>" class="text-center text-secondary py-4">No social accounts registered in directory.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $acc): ?>
                                    <?php 
                                    $platName = htmlspecialchars($acc->platform_name ?? 'N/A');
                                    $profName = htmlspecialchars($acc->profile_name ?? '');
                                    $usrName = htmlspecialchars($acc->username ?? ('@' . strtolower(str_replace(' ', '', $profName))));
                                    $empNames = htmlspecialchars($acc->assigned_employees ?? '');
                                    $clientName = htmlspecialchars($acc->company_name ?? 'Raptor Enterprise');
                                    ?>
                                    <tr class="account-row" 
                                        data-profile="<?php echo strtolower($profName); ?>"
                                        data-username="<?php echo strtolower($usrName); ?>"
                                        data-platform="<?php echo strtolower($platName); ?>"
                                        data-employee="<?php echo strtolower($empNames); ?>"
                                        data-client="<?php echo strtolower($clientName); ?>">
                                        <td>
                                            <div class="fw-bold" style="color: var(--text-color, #0f172a);">
                                                <i class="<?php echo htmlspecialchars($acc->platform_icon ?? 'fa-solid fa-share-nodes'); ?> me-2 text-primary"></i>
                                                <?php echo $profName; ?>
                                            </div>
                                            <div class="small text-secondary ps-4"><?php echo $platName; ?></div>
                                        </td>
                                        <td>
                                            <code class="px-2 py-1 rounded fw-bold" style="background: rgba(37, 99, 235, 0.1); border: 1px solid rgba(37, 99, 235, 0.25); color: #2563eb !important; font-size: 0.85rem;"><?php echo $usrName; ?></code>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info view-creds-btn" 
                                                    data-profile="<?php echo $profName; ?>"
                                                    data-platform="<?php echo $platName; ?>"
                                                    data-username="<?php echo $usrName; ?>"
                                                    data-pass="<?php echo htmlspecialchars($acc->account_password ?? 'RaptorPass@2026'); ?>"
                                                    data-notes="<?php echo htmlspecialchars($acc->account_notes ?? 'No special access notes.'); ?>"
                                                    data-remarks="<?php echo htmlspecialchars($acc->manager_remarks ?? ''); ?>"
                                                    data-handler="<?php echo !empty($empNames) ? $empNames : 'Unassigned'; ?>"
                                                    data-url="<?php echo htmlspecialchars($acc->profile_url ?? '#'); ?>">
                                                <i class="fa-solid fa-key me-1"></i>View Credentials
                                            </button>
                                        </td>
                                        <td>
                                            <?php if (!empty($acc->assigned_employees)): ?>
                                                <span class="badge px-2 py-1 fw-bold" style="background: #2563eb; color: #ffffff !important; font-size: 0.82rem; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);">
                                                    <i class="fa-solid fa-user me-1 text-white"></i><?php echo $empNames; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge px-2 py-1 fw-semibold" style="background: #64748b; color: #ffffff !important; font-size: 0.82rem;">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small fw-semibold" style="color: var(--secondary-color, #475569);"><?php echo $clientName; ?></td>
                                        
                                        <!-- Manager Review Comment Column -->
                                        <td>
                                            <?php if (!empty($acc->manager_remarks)): ?>
                                                <div class="small p-2 rounded border border-primary border-opacity-25 mb-1 fw-medium" style="background: rgba(37, 99, 235, 0.08); color: var(--text-color, #0f172a);">
                                                    <i class="fa-solid fa-comment-dots text-primary me-1"></i><?php echo htmlspecialchars($acc->manager_remarks); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="small text-secondary fst-italic">No review remarks yet.</span>
                                            <?php endif; ?>
                                            
                                            <?php if (!$isEmployee): ?>
                                                <div>
                                                    <button type="button" class="btn btn-sm text-primary p-0 border-0 edit-remarks-btn" 
                                                            data-id="<?php echo $acc->account_id; ?>" 
                                                            data-profile="<?php echo $profName; ?>"
                                                            data-remarks="<?php echo htmlspecialchars($acc->manager_remarks ?? ''); ?>"
                                                            style="font-size: 0.8rem; font-weight: 600;">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i><?php echo !empty($acc->manager_remarks) ? 'Edit Remarks' : 'Add Review Remarks'; ?>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge bg-<?php echo ($acc->status ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($acc->status ?? 'active')); ?>
                                            </span>
                                        </td>
                                        <?php if (!$isEmployee): ?>
                                            <td class="text-end">
                                                <form action="index.php?route=social/archiveAccount" method="POST" onsubmit="return confirm('Are you sure you want to disconnect/archive this account?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="account_id" value="<?php echo $acc->account_id; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" style="padding: 0.25rem 0.6rem;"><i class="fa-solid fa-box-archive"></i> Disconnect</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!$isEmployee): ?>
        <!-- OPTION 2: Assign Social Account to Employee -->
        <div class="tab-pane fade" id="tab-assign" role="tabpanel" aria-labelledby="assign-tab">
            <div class="pulse-card mb-4">
                <h5 class="text-white mb-3"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Assign Social Account to Employee</h5>
                <form action="index.php?route=social/assignAccount" method="POST" class="mb-4 border-bottom pb-4 border-secondary border-opacity-10">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="account_id" class="form-label text-secondary small">Select Account</label>
                            <select name="account_id" id="account_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Account</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo $acc->account_id; ?>"><?php echo htmlspecialchars($acc->profile_name); ?> (<?php echo htmlspecialchars($acc->platform_name ?? 'Platform'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="user_id" class="form-label text-secondary small">Assignee Employee</label>
                            <select name="user_id" id="user_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->user_id; ?>"><?php echo htmlspecialchars($emp->name); ?> (<?php echo htmlspecialchars($emp->role_name ?? 'Employee'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex flex-column justify-content-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_shared" id="is_shared" class="form-check-input" value="1">
                                <label for="is_shared" class="form-check-label text-secondary small">Allow Shared Access</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" style="background: var(--primary); border: none; padding: 0.65rem;">
                                <i class="fa-solid fa-check me-1"></i>Assign Account
                            </button>
                        </div>
                    </div>
                </form>

                <h6 class="text-white mb-3">Active Employee Account Assignments</h6>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Social Account</th>
                                <th>Platform</th>
                                <th>Assigned Employee</th>
                                <th>Shared Access</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $db = Database::getInstance()->getConnection();
                            $stmt = $db->query("SELECT a.*, u.name as employee_name, sa.profile_name, p.name as platform_name, p.icon as platform_icon 
                                                FROM assignments a 
                                                JOIN users u ON a.user_id = u.user_id 
                                                JOIN social_accounts sa ON a.account_id = sa.account_id 
                                                JOIN platforms p ON sa.platform_id = p.platform_id");
                            $assignments = $stmt->fetchAll(PDO::FETCH_OBJ);
                            
                            if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">No active employee assignments. Select an account above to assign.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $asg): ?>
                                    <tr>
                                        <td class="fw-semibold text-white"><?php echo htmlspecialchars($asg->profile_name); ?></td>
                                        <td><span class="small"><i class="<?php echo $asg->platform_icon; ?> me-1 text-primary"></i><?php echo htmlspecialchars($asg->platform_name); ?></span></td>
                                        <td><span class="badge bg-primary bg-opacity-20 text-primary px-2 py-1"><i class="fa-solid fa-user me-1"></i><?php echo htmlspecialchars($asg->employee_name); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $asg->is_shared ? 'bg-success' : 'bg-secondary'; ?> bg-opacity-15 text-<?php echo $asg->is_shared ? 'success' : 'secondary'; ?>">
                                                <?php echo $asg->is_shared ? 'Enabled' : 'Single User Only'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <form action="index.php?route=social/unassignAccount" method="POST" onsubmit="return confirm('Are you sure you want to unassign this account?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="account_id" value="<?php echo $asg->account_id; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $asg->user_id; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 0.25rem 0.6rem;"><i class="fa-solid fa-user-minus"></i> Unassign</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- OPTION 3: Create & Store Credentials -->
        <div class="tab-pane fade" id="tab-create" role="tabpanel" aria-labelledby="create-tab">
            <div class="pulse-card">
                <h5 class="text-white mb-3"><i class="fa-solid fa-key me-2 text-primary"></i>Create Social Account &amp; Store Credentials</h5>
                <form action="index.php?route=social/addAccount" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label text-secondary small">Assign to Client</label>
                            <select name="client_id" id="client_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo $c->client_id; ?>"><?php echo htmlspecialchars($c->company_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="platform_id" class="form-label text-secondary small">Platform</label>
                            <select name="platform_id" id="platform_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Platform</option>
                                <?php foreach ($platforms as $p): ?>
                                    <option value="<?php echo $p->platform_id; ?>"><?php echo htmlspecialchars($p->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="profile_name" class="form-label text-secondary small">Account Profile Name</label>
                        <input type="text" name="profile_name" id="profile_name" class="form-control" placeholder="e.g. Raptor Marketing Main Page" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label text-secondary small">Username / Handle</label>
                            <input type="text" name="username" id="username" class="form-control" placeholder="@raptor_official">
                        </div>
                        <div class="col-md-6">
                            <label for="account_password" class="form-label text-secondary small">Password / Key</label>
                            <input type="password" name="account_password" id="account_password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="account_notes" class="form-label text-secondary small">Access Notes / 2FA Instructions</label>
                        <textarea name="account_notes" id="account_notes" class="form-control" rows="2" placeholder="e.g. 2FA backup codes or manager notes..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="profile_url" class="form-label text-secondary small">Profile URL (Optional)</label>
                        <input type="url" name="profile_url" id="profile_url" class="form-control" placeholder="https://instagram.com/raptor_official">
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; padding: 0.75rem;">
                            <i class="fa-solid fa-lock me-2"></i>Save &amp; Store Credentials
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- OPTION 4: Manage Platforms -->
        <div class="tab-pane fade" id="tab-platforms" role="tabpanel" aria-labelledby="platforms-tab">
            <div class="pulse-card">
                <h5 class="text-white mb-3"><i class="fa-solid fa-folder-plus me-2 text-primary"></i>Manage Platforms</h5>
                
                <form action="index.php?route=social/addPlatform" method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small">Platform Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Platform (e.g. Threads)" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small">FontAwesome Icon Class</label>
                            <input type="text" name="icon" class="form-control" placeholder="Icon Class" value="fa-brands fa-square-share-nodes">
                        </div>
                        <div class="col-md-2 d-flex flex-column justify-content-end">
                            <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; padding: 0.65rem;"><i class="fa-solid fa-plus me-1"></i>Add</button>
                        </div>
                    </div>
                </form>

                <h6 class="text-white mb-3">Active Platforms Directory</h6>
                <div class="list-group list-group-flush" style="max-height: 350px; overflow-y: auto;">
                    <?php foreach ($platforms as $p): ?>
                        <?php $iconClass = (strpos(strtolower($p->name), 'twitter') !== false || strtolower($p->name) === 'x') ? 'fa-brands fa-x-twitter' : htmlspecialchars($p->icon); ?>
                        <div class="list-group-item bg-transparent border-secondary text-white px-3 py-2 d-flex justify-content-between align-items-center mb-1 rounded border">
                            <span><i class="<?php echo $iconClass; ?> me-2 text-primary fs-5"></i><strong class="text-white"><?php echo htmlspecialchars($p->name); ?></strong></span>
                            <form action="index.php?route=social/removePlatform" method="POST" onsubmit="return confirm('Are you sure you want to delete this platform?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="platform_id" value="<?php echo $p->platform_id; ?>">
                                <button type="submit" class="btn btn-sm text-danger bg-transparent border-0"><i class="fa-solid fa-trash-can fs-6"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- View Credentials Modal -->
<div class="modal fade" id="credentialsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-shield-halved me-2 text-primary"></i><span id="cred-modal-title">Account Credentials</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-secondary small">Platform</label>
                    <div class="fw-semibold text-primary" id="cred-modal-platform"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Assigned Handler / Team Member</label>
                    <div class="fw-semibold text-info" id="cred-modal-handler"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Username / Handle</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-secondary bg-opacity-20 text-white border-secondary" id="cred-modal-user" readonly>
                        <button class="btn btn-outline-secondary copy-btn" data-target="cred-modal-user" type="button"><i class="fa-solid fa-copy"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Password / Access Key</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-secondary bg-opacity-20 text-white border-secondary" id="cred-modal-pass" readonly>
                        <button class="btn btn-outline-secondary copy-btn" data-target="cred-modal-pass" type="button"><i class="fa-solid fa-copy"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Access Notes / 2FA Instructions</label>
                    <textarea class="form-control bg-secondary bg-opacity-20 text-white border-secondary" id="cred-modal-notes" rows="2" readonly></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small">Manager Review &amp; Remarks</label>
                    <div class="p-2 rounded border border-primary border-opacity-25 bg-primary bg-opacity-10 text-white" id="cred-modal-remarks"></div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Manager Review & Remarks Modal -->
<div class="modal fade" id="managerRemarksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=social/saveRemarks" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="account_id" id="remarks-account-id" value="">
                
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fa-solid fa-comment-dots me-2 text-primary"></i>Manager Review Remarks</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Account Profile</label>
                        <div class="fw-bold text-primary fs-6" id="remarks-profile-title"></div>
                    </div>
                    <div class="mb-3">
                        <label for="manager_remarks" class="form-label text-secondary small">Manager Review Comment / Performance Remarks</label>
                        <textarea name="manager_remarks" id="remarks-text" class="form-control bg-secondary bg-opacity-20 text-white border-secondary" rows="4" placeholder="Enter manager remarks, account status review, or employee instructions..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;"><i class="fa-solid fa-floppy-disk me-1"></i>Save Remarks</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function () {
    // View Credentials Modal handler
    $('.view-creds-btn').on('click', function () {
        const btn = $(this);
        $('#cred-modal-title').text(btn.data('profile'));
        $('#cred-modal-platform').text(btn.data('platform'));
        $('#cred-modal-handler').text(btn.data('handler') || 'Unassigned');
        $('#cred-modal-user').val(btn.data('username'));
        $('#cred-modal-pass').val(btn.data('pass'));
        $('#cred-modal-notes').val(btn.data('notes'));
        $('#cred-modal-remarks').text(btn.data('remarks') || 'No manager remarks added yet.');
        
        const modal = new bootstrap.Modal(document.getElementById('credentialsModal'));
        modal.show();
    });

    // Edit Manager Remarks Modal handler
    $('.edit-remarks-btn').on('click', function () {
        const btn = $(this);
        $('#remarks-account-id').val(btn.data('id'));
        $('#remarks-profile-title').text(btn.data('profile'));
        $('#remarks-text').val(btn.data('remarks'));
        
        const modal = new bootstrap.Modal(document.getElementById('managerRemarksModal'));
        modal.show();
    });

    // 1-Click Clipboard Copy handler
    $('.copy-btn').on('click', function () {
        const targetId = $(this).data('target');
        const input = document.getElementById(targetId);
        if (input) {
            input.select();
            document.execCommand('copy');
            const originalHtml = $(this).html();
            $(this).html('<i class="fa-solid fa-check text-success"></i>');
            setTimeout(() => { $(this).html(originalHtml); }, 1500);
        }
    });

    // Live Search and Filtering Logic
    function filterAccountsTable() {
        const searchVal = $('#socialSearchInput').val().toLowerCase().trim();
        const empVal = $('#employeeFilter').val() ? $('#employeeFilter').val().toLowerCase().trim() : '';
        const platVal = $('#platformFilter').val() ? $('#platformFilter').val().toLowerCase().trim() : '';

        $('#accountsTableBody tr.account-row').each(function () {
            const profile = $(this).data('profile') || '';
            const username = $(this).data('username') || '';
            const platform = $(this).data('platform') || '';
            const employee = $(this).data('employee') || '';
            const client = $(this).data('client') || '';

            const matchesSearch = !searchVal || 
                profile.indexOf(searchVal) !== -1 || 
                username.indexOf(searchVal) !== -1 || 
                platform.indexOf(searchVal) !== -1 || 
                employee.indexOf(searchVal) !== -1 || 
                client.indexOf(searchVal) !== -1;

            const matchesEmp = !empVal || employee.indexOf(empVal) !== -1;
            const matchesPlat = !platVal || platform.indexOf(platVal) !== -1;

            if (matchesSearch && matchesEmp && matchesPlat) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    $('#socialSearchInput').on('keyup input', filterAccountsTable);
    $('#employeeFilter, #platformFilter').on('change', filterAccountsTable);
});
</script>
