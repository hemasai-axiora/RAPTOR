<?php
// Manager Social Analytics Performance Panel
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <div class="mb-4">
        <h1 class="h3 text-white mb-0">Manager Social Analytics Panel</h1>
        <p class="text-secondary mb-0">Track employee updates, account statuses, and engagement rates.</p>
    </div>

    <!-- Aggregate Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="pulse-card card-glow">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="card-title mb-1">Today's updates</div>
                        <h2 class="text-white mb-0"><?php echo $metrics['today_updates']; ?></h2>
                        <div class="text-secondary small mt-1">Updates logged today</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3 text-primary">
                        <i class="fa-solid fa-cloud-arrow-up fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="pulse-card" style="border-left: 3px solid #e63946;">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="card-title mb-1">Pending Updates</div>
                        <h2 class="text-white mb-0" style="color: #e63946 !important;"><?php echo $metrics['pending_updates']; ?></h2>
                        <div class="text-secondary small mt-1">Updates remaining today</div>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-3 rounded-3 text-danger">
                        <i class="fa-solid fa-triangle-exclamation fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="pulse-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="card-title mb-1">Top Platform</div>
                        <h2 class="text-white mb-0 text-truncate" style="font-size: 1.4rem; max-width: 170px;" title="<?php echo $metrics['top_platform']; ?>"><?php echo $metrics['top_platform']; ?></h2>
                        <div class="text-secondary small mt-1">By average engagement rate</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded-3 text-warning">
                        <i class="fa-solid fa-ranking-star fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="pulse-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="card-title mb-1">Avg Engagement</div>
                        <h2 class="text-white mb-0"><?php echo $metrics['average_engagement_rate']; ?>%</h2>
                        <div class="text-secondary small mt-1">Average ER across posts</div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded-3 text-success">
                        <i class="fa-solid fa-chart-line fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Productivity and History Table Section -->
    <div class="row">
        <!-- Employee Productivity Table -->
        <div class="col-lg-12 mb-4">
            <div class="pulse-card card-glow">
                <h5 class="text-white mb-3"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Employee Productivity & Account Updates</h5>
                <div class="table-responsive">
                    <table class="table table-hover text-white align-middle mb-0">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th class="py-3">Employee</th>
                                <th class="py-3 text-center">Assigned Accounts</th>
                                <th class="py-3 text-center">Updates Logged Today</th>
                                <th class="py-3 text-center">Performance Score</th>
                                <th class="py-3">Last Update Time</th>
                                <th class="py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productivity)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4">No employee productivity records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productivity as $p): ?>
                                    <tr class="border-bottom border-secondary border-opacity-10">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-3 bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; border-radius: 50%; font-size: 0.9rem;">
                                                    <?php echo strtoupper(substr($p->employee_name, 0, 2)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($p->employee_name); ?></div>
                                                    <div class="text-secondary small">Social Media Operations</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center fw-semibold"><?php echo $p->assigned_accounts; ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $p->updates_today > 0 ? 'bg-success bg-opacity-15 text-success' : 'bg-danger bg-opacity-15 text-danger'; ?>">
                                                <?php echo $p->updates_today; ?> completed
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <span class="fw-semibold me-2"><?php echo $p->performance_score; ?>%</span>
                                                <div class="progress" style="width: 80px; height: 6px; background-color: rgba(255,255,255,0.1);">
                                                    <div class="progress-bar <?php echo $p->performance_score >= 80.0 ? 'bg-success' : ($p->performance_score >= 40.0 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $p->performance_score; ?>%;" 
                                                         aria-valuenow="<?php echo $p->performance_score; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-secondary small">
                                            <?php echo $p->last_update_time ? date('Y-m-d h:i:s A', strtotime($p->last_update_time)) : '<span class="text-muted">Never</span>'; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="index.php?route=social/history" class="btn btn-sm btn-outline-light">
                                                <i class="fa-solid fa-eye me-1"></i> View Logs
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Latest Timeline updates -->
        <div class="col-lg-12">
            <div class="pulse-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Latest Updates Timeline</h5>
                    <a href="index.php?route=social/history" class="small text-primary text-decoration-none">View All Timeline History &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover text-white align-middle mb-0">
                        <thead>
                            <tr class="border-bottom border-secondary border-opacity-10 text-secondary" style="font-size: 0.85rem;">
                                <th>Timestamp</th>
                                <th>Platform</th>
                                <th>Account</th>
                                <th>Likes</th>
                                <th>Comments</th>
                                <th>Views</th>
                                <th>ER</th>
                                <th>Log By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-3">No updates yet today.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($history, 0, 10) as $h): ?>
                                    <tr class="border-bottom border-secondary border-opacity-10" style="font-size: 0.9rem;">
                                        <td class="text-secondary small"><?php echo date('h:i:s A', strtotime($h->created_at)); ?></td>
                                        <td>
                                            <span class="small">
                                                <i class="<?php echo $h->platform_icon; ?> me-1 text-primary"></i><?php echo htmlspecialchars($h->platform_name); ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($h->profile_name); ?></td>
                                        <td><?php echo number_format($h->likes); ?></td>
                                        <td><?php echo number_format($h->comments); ?></td>
                                        <td class="text-secondary"><?php echo number_format($h->views); ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-white"><?php echo $h->engagement_rate; ?>%</span></td>
                                        <td><span class="small text-secondary"><?php echo htmlspecialchars($h->updated_by_name); ?></span></td>
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
