<?php
// Admin Social media Configurations view
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <div class="mb-4">
        <h1 class="h3 text-white mb-0">Social Configurations Panel</h1>
        <p class="text-secondary mb-0">Create platforms, accounts, and assign them to employees.</p>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Platforms & Accounts Creation -->
        <div class="col-lg-5 mb-4">
            <!-- Platform Management -->
            <div class="pulse-card card-glow mb-4">
                <h5 class="text-white mb-3"><i class="fa-solid fa-folder-plus me-2 text-primary"></i>Manage Platforms</h5>
                
                <form action="index.php?route=social/addPlatform" method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" name="name" class="form-control" placeholder="Platform (e.g. Threads)" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="icon" class="form-control" placeholder="Icon Class (fa-...)" value="fa-brands fa-square-share-nodes">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                </form>

                <div class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">
                    <?php foreach ($platforms as $p): ?>
                        <div class="list-group-item bg-transparent border-secondary text-white px-0 py-2 d-flex justify-content-between align-items-center">
                            <span><i class="<?php echo $p->icon; ?> me-2 text-primary"></i><?php echo htmlspecialchars($p->name); ?></span>
                            <form action="index.php?route=social/removePlatform" method="POST" onsubmit="return confirm('Are you sure you want to delete this platform?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="platform_id" value="<?php echo $p->platform_id; ?>">
                                <button type="submit" class="btn btn-sm text-danger bg-transparent border-0"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Create Social Account -->
            <div class="pulse-card">
                <h5 class="text-white mb-3"><i class="fa-solid fa-square-rss me-2 text-primary"></i>Create Social Account</h5>
                <form action="index.php?route=social/addAccount" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="client_id" class="form-label text-secondary small">Assign to Client</label>
                        <select name="client_id" id="client_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c->client_id; ?>"><?php echo htmlspecialchars($c->company_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="platform_id" class="form-label text-secondary small">Platform</label>
                        <select name="platform_id" id="platform_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                            <option value="">Select Platform</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?php echo $p->platform_id; ?>"><?php echo htmlspecialchars($p->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="profile_name" class="form-label text-secondary small">Account Profile Name</label>
                        <input type="text" name="profile_name" id="profile_name" class="form-control" placeholder="e.g. Raptor Marketing Main" required>
                    </div>

                    <div class="mb-3">
                        <label for="profile_url" class="form-label text-secondary small">Profile URL (Optional)</label>
                        <input type="url" name="profile_url" id="profile_url" class="form-control" placeholder="https://instagram.com/raptormarketing">
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Create Social Account</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Assignments Matrix -->
        <div class="col-lg-7">
            <div class="pulse-card card-glow mb-4">
                <h5 class="text-white mb-3"><i class="fa-solid fa-user-check me-2 text-primary"></i>Assign Social Accounts</h5>
                <form action="index.php?route=social/assignAccount" method="POST" class="mb-4 border-bottom pb-4 border-secondary border-opacity-10">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="account_id" class="form-label text-secondary small">Account</label>
                            <select name="account_id" id="account_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Account</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo $acc->account_id; ?>"><?php echo htmlspecialchars($acc->profile_name); ?> (<?php echo htmlspecialchars($acc->platform_name); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="user_id" class="form-label text-secondary small">Assignee Employee</label>
                            <select name="user_id" id="user_id" class="filter-select w-100" required style="padding: 0.6rem 1rem;">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->user_id; ?>"><?php echo htmlspecialchars($emp->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex flex-column justify-content-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_shared" id="is_shared" class="form-check-input" value="1">
                                <label for="is_shared" class="form-check-label text-secondary small">Allow Shared</label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100" style="background: var(--primary); border: none; padding: 0.55rem;">Assign</button>
                        </div>
                    </div>
                </form>

                <h6 class="text-white mb-2">Active Assignments Matrix</h6>
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Social Account</th>
                                <th>Platform</th>
                                <th>Assigned To</th>
                                <th>Shared Access</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Fetch active assignments list
                            $db = Database::getInstance()->getConnection();
                            $stmt = $db->query("SELECT a.*, u.name as employee_name, sa.profile_name, p.name as platform_name, p.icon as platform_icon 
                                                FROM assignments a 
                                                JOIN users u ON a.user_id = u.user_id 
                                                JOIN social_accounts sa ON a.account_id = sa.account_id 
                                                JOIN platforms p ON sa.platform_id = p.platform_id");
                            $assignments = $stmt->fetchAll(PDO::FETCH_OBJ);
                            
                            if (empty($assignments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">No social assignments registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($assignments as $asg): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($asg->profile_name); ?></td>
                                        <td><span class="small"><i class="<?php echo $asg->platform_icon; ?> me-1 text-primary"></i><?php echo htmlspecialchars($asg->platform_name); ?></span></td>
                                        <td><?php echo htmlspecialchars($asg->employee_name); ?></td>
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
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 0.2rem 0.5rem;"><i class="fa-solid fa-user-minus"></i> Unassign</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- List of All Social Accounts -->
            <div class="pulse-card">
                <h5 class="text-white mb-3"><i class="fa-solid fa-list-ul me-2 text-primary"></i>Connected Social Accounts Directory</h5>
                <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Account</th>
                                <th>Client Name</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">No connected accounts registered.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($acc->profile_name); ?></div>
                                            <a href="<?php echo htmlspecialchars($acc->profile_url); ?>" target="_blank" class="small text-primary text-decoration-none"><?php echo htmlspecialchars($acc->profile_url); ?></a>
                                        </td>
                                        <td class="small text-secondary"><?php echo htmlspecialchars($acc->company_name); ?></td>
                                        <td><span class="small"><i class="<?php echo $acc->platform_icon; ?> me-1 text-primary"></i><?php echo htmlspecialchars($acc->platform_name); ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $acc->status === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($acc->status); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <form action="index.php?route=social/archiveAccount" method="POST" onsubmit="return confirm('Are you sure you want to archive/disconnect this account?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="account_id" value="<?php echo $acc->account_id; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning" style="padding: 0.2rem 0.5rem;"><i class="fa-solid fa-box-archive"></i> Disconnect</button>
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
    </div>
</div>
