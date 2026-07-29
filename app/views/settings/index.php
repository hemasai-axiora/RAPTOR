<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-15 border-success border-opacity-25 text-success shadow-lg mb-4" role="alert" style="border-radius:12px;">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="pulse-card">
            <h4 class="text-white mb-4"><i class="fa-solid fa-sliders text-primary me-2"></i>Admin Configuration Hub</h4>

            <form action="index.php?route=settings/save" method="POST" id="settings-form">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-4">
                    <label for="gemini_api_key" class="form-label text-secondary">Gemini API Key</label>
                    <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control bg-dark text-white border-secondary" 
                           value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" 
                           placeholder="AI summary translation key">
                    <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Required to generate Chief Executive insights automatically from live metrics.</div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 1. Security & Retention -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Security &amp; Retention</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Max Failed Logins</label>
                        <input type="number" min="1" name="auth.max_failed_attempts" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['auth.max_failed_attempts'] ?? '5'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Lockout Minutes</label>
                        <input type="number" min="1" name="auth.lockout_minutes" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['auth.lockout_minutes'] ?? '15'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">API Limit</label>
                        <input type="number" min="1" name="rate.api_limit" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['rate.api_limit'] ?? '120'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">API Window Seconds</label>
                        <input type="number" min="1" name="rate.api_window_seconds" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['rate.api_window_seconds'] ?? '60'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Limit</label>
                        <input type="number" min="1" name="rate.login_limit" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['rate.login_limit'] ?? '20'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Window Seconds</label>
                        <input type="number" min="1" name="rate.login_window_seconds" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['rate.login_window_seconds'] ?? '300'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Audit Retention Days</label>
                        <input type="number" min="1" name="retention.audit_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['retention.audit_days'] ?? '365'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Security Event Days</label>
                        <input type="number" min="1" name="retention.security_events_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['retention.security_events_days'] ?? '365'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Attempt Days</label>
                        <input type="number" min="1" name="retention.login_attempts_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['retention.login_attempts_days'] ?? '90'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Notification Days</label>
                        <input type="number" min="1" name="retention.notifications_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['retention.notifications_days'] ?? '180'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Location Retention Days</label>
                        <input type="number" min="1" name="retention.location_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['retention.location_days'] ?? ($settings['location.retention_days'] ?? '90')); ?>">
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 2. Attendance & Location Rules (Layout Restructured & Fixed) -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-business-time text-warning me-2"></i>Attendance &amp; Location Rules</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Shift Start</label>
                        <input type="time" name="attendance.shift_start" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['attendance.shift_start'] ?? '09:30'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Shift End</label>
                        <input type="time" name="attendance.shift_end" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['attendance.shift_end'] ?? '18:30'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Grace Minutes</label>
                        <input type="number" min="0" name="attendance.grace_minutes" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['attendance.grace_minutes'] ?? '15'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Ping Interval Seconds</label>
                        <input type="number" min="30" name="location.ping_interval_seconds" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['location.ping_interval_seconds'] ?? '120'); ?>">
                    </div>
                </div>

                <!-- Fixed Row Layout for Retention & Toggles -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Raw Location Retention Days</label>
                        <input type="number" min="1" name="location.retention_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['location.retention_days'] ?? '90'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Geofence Enforcer</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Enforce Geofence</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="attendance.geofence_enabled" id="attendance_geofence_enabled" <?php echo (($settings['attendance.geofence_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Foreground Location</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Enable Foreground Location</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="location.tracking_enabled" id="location_tracking_enabled" <?php echo (($settings['location.tracking_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 3. Week Off Configuration (NEW) -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-calendar-minus text-info me-2"></i>Week Off Configuration</h5>
                <?php 
                    $selectedDays = array_map('trim', explode(',', $settings['attendance.week_off_days'] ?? 'Sunday,Saturday'));
                    $daysList = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label text-secondary">Default Organization Week Off Days *</label>
                        <div class="p-3 bg-dark border border-secondary rounded d-flex flex-wrap gap-3">
                            <?php foreach ($daysList as $d): ?>
                                <div class="form-check form-check-inline me-2 mb-0">
                                    <input class="form-check-input" type="checkbox" name="attendance_week_off_days[]" value="<?php echo $d; ?>" id="week_off_<?php echo strtolower($d); ?>" <?php echo in_array($d, $selectedDays, true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-white small" for="week_off_<?php echo strtolower($d); ?>"><?php echo $d; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Alternate Saturdays Pattern</label>
                        <select name="attendance.alt_saturday_off" class="form-select bg-dark text-white border-secondary">
                            <option value="none" <?php echo (($settings['attendance.alt_saturday_off'] ?? 'none') === 'none') ? 'selected' : ''; ?>>None (All Selected Days Off)</option>
                            <option value="1st_3rd" <?php echo (($settings['attendance.alt_saturday_off'] ?? '') === '1st_3rd') ? 'selected' : ''; ?>>1st &amp; 3rd Saturday Off</option>
                            <option value="2nd_4th" <?php echo (($settings['attendance.alt_saturday_off'] ?? '') === '2nd_4th') ? 'selected' : ''; ?>>2nd &amp; 4th Saturday Off</option>
                            <option value="all" <?php echo (($settings['attendance.alt_saturday_off'] ?? '') === 'all') ? 'selected' : ''; ?>>All Saturdays Off</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <div class="p-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-white fw-semibold small">Allow Department &amp; Employee Level Week Off Overrides</div>
                                <div class="text-secondary small">If enabled, team managers can configure dedicated week-off days per shift/department under HR &amp; Payroll.</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="attendance.allow_week_off_override" id="allow_week_off_override" <?php echo (($settings['attendance.allow_week_off_override'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 4. Leave Policy Configuration (NEW) -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-umbrella-beach text-success me-2"></i>Leave Policy Configuration</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Leave Approval Workflow</label>
                        <select name="leave.approval_workflow" class="form-select bg-dark text-white border-secondary">
                            <option value="single_level" <?php echo (($settings['leave.approval_workflow'] ?? 'single_level') === 'single_level') ? 'selected' : ''; ?>>Single-level (Direct Manager)</option>
                            <option value="multi_level" <?php echo (($settings['leave.approval_workflow'] ?? '') === 'multi_level') ? 'selected' : ''; ?>>Multi-level (Manager → HR)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Minimum Notice Period (Days)</label>
                        <input type="number" min="0" name="leave.min_notice_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['leave.min_notice_days'] ?? '3'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Leave Year Start Month</label>
                        <select name="leave.year_start_month" class="form-select bg-dark text-white border-secondary">
                            <?php foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo (($settings['leave.year_start_month'] ?? 'January') === $m) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Manageable Leave Types Table -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-secondary mb-0">Leave Types &amp; Annual Quota Policy</label>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btn-add-leave-type"><i class="fa-solid fa-plus me-1"></i>Add Leave Type</button>
                    </div>
                    
                    <?php 
                        $defaultLeaveTypes = [
                            ['name' => 'Casual Leave (CL)', 'quota' => 12, 'carry_forward' => 'No', 'max_carry' => 0, 'encashable' => 'No'],
                            ['name' => 'Sick Leave (SL)', 'quota' => 10, 'carry_forward' => 'Yes', 'max_carry' => 5, 'encashable' => 'No'],
                            ['name' => 'Earned Leave (EL)', 'quota' => 15, 'carry_forward' => 'Yes', 'max_carry' => 30, 'encashable' => 'Yes'],
                            ['name' => 'Comp-Off (CO)', 'quota' => 6, 'carry_forward' => 'No', 'max_carry' => 0, 'encashable' => 'No']
                        ];
                        $leaveTypesJson = $settings['leave.types_json'] ?? json_encode($defaultLeaveTypes);
                    ?>
                    <input type="hidden" name="leave.types_json" id="leave_types_json" value="<?php echo htmlspecialchars($leaveTypesJson); ?>">

                    <div class="table-responsive">
                        <table class="table table-dark table-hover border-secondary align-middle mb-0" id="leave-types-table" style="font-size:0.88rem;">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Leave Type Name</th>
                                    <th>Annual Quota (Days)</th>
                                    <th>Carry Forward</th>
                                    <th>Max Carry Days</th>
                                    <th>Encashable</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rendered dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 5. Shift Roster Configuration (NEW) -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-clock text-primary me-2"></i>Shift Roster Configuration</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="roster_mode_select" class="form-label text-secondary">Roster Assignment Mode</label>
                        <select name="attendance.roster_mode" id="roster_mode_select" class="form-select bg-dark text-white border-secondary">
                            <option value="fixed" <?php echo (($settings['attendance.roster_mode'] ?? 'fixed') === 'fixed') ? 'selected' : ''; ?>>Fixed (Same Shift for All Employees)</option>
                            <option value="rotational" <?php echo (($settings['attendance.roster_mode'] ?? '') === 'rotational') ? 'selected' : ''; ?>>Rotational (Scheduled Roster)</option>
                            <option value="per_employee" <?php echo (($settings['attendance.roster_mode'] ?? '') === 'per_employee') ? 'selected' : ''; ?>>Per-Employee Assignment</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">Roster Publish Lead Time (Days)</label>
                        <input type="number" min="1" name="attendance.roster_publish_lead_days" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['attendance.roster_publish_lead_days'] ?? '7'); ?>">
                    </div>
                </div>

                <!-- Rotational Roster Notice Banner -->
                <div id="rotational_notice_banner" class="alert alert-info border-0 shadow mb-3 <?php echo (($settings['attendance.roster_mode'] ?? 'fixed') === 'fixed') ? 'd-none' : ''; ?>" style="background: rgba(13, 202, 240, 0.15); color: #0dcaf0;">
                    <i class="fa-solid fa-circle-info me-2"></i> <strong>Rotational &amp; Custom Rostering Notice</strong>: Rotational and employee-level shift assignments require the interactive Shift Roster Builder UI (managed under <strong>HR &amp; Payroll &gt; Shift Roster</strong>). Global settings define shift templates and lead time.
                </div>

                <!-- Manageable Shift Templates Table -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-secondary mb-0">Shift Templates Directory</label>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-shift-template"><i class="fa-solid fa-plus me-1"></i>Add Shift Template</button>
                    </div>

                    <?php 
                        $defaultShifts = [
                            ['name' => 'General Shift', 'start_time' => '09:30', 'end_time' => '18:30', 'grace_minutes' => 15],
                            ['name' => 'Morning Shift', 'start_time' => '06:00', 'end_time' => '15:00', 'grace_minutes' => 10],
                            ['name' => 'Night Shift', 'start_time' => '22:00', 'end_time' => '07:00', 'grace_minutes' => 15]
                        ];
                        $shiftTemplatesJson = $settings['attendance.shift_templates_json'] ?? json_encode($defaultShifts);
                    ?>
                    <input type="hidden" name="attendance.shift_templates_json" id="shift_templates_json" value="<?php echo htmlspecialchars($shiftTemplatesJson); ?>">

                    <div class="table-responsive">
                        <table class="table table-dark table-hover border-secondary align-middle mb-0" id="shift-templates-table" style="font-size:0.88rem;">
                            <thead>
                                <tr class="text-secondary">
                                    <th>Shift Name</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Grace Minutes</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rendered dynamically by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 6. Alerts & Notifications (Layout Fixed) -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-bell text-danger me-2"></i>Alerts &amp; Notifications</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Lead Contact SLA Hours</label>
                        <input type="number" min="1" name="lead.contact_sla_hours" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['lead.contact_sla_hours'] ?? '24'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Scheduled Alerts</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Cron Alerts</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="alerts.cron_enabled" id="alerts_cron_enabled" <?php echo (($settings['alerts.cron_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Browser Push</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Web Push Opt-in</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="alerts.web_push_enabled" id="alerts_web_push_enabled" <?php echo (($settings['alerts.web_push_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Email Alerts</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Email Alerts</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="alerts.email_enabled" id="alerts_email_enabled" <?php echo (($settings['alerts.email_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-secondary">VAPID Public Key</label>
                        <input type="text" name="alerts.vapid_public_key" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['alerts.vapid_public_key'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">VAPID Private Key</label>
                        <input type="password" name="alerts.vapid_private_key" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['alerts.vapid_private_key'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Alert Email From</label>
                        <input type="email" name="alerts.email_from" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['alerts.email_from'] ?? ''); ?>">
                    </div>
                </div>

                <?php if (!empty($alert_rules)): ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>Category</th>
                                    <th>Enabled</th>
                                    <th>Severity</th>
                                    <th>Threshold</th>
                                    <th>Recipients</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alert_rules as $rule): ?>
                                    <tr>
                                        <td>
                                            <div class="text-white fw-semibold"><?php echo htmlspecialchars($rule->name); ?></div>
                                            <div class="text-secondary small"><?php echo htmlspecialchars($rule->rule_key); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($rule->category); ?></td>
                                        <td>
                                            <input class="form-check-input" type="checkbox"
                                                   name="alert_rules[<?php echo htmlspecialchars($rule->rule_key); ?>][enabled]"
                                                   value="1" <?php echo $rule->enabled ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <select name="alert_rules[<?php echo htmlspecialchars($rule->rule_key); ?>][severity]" class="form-select form-select-sm bg-dark text-white border-secondary">
                                                <?php foreach (['info', 'warning', 'critical'] as $severity): ?>
                                                    <option value="<?php echo $severity; ?>" <?php echo $rule->severity === $severity ? 'selected' : ''; ?>><?php echo ucfirst($severity); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 align-items-center">
                                                <input type="number" step="0.01" name="alert_rules[<?php echo htmlspecialchars($rule->rule_key); ?>][threshold_value]"
                                                       class="form-control form-control-sm bg-dark text-white border-secondary"
                                                       value="<?php echo htmlspecialchars((string) $rule->threshold_value); ?>">
                                                <span class="text-secondary small"><?php echo htmlspecialchars($rule->threshold_unit ?? ''); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="alert_rules[<?php echo htmlspecialchars($rule->rule_key); ?>][recipient_scope]" class="form-select form-select-sm bg-dark text-white border-secondary">
                                                <?php foreach (['owner' => 'Owner', 'manager' => 'Manager', 'both' => 'Both', 'admin' => 'Admin'] as $scope => $label): ?>
                                                    <option value="<?php echo $scope; ?>" <?php echo $rule->recipient_scope === $scope ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 7. Report Digest -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-envelope-open-text text-info me-2"></i>Report Digest</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Digest Email Frequency</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Monthly Digest Email</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="reports.email_enabled" id="reports_email_enabled" <?php echo (($settings['reports.email_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label text-secondary">Digest Recipients</label>
                        <input type="text" name="reports.digest_recipients" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['reports.digest_recipients'] ?? ''); ?>"
                               placeholder="manager@example.com, admin@example.com">
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 8. Marketing Connectors -->
                <h5 class="text-white mb-3" style="font-size:1.05rem;"><i class="fa-solid fa-plug text-primary me-2"></i>Marketing Connectors</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="ga4_measurement_id" class="form-label text-secondary">Google Analytics 4 Measurement ID</label>
                        <input type="text" name="ga4_measurement_id" id="ga4_measurement_id" class="form-control bg-dark text-white border-secondary" 
                               value="<?php echo htmlspecialchars($settings['ga4_measurement_id'] ?? ''); ?>" 
                               placeholder="G-XXXXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label for="google_ads_client_id" class="form-label text-secondary">Google Ads Account ID</label>
                        <input type="text" name="google_ads_client_id" id="google_ads_client_id" class="form-control bg-dark text-white border-secondary" 
                               value="<?php echo htmlspecialchars($settings['google_ads_client_id'] ?? ''); ?>" 
                               placeholder="XXX-XXX-XXXX">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_access_token" class="form-label text-secondary">Meta Access Token</label>
                        <input type="password" name="meta_access_token" id="meta_access_token" class="form-control bg-dark text-white border-secondary" 
                               value="<?php echo htmlspecialchars($settings['meta_access_token'] ?? ''); ?>" 
                               placeholder="EAABw...">
                    </div>
                    <div class="col-md-6">
                        <label for="linkedin_urn" class="form-label text-secondary">LinkedIn Page URN</label>
                        <input type="text" name="linkedin_urn" id="linkedin_urn" class="form-control bg-dark text-white border-secondary" 
                               value="<?php echo htmlspecialchars($settings['linkedin_urn'] ?? ''); ?>" 
                               placeholder="urn:li:organization:XXXXXX">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4 border-top border-secondary border-opacity-10 pt-3">
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" style="background: var(--primary); border: none;">
                        <i class="fa-regular fa-floppy-disk me-2"></i>Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 1. Leave Types JSON Table Management
    let leaveTypes = [];
    try {
        leaveTypes = JSON.parse($('#leave_types_json').val() || '[]');
    } catch(e) { leaveTypes = []; }

    function renderLeaveTypes() {
        const tbody = $('#leave-types-table tbody');
        tbody.empty();
        if (leaveTypes.length === 0) {
            tbody.append('<tr><td colspan="6" class="text-center text-secondary py-2">No leave types defined. Click "Add Leave Type" above.</td></tr>');
            return;
        }

        leaveTypes.forEach((lt, idx) => {
            const tr = $('<tr>');
            tr.append(`<td><input type="text" class="form-control form-control-sm bg-dark text-white border-secondary lt-name" data-idx="${idx}" value="${lt.name || ''}"></td>`);
            tr.append(`<td><input type="number" min="0" class="form-control form-control-sm bg-dark text-white border-secondary lt-quota" data-idx="${idx}" value="${lt.quota || 0}"></td>`);
            tr.append(`<td>
                <select class="form-select form-select-sm bg-dark text-white border-secondary lt-carry" data-idx="${idx}">
                    <option value="No" ${lt.carry_forward === 'No' ? 'selected' : ''}>No</option>
                    <option value="Yes" ${lt.carry_forward === 'Yes' ? 'selected' : ''}>Yes</option>
                </select>
            </td>`);
            tr.append(`<td><input type="number" min="0" class="form-control form-control-sm bg-dark text-white border-secondary lt-maxcarry" data-idx="${idx}" value="${lt.max_carry || 0}"></td>`);
            tr.append(`<td>
                <select class="form-select form-select-sm bg-dark text-white border-secondary lt-encash" data-idx="${idx}">
                    <option value="No" ${lt.encashable === 'No' ? 'selected' : ''}>No</option>
                    <option value="Yes" ${lt.encashable === 'Yes' ? 'selected' : ''}>Yes</option>
                </select>
            </td>`);
            tr.append(`<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del-lt" data-idx="${idx}"><i class="fa-solid fa-trash"></i></button></td>`);
            tbody.append(tr);
        });
    }

    function syncLeaveTypesJson() {
        $('#leave_types_json').val(JSON.stringify(leaveTypes));
    }

    $('#btn-add-leave-type').on('click', function() {
        leaveTypes.push({ name: 'New Leave Type', quota: 10, carry_forward: 'No', max_carry: 0, encashable: 'No' });
        renderLeaveTypes();
        syncLeaveTypesJson();
    });

    $(document).on('change input', '.lt-name, .lt-quota, .lt-carry, .lt-maxcarry, .lt-encash', function() {
        const idx = $(this).data('idx');
        if (leaveTypes[idx]) {
            leaveTypes[idx].name = $(`input.lt-name[data-idx="${idx}"]`).val();
            leaveTypes[idx].quota = parseInt($(`input.lt-quota[data-idx="${idx}"]`).val() || 0);
            leaveTypes[idx].carry_forward = $(`select.lt-carry[data-idx="${idx}"]`).val();
            leaveTypes[idx].max_carry = parseInt($(`input.lt-maxcarry[data-idx="${idx}"]`).val() || 0);
            leaveTypes[idx].encashable = $(`select.lt-encash[data-idx="${idx}"]`).val();
            syncLeaveTypesJson();
        }
    });

    $(document).on('click', '.btn-del-lt', function() {
        const idx = $(this).data('idx');
        leaveTypes.splice(idx, 1);
        renderLeaveTypes();
        syncLeaveTypesJson();
    });

    renderLeaveTypes();

    // 2. Shift Templates JSON Table Management
    let shiftTemplates = [];
    try {
        shiftTemplates = JSON.parse($('#shift_templates_json').val() || '[]');
    } catch(e) { shiftTemplates = []; }

    function renderShiftTemplates() {
        const tbody = $('#shift-templates-table tbody');
        tbody.empty();
        if (shiftTemplates.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center text-secondary py-2">No shift templates defined. Click "Add Shift Template" above.</td></tr>');
            return;
        }

        shiftTemplates.forEach((st, idx) => {
            const tr = $('<tr>');
            tr.append(`<td><input type="text" class="form-control form-control-sm bg-dark text-white border-secondary st-name" data-idx="${idx}" value="${st.name || ''}"></td>`);
            tr.append(`<td><input type="time" class="form-control form-control-sm bg-dark text-white border-secondary st-start" data-idx="${idx}" value="${st.start_time || '09:30'}"></td>`);
            tr.append(`<td><input type="time" class="form-control form-control-sm bg-dark text-white border-secondary st-end" data-idx="${idx}" value="${st.end_time || '18:30'}"></td>`);
            tr.append(`<td><input type="number" min="0" class="form-control form-control-sm bg-dark text-white border-secondary st-grace" data-idx="${idx}" value="${st.grace_minutes || 15}"></td>`);
            tr.append(`<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-del-st" data-idx="${idx}"><i class="fa-solid fa-trash"></i></button></td>`);
            tbody.append(tr);
        });
    }

    function syncShiftTemplatesJson() {
        $('#shift_templates_json').val(JSON.stringify(shiftTemplates));
    }

    $('#btn-add-shift-template').on('click', function() {
        shiftTemplates.push({ name: 'Custom Shift', start_time: '09:00', end_time: '18:00', grace_minutes: 15 });
        renderShiftTemplates();
        syncShiftTemplatesJson();
    });

    $(document).on('change input', '.st-name, .st-start, .st-end, .st-grace', function() {
        const idx = $(this).data('idx');
        if (shiftTemplates[idx]) {
            shiftTemplates[idx].name = $(`input.st-name[data-idx="${idx}"]`).val();
            shiftTemplates[idx].start_time = $(`input.st-start[data-idx="${idx}"]`).val();
            shiftTemplates[idx].end_time = $(`input.st-end[data-idx="${idx}"]`).val();
            shiftTemplates[idx].grace_minutes = parseInt($(`input.st-grace[data-idx="${idx}"]`).val() || 0);
            syncShiftTemplatesJson();
        }
    });

    $(document).on('click', '.btn-del-st', function() {
        const idx = $(this).data('idx');
        shiftTemplates.splice(idx, 1);
        renderShiftTemplates();
        syncShiftTemplatesJson();
    });

    renderShiftTemplates();

    // 3. Rotational Notice Banner Toggle
    $('#roster_mode_select').on('change', function() {
        const val = $(this).val();
        if (val === 'rotational' || val === 'per_employee') {
            $('#rotational_notice_banner').removeClass('d-none');
        } else {
            $('#rotational_notice_banner').addClass('d-none');
        }
    });
});
</script>
