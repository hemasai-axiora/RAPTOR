<?php
$role = $_SESSION['user_role'];
$stats = $data['stats'] ?? [];
$personal = $data['personal'] ?? [];
?>

<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0"><i class="fa-solid fa-gauge text-primary me-2"></i>HRMS Dashboard</h4>
        <span class="badge bg-primary px-3 py-2 text-uppercase"><?php echo htmlspecialchars($role); ?> VIEW</span>
    </div>

    <!-- =======================================================================
         ADMIN & HR DASHBOARD VIEW
         ======================================================================= -->
    <?php if ($role === 'admin' || $role === 'hr'): ?>
        <!-- Statistics Counter Grid -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(13, 110, 253, 0.12); border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.25) !important;">
                    <div class="text-primary fs-2 mb-2"><i class="fa-solid fa-users"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Active Employees</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['active_employees'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(220, 53, 69, 0.12); border-radius: 16px; border: 1px solid rgba(220, 53, 69, 0.25) !important;">
                    <div class="text-danger fs-2 mb-2"><i class="fa-solid fa-plane-departure"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Pending Leaves</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['pending_leaves'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(255, 193, 7, 0.12); border-radius: 16px; border: 1px solid rgba(255, 193, 7, 0.25) !important;">
                    <div class="text-warning fs-2 mb-2"><i class="fa-solid fa-user-clock"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Pending Attendance</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['pending_attendance'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(25, 135, 84, 0.12); border-radius: 16px; border: 1px solid rgba(25, 135, 84, 0.25) !important;">
                    <div class="text-success fs-2 mb-2"><i class="fa-solid fa-money-check-dollar"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Payroll Processed</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['payroll_runs'] ?? 0; ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Side Column: Birthdays & Anniversaries -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-cake-candles text-primary me-2"></i>Upcoming Birthdays (Next 30 Days)</h5>
                    <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto;">
                        <?php if (empty($stats['birthdays'])): ?>
                            <div class="text-secondary small text-center py-3">No birthdays coming up soon.</div>
                        <?php else: ?>
                            <?php foreach ($stats['birthdays'] as $b): ?>
                                <div class="d-flex align-items-center p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.03);">
                                    <div class="avatar bg-primary bg-opacity-20 text-primary rounded-circle fw-bold d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                                        <i class="fa-solid fa-birthday-cake"></i>
                                    </div>
                                    <div>
                                        <h6 class="text-white fw-bold mb-0 small"><?php echo htmlspecialchars($b->name); ?></h6>
                                        <small class="text-secondary"><?php echo htmlspecialchars($b->job_title); ?></small>
                                    </div>
                                    <span class="ms-auto badge bg-primary bg-opacity-10 text-primary border border-primary-subtle"><?php echo date('M d', strtotime($b->date_of_birth)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-award text-success me-2"></i>Work Anniversaries</h5>
                    <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto;">
                        <?php if (empty($stats['anniversaries'])): ?>
                            <div class="text-secondary small text-center py-3">No work anniversaries this month.</div>
                        <?php else: ?>
                            <?php foreach ($stats['anniversaries'] as $a): ?>
                                <div class="d-flex align-items-center p-2 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.03);">
                                    <div class="avatar bg-success bg-opacity-20 text-success rounded-circle fw-bold d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                                        <i class="fa-solid fa-gift"></i>
                                    </div>
                                    <div>
                                        <h6 class="text-white fw-bold mb-0 small"><?php echo htmlspecialchars($a->name); ?></h6>
                                        <small class="text-secondary"><?php echo htmlspecialchars($a->job_title); ?> • <?php echo $a->years; ?> Years</small>
                                    </div>
                                    <span class="ms-auto badge bg-success bg-opacity-10 text-success border border-success-subtle"><?php echo date('M d', strtotime($a->date_of_joining)); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side Column: Chart/Analytics Visualization -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4 mb-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Department Distribution</h5>
                    <div id="dept-chart" style="min-height: 250px;"></div>
                </div>

                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-umbrella-beach text-warning me-2"></i>Organizational Holidays</h5>
                    <div class="row">
                        <?php foreach ($data['holidays'] as $h): ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.03);">
                                    <span class="text-white small fw-bold"><?php echo htmlspecialchars($h->holiday_name); ?></span>
                                    <span class="text-secondary small"><?php echo date('M d', strtotime($h->holiday_date)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var options = {
                chart: {
                    type: 'donut',
                    height: 250
                },
                series: [44, 55, 13, 33],
                labels: ['Sales', 'Marketing', 'Finance', 'HR'],
                theme: {
                    mode: 'dark'
                },
                colors: ['#0d6efd', '#25c2e6', '#dc3545', '#198754']
            };
            var chart = new ApexCharts(document.querySelector("#dept-chart"), options);
            chart.render();
        });
        </script>

    <!-- =======================================================================
         MANAGER DASHBOARD VIEW
         ======================================================================= -->
    <?php elseif ($role === 'manager' || $role === 'team_leader'): ?>
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(13, 110, 253, 0.12); border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.25) !important;">
                    <div class="text-primary fs-2 mb-2"><i class="fa-solid fa-users"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Team Active Size</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['team_size'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(220, 53, 69, 0.12); border-radius: 16px; border: 1px solid rgba(220, 53, 69, 0.25) !important;">
                    <div class="text-danger fs-2 mb-2"><i class="fa-solid fa-plane-departure"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Pending Team Leaves</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['pending_leaves'] ?? 0; ?></h2>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(255, 193, 7, 0.12); border-radius: 16px; border: 1px solid rgba(255, 193, 7, 0.25) !important;">
                    <div class="text-warning fs-2 mb-2"><i class="fa-solid fa-user-clock"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Pending Team Attendance</h6>
                    <h2 class="text-white fw-bold mb-0"><?php echo $stats['pending_attendance'] ?? 0; ?></h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-envelope text-primary me-2"></i>Action Required</h5>
                    <div class="d-flex flex-column gap-2">
                        <a href="index.php?route=leaves/approvals" class="btn btn-outline-danger btn-sm text-start p-3 w-100 mb-2" style="border-radius:10px;">
                            <i class="fa-solid fa-plane-departure me-2"></i>Review Pending Leaves
                        </a>
                        <a href="index.php?route=attendance/approvals" class="btn btn-outline-warning btn-sm text-start p-3 w-100" style="border-radius:10px;">
                            <i class="fa-solid fa-user-check me-2"></i>Review Pending Attendance Exceptions
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-umbrella-beach text-warning me-2"></i>Holidays</h5>
                    <div class="row">
                        <?php foreach ($data['holidays'] as $h): ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.03);">
                                    <span class="text-white small fw-bold"><?php echo htmlspecialchars($h->holiday_name); ?></span>
                                    <span class="text-secondary small"><?php echo date('M d', strtotime($h->holiday_date)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- =======================================================================
         EMPLOYEE DASHBOARD VIEW
         ======================================================================= -->
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(25, 135, 84, 0.12); border-radius: 16px; border: 1px solid rgba(25, 135, 84, 0.25) !important;">
                    <div class="text-success fs-2 mb-2"><i class="fa-solid fa-fingerprint"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Check-in Status Today</h6>
                    <h4 class="text-white fw-bold mb-0">
                        <?php echo !empty($personal['today']) ? 'Checked In (' . date('h:i A', strtotime($personal['today']->login_at)) . ')' : 'Not Checked In'; ?>
                    </h4>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(13, 110, 253, 0.12); border-radius: 16px; border: 1px solid rgba(13, 110, 253, 0.25) !important;">
                    <div class="text-primary fs-2 mb-2"><i class="fa-solid fa-mug-hot"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Casual Leave Balance</h6>
                    <h3 class="text-white fw-bold mb-0"><?php echo number_format((float)($personal['balance']->casual_leave ?? 12.0), 1); ?> Days</h3>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm p-4 text-center" style="background: rgba(255, 193, 7, 0.12); border-radius: 16px; border: 1px solid rgba(255, 193, 7, 0.25) !important;">
                    <div class="text-warning fs-2 mb-2"><i class="fa-solid fa-plane-departure"></i></div>
                    <h6 class="text-secondary small fw-bold text-uppercase">Total Earned Leaves</h6>
                    <h3 class="text-white fw-bold mb-0"><?php echo number_format((float)($personal['balance']->earned_leave ?? 15.0), 1); ?> Days</h3>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-bolt text-primary me-2"></i>Quick Actions</h5>
                    <div class="d-flex flex-column gap-2">
                        <a href="index.php?route=attendance/index" class="btn btn-outline-success p-3 text-start w-100" style="border-radius:10px;">
                            <i class="fa-solid fa-fingerprint me-2"></i>Go to Attendance Screen
                        </a>
                        <a href="index.php?route=leaves/index" class="btn btn-outline-primary p-3 text-start w-100 mt-2" style="border-radius:10px;">
                            <i class="fa-solid fa-paper-plane me-2"></i>Apply for Leaves
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-umbrella-beach text-warning me-2"></i>Holidays</h5>
                    <div class="row">
                        <?php foreach ($data['holidays'] as $h): ?>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 rounded d-flex justify-content-between align-items-center" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.03);">
                                    <span class="text-white small fw-bold"><?php echo htmlspecialchars($h->holiday_name); ?></span>
                                    <span class="text-secondary small"><?php echo date('M d', strtotime($h->holiday_date)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
