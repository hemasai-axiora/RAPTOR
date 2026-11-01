<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?route=forms/index" class="text-secondary text-decoration-none small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Forms List
        </a>
        <div class="small text-secondary">
            Form UUID: <code class="text-info"><?= htmlspecialchars($form->form_uuid); ?></code>
        </div>
    </div>

    <!-- Header Stats Card -->
    <div class="pulse-card mb-4 p-4 border-start border-4 border-info">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="text-white mb-1"><?= htmlspecialchars($form->name); ?></h3>
                <div class="text-secondary small">
                    Type: <span class="badge bg-secondary"><?= strtoupper($form->form_type); ?></span>
                    <span class="mx-2">•</span>
                    Source: <code><?= htmlspecialchars($form->default_lead_source); ?></code>
                    <span class="mx-2">•</span>
                    Created: <?= date('M d, Y', strtotime($form->created_at)); ?>
                </div>
            </div>
            <div class="d-flex gap-3 text-center">
                <div class="p-2 px-3 bg-dark rounded border border-secondary">
                    <div class="small text-secondary">Views</div>
                    <div class="fs-4 fw-bold text-white"><?= number_format((int)$form->views_count); ?></div>
                </div>
                <div class="p-2 px-3 bg-dark rounded border border-secondary">
                    <div class="small text-secondary">Submissions</div>
                    <div class="fs-4 fw-bold text-primary"><?= number_format((int)$form->submissions_count); ?></div>
                </div>
                <div class="p-2 px-3 bg-dark rounded border border-secondary">
                    <div class="small text-secondary">Conversion Rate</div>
                    <?php
                    $cr = ((int)$form->views_count > 0) ? round(((int)$form->submissions_count * 100.0) / (int)$form->views_count, 1) : 0;
                    ?>
                    <div class="fs-4 fw-bold text-success"><?= $cr; ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Embed Code Snippets -->
    <div class="card bg-dark border-secondary p-4 mb-4" style="border-radius: 10px;">
        <h5 class="text-white mb-2"><i class="fa-solid fa-code me-2 text-warning"></i>Embed This Form On Your Website</h5>
        <div class="text-secondary small mb-3">Copy and paste either the iframe or the lightweight JavaScript snippet into your CMS or landing page.</div>

        <ul class="nav nav-pills mb-3" id="embedTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active bg-secondary bg-opacity-25 text-white btn-sm me-2" id="iframe-tab" data-bs-toggle="pill" data-bs-target="#iframe-content" type="button" role="tab">iFrame Embed</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link bg-secondary bg-opacity-25 text-white btn-sm" id="js-tab" data-bs-toggle="pill" data-bs-target="#js-content" type="button" role="tab">JavaScript Widget</button>
            </li>
        </ul>

        <div class="tab-content" id="embedTabsContent">
            <div class="tab-pane fade show active" id="iframe-content" role="tabpanel">
                <div class="position-relative">
                    <textarea class="form-control bg-black text-info font-monospace border-secondary small" rows="3" readonly><?= htmlspecialchars($iframe_snippet); ?></textarea>
                </div>
            </div>
            <div class="tab-pane fade" id="js-content" role="tabpanel">
                <div class="position-relative">
                    <textarea class="form-control bg-black text-info font-monospace border-secondary small" rows="3" readonly><?= htmlspecialchars($js_snippet); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="card bg-dark border-secondary p-4" style="border-radius: 10px;">
        <h5 class="text-white mb-3"><i class="fa-solid fa-inbox me-2 text-primary"></i>Inbound Submissions Log</h5>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
                <thead class="table-dark" style="border-bottom: 2px solid #333;">
                    <tr class="text-secondary small">
                        <th>DATE & TIME</th>
                        <th>CONTACT PROFILE</th>
                        <th>CRM LEAD</th>
                        <th>UTM ATTRIBUTION</th>
                        <th>IP & VISITOR</th>
                        <th>SUBMITTED DATA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-envelope-open-text fa-2x mb-2 d-block"></i>
                                No submissions recorded yet for this form.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $s): ?>
                            <tr>
                                <td class="small text-secondary font-monospace">
                                    <?= date('M d, Y H:i', strtotime($s->created_at)); ?>
                                </td>
                                <td>
                                    <?php if ($s->contact_id): ?>
                                        <a href="index.php?route=contacts/view/<?= $s->contact_id; ?>" class="text-info fw-bold text-decoration-none">
                                            <?= htmlspecialchars(trim(($s->contact_first_name ?? '') . ' (' . ($s->primary_email ?? '') . ')')); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s->lead_id): ?>
                                        <a href="index.php?route=leads/view/<?= $s->lead_id; ?>" class="badge bg-primary text-decoration-none">
                                            <?= htmlspecialchars($s->lead_code ?? ('LEAD-' . $s->lead_id)); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($s->utm_source || $s->utm_campaign): ?>
                                        <span class="badge bg-secondary text-info"><?= htmlspecialchars($s->utm_source); ?></span>
                                        <span class="badge bg-dark border border-secondary text-secondary"><?= htmlspecialchars($s->utm_campaign); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Direct / Organic</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary font-monospace">
                                    <?= htmlspecialchars($s->ip_address ?: '127.0.0.1'); ?>
                                </td>
                                <td>
                                    <button class="btn btn-outline-secondary btn-sm py-0 px-2 small" data-bs-toggle="collapse" data-bs-target="#subData<?= $s->submission_id; ?>">
                                        View Payload
                                    </button>
                                    <div class="collapse mt-2" id="subData<?= $s->submission_id; ?>">
                                        <pre class="bg-black text-info p-2 rounded small mb-0" style="max-width: 300px; font-size: 0.7rem;"><?= htmlspecialchars($s->submitted_data_json); ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
