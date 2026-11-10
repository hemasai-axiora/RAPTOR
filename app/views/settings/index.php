<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show bg-success bg-opacity-15 border-success border-opacity-25 text-success shadow-lg mb-4" role="alert" style="border-radius:12px;">
                <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="pulse-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h4 class="text-white mb-0"><i class="fa-solid fa-sliders text-primary me-2"></i>Admin Configuration Hub</h4>
                <div class="d-flex gap-2">
                    <a href="update.php" target="_blank" class="btn btn-sm btn-outline-success" title="Pull latest code and apply migrations">
                        <i class="fa-solid fa-cloud-arrow-down me-1"></i> Sync Live Release
                    </a>
                    <a href="index.php?route=settings/dlq" class="btn btn-sm btn-outline-warning">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Dead-Letter Queue
                    </a>
                    <a href="index.php?route=apidocs/index" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="fa-solid fa-book-bookmark me-1"></i> Interactive API Docs
                    </a>
                </div>
            </div>

            <form action="index.php?route=settings/save" method="POST" id="settings-form">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <!-- Universal AI & LLM Engine Hub -->
                <div class="p-3 mb-4 rounded-3 border border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(10px);">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="text-white mb-1"><i class="fa-solid fa-wand-magic-sparkles text-info me-2"></i>Universal AI &amp; LLM Engine</h5>
                            <p class="text-secondary small mb-0">Connect Google Gemini, OpenAI GPT, or Anthropic Claude to power email copywriting, subject line optimization, omnichannel smart replies, and executive insights.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-25 px-2.5 py-1.5">
                                <i class="fa-solid fa-bolt me-1"></i>Multi-Model Support
                            </span>
                        </div>
                    </div>

                    <!-- Default Provider Selection -->
                    <div class="mb-4 p-2.5 rounded bg-dark bg-opacity-50 border border-secondary border-opacity-20">
                        <label class="form-label text-white small fw-bold mb-2">Default Active AI Provider</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php $activeProv = $settings['ai_default_provider'] ?? 'gemini'; ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ai_default_provider" id="prov_gemini" value="gemini" <?php echo ($activeProv === 'gemini') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-light" for="prov_gemini">
                                    <i class="fa-brands fa-google text-primary me-1"></i> Google Gemini
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ai_default_provider" id="prov_openai" value="openai" <?php echo ($activeProv === 'openai') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-light" for="prov_openai">
                                    <i class="fa-solid fa-robot text-success me-1"></i> OpenAI (GPT-4o)
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ai_default_provider" id="prov_anthropic" value="anthropic" <?php echo ($activeProv === 'anthropic') ? 'checked' : ''; ?>>
                                <label class="form-check-label text-light" for="prov_anthropic">
                                    <i class="fa-solid fa-brain text-warning me-1"></i> Anthropic (Claude 3.5)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Provider Cards Grid -->
                    <div class="row g-3">
                        <!-- 1. Google Gemini Card -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 bg-dark bg-opacity-75 border-secondary border-opacity-30 rounded-3 shadow-sm">
                                <div class="card-header bg-transparent border-secondary border-opacity-20 d-flex justify-content-between align-items-center py-2.5">
                                    <span class="text-white fw-bold"><i class="fa-brands fa-google text-primary me-2"></i>Google Gemini</span>
                                    <?php if (!empty($settings['gemini_api_key'])): ?>
                                        <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-25" style="font-size:0.7rem;"><i class="fa-solid fa-check me-1"></i>Configured</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-20 text-secondary border border-secondary border-opacity-25" style="font-size:0.7rem;">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-2.5">
                                        <label class="form-label text-secondary small mb-1">API Key</label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control bg-black text-white border-secondary"
                                                   value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIzaSy...">
                                            <button class="btn btn-outline-secondary toggle-key-visibility" type="button" data-target="gemini_api_key">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-primary text-decoration-none" style="font-size:0.7rem;">Get Key at Google AI Studio &rarr;</a>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small mb-1">Model</label>
                                        <?php $geminiModel = $settings['gemini_model'] ?? 'gemini-2.5-flash'; ?>
                                        <select name="gemini_model" id="gemini_model" class="form-select form-select-sm bg-black text-white border-secondary">
                                            <option value="gemini-2.5-flash" <?php echo ($geminiModel === 'gemini-2.5-flash') ? 'selected' : ''; ?>>gemini-2.5-flash (Fast &amp; Recommended)</option>
                                            <option value="gemini-1.5-pro" <?php echo ($geminiModel === 'gemini-1.5-pro') ? 'selected' : ''; ?>>gemini-1.5-pro (Deep Reasoning)</option>
                                            <option value="gemini-2.0-flash" <?php echo ($geminiModel === 'gemini-2.0-flash') ? 'selected' : ''; ?>>gemini-2.0-flash (Next-Gen Preview)</option>
                                            <option value="gemini-1.5-flash" <?php echo ($geminiModel === 'gemini-1.5-flash') ? 'selected' : ''; ?>>gemini-1.5-flash (Legacy)</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary w-100 btn-test-ai" data-provider="gemini" data-key-id="gemini_api_key" data-model-id="gemini_model">
                                        <i class="fa-solid fa-plug me-1"></i> Test Gemini Connection
                                    </button>
                                    <div class="ai-test-result mt-2 small" id="gemini-test-result" style="display:none;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. OpenAI GPT Card -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 bg-dark bg-opacity-75 border-secondary border-opacity-30 rounded-3 shadow-sm">
                                <div class="card-header bg-transparent border-secondary border-opacity-20 d-flex justify-content-between align-items-center py-2.5">
                                    <span class="text-white fw-bold"><i class="fa-solid fa-robot text-success me-2"></i>OpenAI GPT</span>
                                    <?php if (!empty($settings['openai_api_key'])): ?>
                                        <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-25" style="font-size:0.7rem;"><i class="fa-solid fa-check me-1"></i>Configured</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-20 text-secondary border border-secondary border-opacity-25" style="font-size:0.7rem;">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-2.5">
                                        <label class="form-label text-secondary small mb-1">API Key</label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" name="openai_api_key" id="openai_api_key" class="form-control bg-black text-white border-secondary"
                                                   value="<?php echo htmlspecialchars($settings['openai_api_key'] ?? ''); ?>" placeholder="sk-proj-...">
                                            <button class="btn btn-outline-secondary toggle-key-visibility" type="button" data-target="openai_api_key">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <a href="https://platform.openai.com/api-keys" target="_blank" class="text-success text-decoration-none" style="font-size:0.7rem;">Get Key at OpenAI &rarr;</a>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small mb-1">Model</label>
                                        <?php $openaiModel = $settings['openai_model'] ?? 'gpt-4o-mini'; ?>
                                        <select name="openai_model" id="openai_model" class="form-select form-select-sm bg-black text-white border-secondary">
                                            <option value="gpt-4o-mini" <?php echo ($openaiModel === 'gpt-4o-mini') ? 'selected' : ''; ?>>gpt-4o-mini (Fast &amp; Cost-Effective)</option>
                                            <option value="gpt-4o" <?php echo ($openaiModel === 'gpt-4o') ? 'selected' : ''; ?>>gpt-4o (High-Intelligence Flagship)</option>
                                            <option value="o3-mini" <?php echo ($openaiModel === 'o3-mini') ? 'selected' : ''; ?>>o3-mini (Reasoning Model)</option>
                                            <option value="gpt-4-turbo" <?php echo ($openaiModel === 'gpt-4-turbo') ? 'selected' : ''; ?>>gpt-4-turbo</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100 btn-test-ai" data-provider="openai" data-key-id="openai_api_key" data-model-id="openai_model">
                                        <i class="fa-solid fa-plug me-1"></i> Test OpenAI Connection
                                    </button>
                                    <div class="ai-test-result mt-2 small" id="openai-test-result" style="display:none;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Anthropic Claude Card -->
                        <div class="col-12 col-lg-4">
                            <div class="card h-100 bg-dark bg-opacity-75 border-secondary border-opacity-30 rounded-3 shadow-sm">
                                <div class="card-header bg-transparent border-secondary border-opacity-20 d-flex justify-content-between align-items-center py-2.5">
                                    <span class="text-white fw-bold"><i class="fa-solid fa-brain text-warning me-2"></i>Anthropic Claude</span>
                                    <?php if (!empty($settings['anthropic_api_key'])): ?>
                                        <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-25" style="font-size:0.7rem;"><i class="fa-solid fa-check me-1"></i>Configured</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-20 text-secondary border border-secondary border-opacity-25" style="font-size:0.7rem;">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-2.5">
                                        <label class="form-label text-secondary small mb-1">API Key</label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" name="anthropic_api_key" id="anthropic_api_key" class="form-control bg-black text-white border-secondary"
                                                   value="<?php echo htmlspecialchars($settings['anthropic_api_key'] ?? ''); ?>" placeholder="sk-ant-...">
                                            <button class="btn btn-outline-secondary toggle-key-visibility" type="button" data-target="anthropic_api_key">
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="d-flex justify-content-between mt-1">
                                            <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-warning text-decoration-none" style="font-size:0.7rem;">Get Key at Anthropic &rarr;</a>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-secondary small mb-1">Model</label>
                                        <?php $anthropicModel = $settings['anthropic_model'] ?? 'claude-3-5-haiku-20241022'; ?>
                                        <select name="anthropic_model" id="anthropic_model" class="form-select form-select-sm bg-black text-white border-secondary">
                                            <option value="claude-3-5-haiku-20241022" <?php echo ($anthropicModel === 'claude-3-5-haiku-20241022') ? 'selected' : ''; ?>>claude-3-5-haiku (Ultra Fast &amp; Cheap)</option>
                                            <option value="claude-3-5-sonnet-20241022" <?php echo ($anthropicModel === 'claude-3-5-sonnet-20241022') ? 'selected' : ''; ?>>claude-3-5-sonnet (Highest Accuracy)</option>
                                            <option value="claude-3-opus-20240229" <?php echo ($anthropicModel === 'claude-3-opus-20240229') ? 'selected' : ''; ?>>claude-3-opus</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-warning w-100 btn-test-ai" data-provider="anthropic" data-key-id="anthropic_api_key" data-model-id="anthropic_model">
                                        <i class="fa-solid fa-plug me-1"></i> Test Claude Connection
                                    </button>
                                    <div class="ai-test-result mt-2 small" id="anthropic-test-result" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fine-Tuning Parameters (Collapsible) -->
                    <div class="mt-3 pt-3 border-top border-secondary border-opacity-20">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small mb-1">Generation Temperature (Creativity: 0.0 - 1.0)</label>
                                <input type="number" step="0.1" min="0.0" max="1.0" name="ai_temperature" class="form-control form-control-sm bg-black text-white border-secondary"
                                       value="<?php echo htmlspecialchars($settings['ai_temperature'] ?? '0.7'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small mb-1">Max Output Tokens</label>
                                <input type="number" step="128" min="256" max="8192" name="ai_max_tokens" class="form-control form-control-sm bg-black text-white border-secondary"
                                       value="<?php echo htmlspecialchars($settings['ai_max_tokens'] ?? '2048'); ?>">
                            </div>
                        </div>
                    </div>
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

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 9. Email & SMTP Integration (Payroll Payslips & System Alerts) -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0" style="font-size:1.05rem;">
                        <i class="fa-solid fa-envelope-circle-check text-success me-2"></i>Email &amp; SMTP Integration
                    </h5>
                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                        <i class="fa-solid fa-paper-plane me-1"></i>Test SMTP Connection
                    </button>
                </div>
                <p class="text-secondary small mb-3">
                    Configure your corporate SMTP mail server (Gmail, Google Workspace, Microsoft 365, AWS SES, Brevo, SendGrid, or cPanel/Postfix) to automatically email vector PDF payslips to employees when salary slips are generated or released.
                </p>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Mail Protocol / Driver</label>
                        <select name="mail.driver" id="mail_driver" class="form-select bg-dark text-white border-secondary">
                            <option value="smtp" <?php echo (($settings['mail.driver'] ?? 'smtp') === 'smtp') ? 'selected' : ''; ?>>SMTP Socket (Recommended)</option>
                            <option value="mail" <?php echo (($settings['mail.driver'] ?? '') === 'mail') ? 'selected' : ''; ?>>PHP Native mail()</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">SMTP Host</label>
                        <input type="text" name="mail.host" id="mail_host" class="form-control bg-dark text-white border-secondary font-monospace"
                               value="<?php echo htmlspecialchars($settings['mail.host'] ?? ''); ?>"
                               placeholder="e.g. smtp.gmail.com or smtp.office365.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-secondary">SMTP Port</label>
                        <input type="number" name="mail.port" id="mail_port" class="form-control bg-dark text-white border-secondary font-monospace"
                               value="<?php echo htmlspecialchars($settings['mail.port'] ?? '587'); ?>"
                               placeholder="587 / 465">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary">Encryption Type</label>
                        <select name="mail.encryption" id="mail_encryption" class="form-select bg-dark text-white border-secondary">
                            <option value="tls" <?php echo (($settings['mail.encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS / STARTTLS (Port 587)</option>
                            <option value="ssl" <?php echo (($settings['mail.encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                            <option value="none" <?php echo (($settings['mail.encryption'] ?? '') === 'none') ? 'selected' : ''; ?>>None / Plain (Port 25)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary">SMTP Username / Email</label>
                        <input type="text" name="mail.username" id="mail_username" class="form-control bg-dark text-white border-secondary font-monospace"
                               value="<?php echo htmlspecialchars($settings['mail.username'] ?? ''); ?>"
                               placeholder="e.g. accounts@axioraglobalsolutions.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary">SMTP Password / App Password</label>
                        <div class="input-group">
                            <input type="password" name="mail.password" id="mail_password" class="form-control bg-dark text-white border-secondary font-monospace"
                                   value="<?php echo htmlspecialchars($settings['mail.password'] ?? ''); ?>"
                                   placeholder="Enter SMTP password or Google App Password">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('mail_password')">
                                <i class="fa-solid fa-eye" id="mail_password_icon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-secondary">Sender "From" Email</label>
                        <input type="email" name="mail.from_address" id="mail_from_address" class="form-control bg-dark text-white border-secondary font-monospace"
                               value="<?php echo htmlspecialchars($settings['mail.from_address'] ?? 'accounts@axioraglobalsolutions.com'); ?>"
                               placeholder="accounts@axioraglobalsolutions.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Sender "From" Name</label>
                        <input type="text" name="mail.from_name" id="mail_from_name" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['mail.from_name'] ?? 'Axiora Global Solutions'); ?>"
                               placeholder="Axiora Global Solutions - Payroll">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Auto-Email Payslips</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Auto-send on slip generation</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="mail.auto_send_on_generation" id="mail_auto_send_on_generation" <?php echo (($settings['mail.auto_send_on_generation'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-secondary border-opacity-15 my-4">

                <!-- 8. Payroll Lifecycle & Calculation Rules -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0" style="font-size:1.05rem;">
                        <i class="fa-solid fa-money-bill-wave text-success me-2"></i>Payroll Lifecycle &amp; Calculation Rules
                    </h5>
                    <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 py-1" style="font-size:0.75rem;">
                        <i class="fa-solid fa-check-double me-1"></i>Data-Driven Core
                    </span>
                </div>

                <div class="row g-3 mb-4">
                    <!-- Pay Cycle Type -->
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Pay Cycle Type</label>
                        <select name="payroll.cycle_type" id="payroll_cycle_type" class="form-select bg-dark text-white border-secondary">
                            <option value="calendar_month" <?php echo (($settings['payroll.cycle_type'] ?? 'calendar_month') === 'calendar_month') ? 'selected' : ''; ?>>
                                Standard Calendar Month (1st to Month End)
                            </option>
                            <option value="custom_cutoff" <?php echo (($settings['payroll.cycle_type'] ?? '') === 'custom_cutoff') ? 'selected' : ''; ?>>
                                Custom Cut-off Window (e.g. 26th to 25th)
                            </option>
                        </select>
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Defines the start and end boundary for calculating attendance, LOPs, and allowances.</div>
                    </div>

                    <!-- Cycle Start Day -->
                    <div class="col-md-2">
                        <label class="form-label text-secondary">Cycle Start Day</label>
                        <input type="number" min="1" max="28" name="payroll.cycle_start_day" id="payroll_cycle_start_day" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['payroll.cycle_start_day'] ?? '1'); ?>">
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Day of month (1 = 1st).</div>
                    </div>

                    <!-- Scheduled Payout Day -->
                    <div class="col-md-2">
                        <label class="form-label text-secondary">Pay Date (Day)</label>
                        <input type="number" min="1" max="28" name="payroll.pay_date_day" id="payroll_pay_date_day" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['payroll.pay_date_day'] ?? '1'); ?>">
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Day of following month.</div>
                    </div>

                    <!-- Proration Method -->
                    <div class="col-md-4">
                        <label class="form-label text-secondary">Working Days Divisor (Proration)</label>
                        <select name="payroll.proration_method" id="payroll_proration_method" class="form-select bg-dark text-white border-secondary">
                            <option value="calendar_days" <?php echo (($settings['payroll.proration_method'] ?? 'calendar_days') === 'calendar_days') ? 'selected' : ''; ?>>
                                Actual Calendar Days (30 / 31 / 28) - Recommended
                            </option>
                            <option value="fixed_30" <?php echo (($settings['payroll.proration_method'] ?? '') === 'fixed_30') ? 'selected' : ''; ?>>
                                Fixed 30 Days Every Month
                            </option>
                            <option value="working_days_only" <?php echo (($settings['payroll.proration_method'] ?? '') === 'working_days_only') ? 'selected' : ''; ?>>
                                Working Days Only (Excluding Weekends/Holidays)
                            </option>
                        </select>
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Divisor used for Loss of Pay (LOP) and mid-month joiner deductions.</div>
                    </div>

                    <!-- Statutory & Component Thresholds -->
                    <div class="col-md-3">
                        <label class="form-label text-secondary">EPF Wage Ceiling (₹)</label>
                        <input type="number" min="0" step="500" name="payroll.epf_wage_ceiling" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['payroll.epf_wage_ceiling'] ?? '15000'); ?>">
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Standard cap ₹15,000 (₹1,800 max EPF).</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-secondary">Gratuity Accrual (%)</label>
                        <input type="number" min="0" max="15" step="0.01" name="payroll.gratuity_pct" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['payroll.gratuity_pct'] ?? '4.81'); ?>">
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Standard 4.81% of Basic (15/26/12).</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-secondary">Fixed Special Allowance (₹)</label>
                        <input type="number" min="0" step="100" name="payroll.special_allowance_fixed" class="form-control bg-dark text-white border-secondary"
                               value="<?php echo htmlspecialchars($settings['payroll.special_allowance_fixed'] ?? '1600'); ?>">
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">Standard company baseline ₹1,600.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-secondary">Professional Tax (PT)</label>
                        <div class="p-2 px-3 bg-dark border border-secondary rounded d-flex justify-content-between align-items-center" style="height:38px;">
                            <span class="text-white small">Auto PT Slabs</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="payroll.pt_enabled" id="payroll_pt_enabled" <?php echo (($settings['payroll.pt_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-text text-secondary-emphasis" style="font-size:0.75rem;">₹200 (≥₹15k) / ₹150 (≥₹10k) slab.</div>
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

<!-- Test SMTP Connection Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary shadow-lg" style="border-radius:12px;">
            <div class="modal-header border-secondary py-3">
                <h5 class="modal-title fs-6 d-flex align-items-center gap-2" id="testEmailModalLabel">
                    <i class="fa-solid fa-paper-plane text-info"></i>
                    <span>Test SMTP Email Delivery</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary small mb-3">
                    Send a live test verification email using the currently configured SMTP parameters to ensure payslips can be sent without delivery errors.
                </p>

                <div id="test-email-alert" class="d-none alert mb-3 small" role="alert"></div>

                <div class="mb-3">
                    <label class="form-label text-secondary small font-weight-bold">Recipient Test Email *</label>
                    <input type="email" id="test_email_recipient" class="form-control bg-black bg-opacity-50 border-secondary text-white font-monospace" 
                           value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@raptor.local'); ?>" placeholder="admin@yourdomain.com" required>
                </div>

                <div class="p-3 rounded bg-black bg-opacity-25 border border-secondary small text-secondary">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Configured Host:</span>
                        <strong class="text-white font-monospace" id="lbl-test-host"><?php echo htmlspecialchars($settings['mail.host'] ?? 'Local/Empty'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Port &amp; Protocol:</span>
                        <strong class="text-white font-monospace" id="lbl-test-port"><?php echo htmlspecialchars($settings['mail.port'] ?? '587'); ?> (<?php echo strtoupper($settings['mail.encryption'] ?? 'TLS'); ?>)</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>From Address:</span>
                        <strong class="text-white font-monospace"><?php echo htmlspecialchars($settings['mail.from_address'] ?? 'accounts@axioraglobalsolutions.com'); ?></strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm px-3" id="btn-run-test-email" style="background: var(--primary); border: none;">
                    <i class="fa-solid fa-paper-plane me-1"></i> <span id="btn-test-text">Dispatch Test Email</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '_icon');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        input.type = 'password';
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}

$(document).ready(function() {
    // Dynamic updates for test modal labels when inputs change
    $('#mail_host').on('input', function() {
        $('#lbl-test-host').text($(this).val() || 'Local/Empty');
    });
    $('#mail_port, #mail_encryption').on('input change', function() {
        $('#lbl-test-port').text(($('#mail_port').val() || '587') + ' (' + ($('#mail_encryption').val() || 'TLS').toUpperCase() + ')');
    });

    // Test Email AJAX handler
    $('#btn-run-test-email').on('click', function() {
        const btn = $(this);
        const alertBox = $('#test-email-alert');
        const recipient = $('#test_email_recipient').val().trim();

        if (!recipient) {
            alertBox.removeClass('d-none alert-success').addClass('alert-danger').text('Please provide a valid recipient email.');
            return;
        }

        btn.prop('disabled', true);
        $('#btn-test-text').html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Testing SMTP...');
        alertBox.addClass('d-none');

        const postData = {
            csrf_token: $('input[name="csrf_token"]').val(),
            test_email_recipient: recipient,
            mail_driver: $('#mail_driver').val(),
            mail_host: $('#mail_host').val(),
            mail_port: $('#mail_port').val(),
            mail_username: $('#mail_username').val(),
            mail_password: $('#mail_password').val(),
            mail_encryption: $('#mail_encryption').val(),
            mail_from_address: $('#mail_from_address').val(),
            mail_from_name: $('#mail_from_name').val()
        };

        $.ajax({
            url: 'index.php?route=settings/test_email',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(resp) {
                btn.prop('disabled', false);
                $('#btn-test-text').html('<i class="fa-solid fa-paper-plane me-1"></i> Dispatch Test Email');
                if (resp && resp.success) {
                    alertBox.removeClass('d-none alert-danger').addClass('alert-success').html('<i class="fa-solid fa-circle-check me-1"></i> <strong>Success!</strong> Test email dispatched successfully to ' + recipient + '.');
                } else {
                    const err = (resp && resp.error) ? resp.error : 'Failed to connect to SMTP server.';
                    alertBox.removeClass('d-none alert-success').addClass('alert-danger').html('<i class="fa-solid fa-circle-exmark me-1"></i> <strong>SMTP Error:</strong> ' + err);
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false);
                $('#btn-test-text').html('<i class="fa-solid fa-paper-plane me-1"></i> Dispatch Test Email');
                alertBox.removeClass('d-none alert-success').addClass('alert-danger').html('<i class="fa-solid fa-triangle-exclamation me-1"></i> Request failed (HTTP ' + xhr.status + '). Check server logs.');
            }
        });
    });

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

    // 4. Universal AI Key Visibility Toggle
    $('.toggle-key-visibility').on('click', function() {
        const targetId = $(this).data('target');
        const input = $('#' + targetId);
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // 5. Universal AI Live Connection Tester
    $('.btn-test-ai').on('click', function() {
        const btn = $(this);
        const provider = btn.data('provider');
        const keyId = btn.data('key-id');
        const modelId = btn.data('model-id');
        const resultDiv = $('#' + provider + '-test-result');

        const apiKey = $('#' + keyId).val().trim();
        const model = $('#' + modelId).val();
        const csrf = $('input[name="csrf_token"]').val();

        if (!apiKey) {
            resultDiv.html('<div class="p-2 rounded bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25"><i class="fa-solid fa-triangle-exclamation me-1"></i>Please enter an API key first.</div>').slideDown();
            return;
        }

        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Pinging ' + provider + '...');
        resultDiv.html('<div class="text-secondary small"><i class="fa-solid fa-circle-notch fa-spin me-1"></i>Testing handshake...</div>').slideDown();

        $.ajax({
            url: 'index.php?route=settings/test_ai',
            type: 'POST',
            data: {
                csrf_token: csrf,
                provider: provider,
                api_key: apiKey,
                model: model
            },
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);
                if (res.success && res.data && res.data.success) {
                    resultDiv.html(
                        '<div class="p-2 rounded bg-success bg-opacity-15 text-success border border-success border-opacity-25">' +
                        '<div class="fw-bold"><i class="fa-solid fa-circle-check me-1"></i>Connected (' + res.data.latency_ms + 'ms)</div>' +
                        '<div class="small opacity-75">' + res.data.message + '</div>' +
                        '</div>'
                    );
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (res.message || 'Connection failed');
                    resultDiv.html(
                        '<div class="p-2 rounded bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25">' +
                        '<div class="fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i>Test Failed</div>' +
                        '<div class="small">' + msg + '</div>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                let errText = 'Network error contacting endpoint.';
                try {
                    const parsed = JSON.parse(xhr.responseText);
                    errText = parsed.message || (parsed.data && parsed.data.message) || errText;
                } catch(e) {}
                resultDiv.html(
                    '<div class="p-2 rounded bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25">' +
                    '<div class="fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i>Error</div>' +
                    '<div class="small">' + errText + '</div>' +
                    '</div>'
                );
            }
        });
    });
});
</script>
