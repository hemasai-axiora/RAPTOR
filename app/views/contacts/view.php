<div class="mb-4">
    <!-- Breadcrumb & Nav -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?route=contacts/index" class="text-secondary text-decoration-none small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Customer 360 Directory
        </a>
        <div class="small text-secondary">
            UUID: <code class="text-info"><?= htmlspecialchars($contact->contact_uuid); ?></code>
        </div>
    </div>

    <!-- Header Card -->
    <div class="pulse-card mb-4 p-4 border-start border-4 border-primary">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-3" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <h3 class="text-white mb-1">
                        <?= htmlspecialchars(trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: 'Anonymous Contact'); ?>
                    </h3>
                    <div class="text-secondary">
                        <i class="fa-solid fa-building me-1"></i><?= htmlspecialchars($contact->company_name ?: 'Individual'); ?>
                        <?php if (!empty($contact->source)): ?>
                            <span class="mx-2">•</span>
                            <i class="fa-solid fa-compass me-1"></i>Source: <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $contact->source))); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Badges -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Stage -->
                <?php
                $stageColors = [
                    'subscriber' => 'secondary',
                    'lead' => 'info',
                    'mql' => 'primary',
                    'sql' => 'warning',
                    'opportunity' => 'primary',
                    'customer' => 'success',
                    'evangelist' => 'success',
                    'churned' => 'danger'
                ];
                $stColor = $stageColors[$contact->lifecycle_stage] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $stColor; ?> fs-6 px-3 py-2">
                    <i class="fa-solid fa-diagram-project me-1"></i><?= strtoupper($contact->lifecycle_stage); ?>
                </span>

                <!-- Score -->
                <span class="badge bg-dark border border-warning text-warning fs-6 px-3 py-2">
                    <i class="fa-solid fa-bolt me-1"></i>Score: <?= (int)$contact->lead_score; ?> (<?= ucfirst($contact->lead_quality); ?>)
                </span>

                <!-- Suppression -->
                <?php if ((int)$contact->is_suppressed === 1): ?>
                    <span class="badge bg-danger fs-6 px-3 py-2" title="Reason: <?= htmlspecialchars($contact->suppression_reason ?? 'Opt-out'); ?>">
                        <i class="fa-solid fa-ban me-1"></i>SUPPRESSED
                    </span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success border border-success fs-6 px-3 py-2">
                        <i class="fa-solid fa-circle-check me-1"></i>ACTIVE
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Left Column: Identities, CRM Links, Controls -->
        <div class="col-lg-4">
            <!-- Channel Identities Card -->
            <div class="card bg-dark border-secondary mb-4 p-3" style="border-radius: 10px;">
                <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                    <i class="fa-solid fa-fingerprint me-2 text-info"></i>Channel Identities
                </h6>
                <div class="d-flex flex-column gap-2">
                    <?php if (empty($identities)): ?>
                        <div class="text-muted small">No verified channel identities registered.</div>
                    <?php else: ?>
                        <?php foreach ($identities as $id): ?>
                            <div class="d-flex justify-content-between align-items-center p-2 bg-black bg-opacity-40 rounded border border-secondary">
                                <div>
                                    <span class="badge bg-secondary text-uppercase small me-1">
                                        <?= htmlspecialchars($id->channel); ?>
                                    </span>
                                    <span class="text-white small fw-bold font-monospace">
                                        <?= htmlspecialchars($id->identity_value); ?>
                                    </span>
                                </div>
                                <?php if ((int)$id->is_primary === 1): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary small" style="font-size: 0.7rem;">Primary</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Associated CRM Records -->
            <div class="card bg-dark border-secondary mb-4 p-3" style="border-radius: 10px;">
                <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                    <i class="fa-solid fa-link me-2 text-warning"></i>Linked CRM Records
                </h6>
                <!-- Lead Link -->
                <div class="mb-3">
                    <div class="small text-secondary mb-1">CRM Sales Lead</div>
                    <?php if ($lead): ?>
                        <div class="p-2 bg-black bg-opacity-40 rounded border border-secondary d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-info fw-bold small"><?= htmlspecialchars($lead->lead_code); ?></span>
                                <span class="badge bg-secondary ms-1 small"><?= ucfirst($lead->status); ?></span>
                            </div>
                            <a href="index.php?route=leads/view/<?= $lead->lead_id; ?>" class="btn btn-outline-info btn-sm py-0 px-2" style="font-size: 0.75rem;">View Lead</a>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small">No direct CRM lead attached.</div>
                    <?php endif; ?>
                </div>

                <!-- Customer Link -->
                <div>
                    <div class="small text-secondary mb-1">Customer Account</div>
                    <?php if ($customer): ?>
                        <div class="p-2 bg-black bg-opacity-40 rounded border border-secondary d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-success fw-bold small"><?= htmlspecialchars($customer->customer_code); ?></span>
                                <span class="badge bg-success ms-1 small"><?= htmlspecialchars($customer->status); ?></span>
                            </div>
                            <a href="index.php?route=customers/view/<?= $customer->customer_id; ?>" class="btn btn-outline-success btn-sm py-0 px-2" style="font-size: 0.75rem;">View Account</a>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small">Not currently registered as a paying customer.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Governance Controls -->
            <div class="card bg-dark border-secondary p-3 mb-4" style="border-radius: 10px;">
                <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                    <i class="fa-solid fa-sliders me-2 text-primary"></i>Profile Actions
                </h6>

                <!-- Stage Change Form -->
                <form method="POST" action="index.php?route=contacts/stage" class="mb-3">
                    <input type="hidden" name="contact_id" value="<?= $contact->contact_id; ?>">
                    <label class="form-label text-secondary small mb-1">Transition Lifecycle Stage</label>
                    <div class="input-group input-group-sm">
                        <select name="lifecycle_stage" class="form-select bg-dark text-white border-secondary">
                            <?php foreach ($stages as $st): ?>
                                <option value="<?= $st; ?>" <?= ($contact->lifecycle_stage === $st) ? 'selected' : ''; ?>><?= ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>

                <!-- Lead Score Adjustment -->
                <form method="POST" action="index.php?route=contacts/score" class="mb-3">
                    <input type="hidden" name="contact_id" value="<?= $contact->contact_id; ?>">
                    <label class="form-label text-secondary small mb-1">Adjust Lead Score</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="delta" class="form-control bg-dark text-white border-secondary" placeholder="+10 or -5" required>
                        <input type="text" name="reason" class="form-control bg-dark text-white border-secondary" placeholder="Reason..." required>
                        <button type="submit" class="btn btn-warning">Score</button>
                    </div>
                </form>

                <!-- Suppression Toggle -->
                <form method="POST" action="index.php?route=contacts/suppress">
                    <input type="hidden" name="contact_id" value="<?= $contact->contact_id; ?>">
                    <?php if ((int)$contact->is_suppressed === 1): ?>
                        <input type="hidden" name="action" value="unsuppress">
                        <button type="submit" class="btn btn-outline-success btn-sm w-100">
                            <i class="fa-solid fa-unlock me-1"></i>Lift Suppression & Allow Outreach
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="suppress">
                        <div class="input-group input-group-sm">
                            <input type="text" name="reason" class="form-control bg-dark text-white border-secondary" placeholder="Opt-out reason..." value="Manual Administrator Opt-out">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-ban me-1"></i>Suppress
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Right Column: Universal Omnichannel Activity Timeline -->
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary p-4" style="border-radius: 10px;">
                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-3 mb-4">
                    <div>
                        <h5 class="text-white mb-1"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i>Customer 360 Activity Timeline</h5>
                        <div class="text-secondary small">Unified audit trail across Web, Email, WhatsApp, Calls, and CRM touchpoints.</div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addNoteCollapse">
                        <i class="fa-solid fa-plus me-1"></i>Add Activity Note
                    </button>
                </div>

                <!-- Add Note Form Collapse -->
                <div class="collapse mb-4" id="addNoteCollapse">
                    <div class="p-3 bg-black bg-opacity-50 rounded border border-secondary">
                        <form method="POST" action="index.php?route=contacts/addNote">
                            <input type="hidden" name="contact_id" value="<?= $contact->contact_id; ?>">
                            <div class="row g-2 mb-2">
                                <div class="col-md-8">
                                    <input type="text" name="title" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Activity Title (e.g., Follow-up Call Notes)" required>
                                </div>
                                <div class="col-md-4">
                                    <select name="channel" class="form-select form-select-sm bg-dark text-white border-secondary">
                                        <option value="crm">CRM General</option>
                                        <option value="call">Phone Call</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="email">Email</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <textarea name="description" class="form-control form-control-sm bg-dark text-white border-secondary" rows="2" placeholder="Enter detailed note..." required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm px-3">Record Activity</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Timeline Feed -->
                <?php if (empty($activities)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-timeline fa-2x mb-2 d-block"></i>
                        No activities logged yet for this contact profile.
                    </div>
                <?php else: ?>
                    <div class="timeline position-relative ps-4" style="border-left: 2px solid #333;">
                        <?php foreach ($activities as $act): ?>
                            <?php
                            $channelIcons = [
                                'web' => ['icon' => 'globe', 'color' => 'info'],
                                'form' => ['icon' => 'file-signature', 'color' => 'success'],
                                'email' => ['icon' => 'envelope', 'color' => 'primary'],
                                'whatsapp' => ['icon' => 'brands fa-whatsapp', 'color' => 'success'],
                                'call' => ['icon' => 'phone', 'color' => 'warning'],
                                'sms' => ['icon' => 'comment-dots', 'color' => 'info'],
                                'crm' => ['icon' => 'user-gear', 'color' => 'secondary'],
                                'invoice' => ['icon' => 'file-invoice-dollar', 'color' => 'warning'],
                            ];
                            $meta = $channelIcons[$act->channel] ?? ['icon' => 'circle', 'color' => 'secondary'];
                            ?>
                            <div class="timeline-item mb-4 position-relative">
                                <!-- Marker -->
                                <div class="position-absolute rounded-circle bg-<?= $meta['color']; ?> d-flex align-items-center justify-content-center text-white" 
                                     style="width: 24px; height: 24px; left: -37px; top: 0px; font-size: 0.7rem;">
                                    <i class="fa-solid fa-<?= $meta['icon']; ?>"></i>
                                </div>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <span class="text-white fw-bold small"><?= htmlspecialchars($act->title); ?></span>
                                        <span class="badge bg-secondary ms-1 text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($act->channel); ?></span>
                                    </div>
                                    <span class="text-secondary small font-monospace" style="font-size: 0.75rem;">
                                        <?= date('M d, Y H:i', strtotime($act->occurred_at)); ?>
                                    </span>
                                </div>
                                <?php if (!empty($act->description)): ?>
                                    <div class="text-secondary small mb-1">
                                        <?= nl2br(htmlspecialchars($act->description)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($act->actor_name)): ?>
                                    <div class="text-muted small" style="font-size: 0.7rem;">
                                        <i class="fa-solid fa-user-pen me-1"></i>Logged by: <?= htmlspecialchars($act->actor_name); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
