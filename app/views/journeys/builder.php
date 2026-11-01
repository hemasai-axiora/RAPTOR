<div class="pulse-card mb-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="index.php?route=journeys/index" class="text-secondary text-decoration-none small">
                    <i class="fa-solid fa-arrow-left me-1"></i> Journeys
                </a>
                <span class="text-muted">/</span>
                <span class="text-white fw-bold"><?= htmlspecialchars($journey->name); ?></span>
            </div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-diagram-project text-primary me-2"></i>Automation Sequence Builder</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php
            $statusBadges = ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning'];
            $sBadge = $statusBadges[$journey->status] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $sBadge; ?>-subtle text-<?= $sBadge; ?> border border-<?= $sBadge; ?> text-uppercase px-3 py-2">
                Status: <?= htmlspecialchars($journey->status); ?>
            </span>

            <form action="index.php?route=journeys/toggleStatus" method="POST" class="d-inline">
                <input type="hidden" name="journey_id" value="<?= $journey->journey_id; ?>">
                <?php if ($journey->status === 'active'): ?>
                    <input type="hidden" name="status" value="paused">
                    <button type="submit" class="btn btn-warning btn-sm px-3 py-2">
                        <i class="fa-solid fa-pause me-1"></i> Pause Workflow
                    </button>
                <?php else: ?>
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-success btn-sm px-3 py-2">
                        <i class="fa-solid fa-play me-1"></i> Activate Workflow
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Workflow Canvas -->
    <div class="row g-4">
        <div class="col-lg-8">
            <form action="index.php?route=journeys/saveSteps" method="POST" id="journeyStepsForm">
                <input type="hidden" name="journey_id" value="<?= $journey->journey_id; ?>">

                <!-- Trigger Card (Step 0) -->
                <div class="card bg-dark border-primary mb-3">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary bg-opacity-25 p-3 text-primary">
                            <i class="fa-solid fa-bolt fa-xl"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="badge bg-primary text-white font-monospace text-uppercase" style="font-size: 0.7rem;">Trigger Event</span>
                            <h5 class="text-white mb-0 mt-1"><?= htmlspecialchars($triggers[$journey->trigger_type] ?? $journey->trigger_type); ?></h5>
                            <div class="text-secondary small">Automatically enrolls contact when this event fires.</div>
                        </div>
                    </div>
                </div>

                <!-- Sequence Steps Container -->
                <div id="stepsContainer">
                    <?php if (empty($steps)): ?>
                        <div class="text-center py-4 text-muted border border-secondary border-dashed rounded mb-3" id="emptyNotice">
                            <i class="fa-solid fa-plus-circle fa-2x mb-2 d-block opacity-50"></i>
                            No sequence steps added yet. Use the action palette on the right to add steps.
                        </div>
                    <?php else: ?>
                        <?php foreach ($steps as $idx => $s): ?>
                            <?php
                            $cfg = !empty($s->action_config_json) ? json_decode($s->action_config_json, true) : [];
                            $i = $idx;
                            ?>
                            <div class="step-item mb-3" data-step-index="<?= $i; ?>">
                                <div class="text-center my-2 text-secondary opacity-50">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </div>
                                <div class="card bg-dark border-secondary border-opacity-50">
                                    <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="step-icon-box rounded p-2 text-center" style="width: 44px; height: 44px;">
                                                <?php if ($s->action_type === 'send_email'): ?>
                                                    <i class="fa-solid fa-envelope text-info fa-lg mt-2"></i>
                                                <?php elseif ($s->action_type === 'send_whatsapp'): ?>
                                                    <i class="fa-brands fa-whatsapp text-success fa-lg mt-2"></i>
                                                <?php elseif ($s->action_type === 'wait_delay'): ?>
                                                    <i class="fa-solid fa-hourglass-half text-warning fa-lg mt-2"></i>
                                                <?php elseif ($s->action_type === 'adjust_lead_score'): ?>
                                                    <i class="fa-solid fa-star text-warning fa-lg mt-2"></i>
                                                <?php elseif ($s->action_type === 'update_stage'): ?>
                                                    <i class="fa-solid fa-layer-group text-primary fa-lg mt-2"></i>
                                                <?php else: ?>
                                                    <i class="fa-solid fa-user-check text-light fa-lg mt-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;">Step <?= $s->step_order; ?></span>
                                                    <span class="text-white fw-bold"><?= htmlspecialchars($action_types[$s->action_type] ?? $s->action_type); ?></span>
                                                </div>
                                                <div class="text-secondary small mt-1 font-monospace">
                                                    <?php
                                                    if ($s->action_type === 'wait_delay') {
                                                        echo "Delay: " . ($cfg['delay_seconds'] ?? 3600) . " seconds";
                                                    } elseif ($s->action_type === 'adjust_lead_score') {
                                                        echo "Score adjustment: " . ($cfg['score_delta'] ?? 10) . " points";
                                                    } elseif ($s->action_type === 'update_stage') {
                                                        echo "Move stage to: " . strtoupper($cfg['lifecycle_stage'] ?? 'MQL');
                                                    } elseif ($s->action_type === 'send_whatsapp') {
                                                        echo "Template: " . ($cfg['template_name'] ?? 'welcome_onboarding');
                                                    } elseif ($s->action_type === 'send_email') {
                                                        echo "Subject: " . ($cfg['subject'] ?? 'Welcome email');
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <!-- Hidden Inputs for Form Submission -->
                                            <input type="hidden" name="steps[<?= $i; ?>][action_type]" value="<?= htmlspecialchars($s->action_type); ?>">
                                            <?php foreach ($cfg as $k => $v): ?>
                                                <input type="hidden" name="steps[<?= $i; ?>][config][<?= htmlspecialchars($k); ?>]" value="<?= htmlspecialchars((string)$v); ?>">
                                            <?php endforeach; ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-step-btn" title="Remove step">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Journey Steps
                    </button>
                </div>
            </form>
        </div>

        <!-- Action Step Palette -->
        <div class="col-lg-4">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 sticky-top" style="top: 20px;">
                <h6 class="text-white mb-3"><i class="fa-solid fa-toolbox me-2 text-info"></i>Action Palette</h6>
                <div class="text-secondary small mb-3">Click an action below to append it to the journey sequence:</div>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info text-start p-2.5 add-step-action" data-action="send_email" data-label="Send Marketing Email">
                        <i class="fa-solid fa-envelope me-2"></i> <strong>Send Marketing Email</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Dispatches personalized email template</div>
                    </button>

                    <button type="button" class="btn btn-outline-success text-start p-2.5 add-step-action" data-action="send_whatsapp" data-label="Send WhatsApp Template">
                        <i class="fa-brands fa-whatsapp me-2"></i> <strong>Send WhatsApp Template</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Dispatches pre-approved Meta WhatsApp template</div>
                    </button>

                    <button type="button" class="btn btn-outline-warning text-start p-2.5 add-step-action" data-action="wait_delay" data-label="Wait Time Delay">
                        <i class="fa-solid fa-hourglass-half me-2"></i> <strong>Wait Time Delay</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Pauses execution (e.g. 1 hour, 1 day)</div>
                    </button>

                    <button type="button" class="btn btn-outline-warning text-start p-2.5 add-step-action" data-action="adjust_lead_score" data-label="Adjust Lead Score">
                        <i class="fa-solid fa-star me-2"></i> <strong>Adjust Lead Score (+/-)</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Dynamically updates score & quality tier</div>
                    </button>

                    <button type="button" class="btn btn-outline-primary text-start p-2.5 add-step-action" data-action="update_stage" data-label="Update Lifecycle Stage">
                        <i class="fa-solid fa-layer-group me-2"></i> <strong>Update Lifecycle Stage</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Advances stage to MQL, SQL, Opportunity</div>
                    </button>

                    <button type="button" class="btn btn-outline-light text-start p-2.5 add-step-action" data-action="assign_rep" data-label="Assign Lead to Rep">
                        <i class="fa-solid fa-user-check me-2"></i> <strong>Assign Lead to Rep</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">Assigns contact to dedicated sales rep</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let stepCount = <?= count($steps); ?>;

    const actionIcons = {
        'send_email': 'fa-solid fa-envelope text-info',
        'send_whatsapp': 'fa-brands fa-whatsapp text-success',
        'wait_delay': 'fa-solid fa-hourglass-half text-warning',
        'adjust_lead_score': 'fa-solid fa-star text-warning',
        'update_stage': 'fa-solid fa-layer-group text-primary',
        'assign_rep': 'fa-solid fa-user-check text-light'
    };

    document.querySelectorAll('.add-step-action').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const label = this.getAttribute('data-label');
            const emptyNotice = document.getElementById('emptyNotice');
            if (emptyNotice) emptyNotice.remove();

            const idx = stepCount++;
            const stepOrder = idx + 1;
            const iconClass = actionIcons[action] || 'fa-solid fa-gear text-secondary';

            let configInputs = '';
            let configSummary = '';

            if (action === 'wait_delay') {
                const secs = prompt("Enter wait delay in seconds (e.g. 3600 for 1 hr, 86400 for 1 day):", "3600");
                if (secs === null) return;
                configInputs = `<input type="hidden" name="steps[${idx}][config][delay_seconds]" value="${secs}">`;
                configSummary = `Delay: ${secs} seconds`;
            } else if (action === 'adjust_lead_score') {
                const delta = prompt("Enter score delta to award (e.g. 15 or -10):", "15");
                if (delta === null) return;
                configInputs = `<input type="hidden" name="steps[${idx}][config][score_delta]" value="${delta}">`;
                configSummary = `Score adjustment: ${delta} points`;
            } else if (action === 'update_stage') {
                const stage = prompt("Enter target lifecycle stage (subscriber, lead, mql, sql, customer):", "mql");
                if (stage === null) return;
                configInputs = `<input type="hidden" name="steps[${idx}][config][lifecycle_stage]" value="${stage}">`;
                configSummary = `Move stage to: ${stage.toUpperCase()}`;
            } else if (action === 'send_whatsapp') {
                const tmpl = prompt("Enter WhatsApp template name:", "welcome_onboarding");
                if (tmpl === null) return;
                configInputs = `<input type="hidden" name="steps[${idx}][config][template_name]" value="${tmpl}">`;
                configSummary = `Template: ${tmpl}`;
            } else if (action === 'send_email') {
                const subj = prompt("Enter email subject:", "Welcome to Raptor Marketing!");
                if (subj === null) return;
                configInputs = `<input type="hidden" name="steps[${idx}][config][subject]" value="${subj}">`;
                configSummary = `Subject: ${subj}`;
            } else if (action === 'assign_rep') {
                configInputs = `<input type="hidden" name="steps[${idx}][config][user_id]" value="1">`;
                configSummary = `Assigned to primary sales rep`;
            }

            const html = `
                <div class="step-item mb-3" data-step-index="${idx}">
                    <div class="text-center my-2 text-secondary opacity-50">
                        <i class="fa-solid fa-arrow-down"></i>
                    </div>
                    <div class="card bg-dark border-secondary border-opacity-50">
                        <div class="card-body d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-icon-box rounded p-2 text-center" style="width: 44px; height: 44px;">
                                    <i class="${iconClass} fa-lg mt-2"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;">Step ${stepOrder}</span>
                                        <span class="text-white fw-bold">${label}</span>
                                    </div>
                                    <div class="text-secondary small mt-1 font-monospace">${configSummary}</div>
                                </div>
                            </div>
                            <div>
                                <input type="hidden" name="steps[${idx}][action_type]" value="${action}">
                                ${configInputs}
                                <button type="button" class="btn btn-outline-danger btn-sm remove-step-btn" title="Remove step">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('stepsContainer').insertAdjacentHTML('beforeend', html);
        });
    });

    // Remove step
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-step-btn')) {
            e.target.closest('.step-item').remove();
        }
    });
});
</script>

