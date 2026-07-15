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

            <form action="index.php?route=settings/save" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-4">
                    <label for="gemini_api_key" class="form-label text-secondary">Gemini API Key</label>
                    <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" 
                           value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" 
                           placeholder="AI summary translation key">
                    <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Required to generate Chief Executive insights automatically from live metrics.</div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <h5 class="text-white mb-3" style="font-size:1.05rem;">Security & Retention</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Max Failed Logins</label>
                        <input type="number" min="1" name="auth.max_failed_attempts" class="form-control"
                               value="<?php echo htmlspecialchars($settings['auth.max_failed_attempts'] ?? '5'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Lockout Minutes</label>
                        <input type="number" min="1" name="auth.lockout_minutes" class="form-control"
                               value="<?php echo htmlspecialchars($settings['auth.lockout_minutes'] ?? '15'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">API Limit</label>
                        <input type="number" min="1" name="rate.api_limit" class="form-control"
                               value="<?php echo htmlspecialchars($settings['rate.api_limit'] ?? '120'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">API Window Seconds</label>
                        <input type="number" min="1" name="rate.api_window_seconds" class="form-control"
                               value="<?php echo htmlspecialchars($settings['rate.api_window_seconds'] ?? '60'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Limit</label>
                        <input type="number" min="1" name="rate.login_limit" class="form-control"
                               value="<?php echo htmlspecialchars($settings['rate.login_limit'] ?? '20'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Window Seconds</label>
                        <input type="number" min="1" name="rate.login_window_seconds" class="form-control"
                               value="<?php echo htmlspecialchars($settings['rate.login_window_seconds'] ?? '300'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Audit Retention Days</label>
                        <input type="number" min="1" name="retention.audit_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['retention.audit_days'] ?? '365'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Security Event Days</label>
                        <input type="number" min="1" name="retention.security_events_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['retention.security_events_days'] ?? '365'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Login Attempt Days</label>
                        <input type="number" min="1" name="retention.login_attempts_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['retention.login_attempts_days'] ?? '90'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Notification Days</label>
                        <input type="number" min="1" name="retention.notifications_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['retention.notifications_days'] ?? '180'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Location Retention Days</label>
                        <input type="number" min="1" name="retention.location_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['retention.location_days'] ?? ($settings['location.retention_days'] ?? '90')); ?>">
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <h5 class="text-white mb-3" style="font-size:1.05rem;">Attendance & Location Rules</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Shift Start</label>
                        <input type="time" name="attendance.shift_start" class="form-control"
                               value="<?php echo htmlspecialchars($settings['attendance.shift_start'] ?? '09:30'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Shift End</label>
                        <input type="time" name="attendance.shift_end" class="form-control"
                               value="<?php echo htmlspecialchars($settings['attendance.shift_end'] ?? '18:30'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Grace Minutes</label>
                        <input type="number" min="0" name="attendance.grace_minutes" class="form-control"
                               value="<?php echo htmlspecialchars($settings['attendance.grace_minutes'] ?? '15'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Ping Interval Seconds</label>
                        <input type="number" min="30" name="location.ping_interval_seconds" class="form-control"
                               value="<?php echo htmlspecialchars($settings['location.ping_interval_seconds'] ?? '120'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Raw Location Retention Days</label>
                        <input type="number" min="1" name="location.retention_days" class="form-control"
                               value="<?php echo htmlspecialchars($settings['location.retention_days'] ?? '90'); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="attendance.geofence_enabled" id="attendance_geofence_enabled" <?php echo (($settings['attendance.geofence_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="attendance_geofence_enabled">Enforce geofence</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="location.tracking_enabled" id="location_tracking_enabled" <?php echo (($settings['location.tracking_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="location_tracking_enabled">Enable foreground location</label>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <h5 class="text-white mb-3" style="font-size:1.05rem;">Alerts & Notifications</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Lead Contact SLA Hours</label>
                        <input type="number" min="1" name="lead.contact_sla_hours" class="form-control"
                               value="<?php echo htmlspecialchars($settings['lead.contact_sla_hours'] ?? '24'); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="alerts.cron_enabled" id="alerts_cron_enabled" <?php echo (($settings['alerts.cron_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="alerts_cron_enabled">Cron alerts enabled</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="alerts.web_push_enabled" id="alerts_web_push_enabled" <?php echo (($settings['alerts.web_push_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="alerts_web_push_enabled">Web Push opt-in</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="alerts.email_enabled" id="alerts_email_enabled" <?php echo (($settings['alerts.email_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="alerts_email_enabled">Email alerts enabled</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">VAPID Public Key</label>
                        <input type="text" name="alerts.vapid_public_key" class="form-control"
                               value="<?php echo htmlspecialchars($settings['alerts.vapid_public_key'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">VAPID Private Key</label>
                        <input type="password" name="alerts.vapid_private_key" class="form-control"
                               value="<?php echo htmlspecialchars($settings['alerts.vapid_private_key'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Alert Email From</label>
                        <input type="email" name="alerts.email_from" class="form-control"
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

                <h5 class="text-white mb-3" style="font-size:1.05rem;">Report Digest</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="reports.email_enabled" id="reports_email_enabled" <?php echo (($settings['reports.email_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label text-secondary" for="reports_email_enabled">Monthly digest email</label>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label text-secondary">Digest Recipients</label>
                        <input type="text" name="reports.digest_recipients" class="form-control"
                               value="<?php echo htmlspecialchars($settings['reports.digest_recipients'] ?? ''); ?>"
                               placeholder="manager@example.com, admin@example.com">
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <h5 class="text-white mb-3" style="font-size:1.05rem;">Marketing Connectors</h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="ga4_measurement_id" class="form-label text-secondary">Google Analytics 4 Measurement ID</label>
                        <input type="text" name="ga4_measurement_id" id="ga4_measurement_id" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['ga4_measurement_id'] ?? ''); ?>" 
                               placeholder="G-XXXXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label for="google_ads_client_id" class="form-label text-secondary">Google Ads Account ID</label>
                        <input type="text" name="google_ads_client_id" id="google_ads_client_id" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['google_ads_client_id'] ?? ''); ?>" 
                               placeholder="XXX-XXX-XXXX">
                    </div>
                    <div class="col-md-6">
                        <label for="meta_access_token" class="form-label text-secondary">Meta Access Token</label>
                        <input type="password" name="meta_access_token" id="meta_access_token" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['meta_access_token'] ?? ''); ?>" 
                               placeholder="EAABw...">
                    </div>
                    <div class="col-md-6">
                        <label for="linkedin_urn" class="form-label text-secondary">LinkedIn Page URN</label>
                        <input type="text" name="linkedin_urn" id="linkedin_urn" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['linkedin_urn'] ?? ''); ?>" 
                               placeholder="urn:li:organization:XXXXXX">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4 border-top border-secondary border-opacity-10 pt-3">
                    <button type="submit" class="btn btn-primary px-4 py-2" style="background: var(--primary); border: none; font-weight:600;">
                        <i class="fa-regular fa-floppy-disk me-2"></i>Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
