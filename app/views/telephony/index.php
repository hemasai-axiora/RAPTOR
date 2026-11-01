<div class="pulse-card mb-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-phone-volume me-2 text-info"></i>Cloud Telephony & Call Logs</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Browser-based click-to-call, automated call recording ingestion, disposition outcomes, and TRAI DND regulatory compliance.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=telephony/dnd" class="btn btn-outline-warning btn-sm px-3 py-2">
                <i class="fa-solid fa-shield-halved me-1"></i> TRAI DND Registry
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#clickToCallModal">
                <i class="fa-solid fa-phone me-1"></i> Click-to-Call
            </button>
        </div>
    </div>

    <!-- Legal Calling Hours Banner -->
    <?php if (!$is_calling_hours): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
            <div>
                <strong>Calling Hours Gate Restricted:</strong> Outbound telemarketing calls are legally restricted outside 09:00 AM – 08:00 PM local time per TRAI / TCPA regulations. Promotional calls will be blocked.
            </div>
        </div>
    <?php endif; ?>

    <!-- Telephony Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Total Calls Dialed</div>
                <div class="fs-3 fw-bold text-white"><?= number_format($analytics['total_calls'] ?? 0); ?></div>
                <div class="text-muted small mt-1">Inbound & Outbound</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Answer Rate</div>
                <div class="fs-3 fw-bold text-success"><?= $analytics['answer_rate'] ?? 0; ?>%</div>
                <div class="text-muted small mt-1"><?= number_format($analytics['completed_calls'] ?? 0); ?> calls connected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Total Talk Time</div>
                <div class="fs-3 fw-bold text-info"><?= number_format($analytics['total_talk_minutes'] ?? 0, 1); ?> <span class="fs-6 text-muted">mins</span></div>
                <div class="text-muted small mt-1">Avg: <?= round($analytics['avg_duration_seconds'] ?? 0); ?>s per call</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Top Disposition</div>
                <?php
                $topDisp = !empty($analytics['dispositions'][0]) ? $analytics['dispositions'][0]->disposition_code : 'None yet';
                ?>
                <div class="fs-3 fw-bold text-warning text-uppercase font-monospace" style="font-size: 1.25rem !important;"><?= htmlspecialchars($topDisp); ?></div>
                <div class="text-muted small mt-1">Outcome distribution</div>
            </div>
        </div>
    </div>

    <!-- Calls Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>AGENT</th>
                    <th>CUSTOMER / CONTACT</th>
                    <th>DIRECTION</th>
                    <th>STATUS</th>
                    <th>DURATION</th>
                    <th>OUTCOME / DISPOSITION</th>
                    <th>RECORDING</th>
                    <th>DATE & TIME</th>
                    <th class="text-end">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($calls)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-headset fa-3x mb-3 d-block opacity-50"></i>
                            No call activity recorded yet. Click "Click-to-Call" to initiate outbound outreach.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($calls as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-white"><?= htmlspecialchars($c->agent_name ?? 'System'); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($c->first_name)): ?>
                                    <div class="text-white fw-bold"><?= htmlspecialchars($c->first_name . ' ' . ($c->last_name ?? '')); ?></div>
                                <?php endif; ?>
                                <div class="font-monospace text-secondary small"><?= htmlspecialchars($c->to_number); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $c->direction === 'inbound' ? 'info' : 'primary'; ?>-subtle text-<?= $c->direction === 'inbound' ? 'info' : 'primary'; ?> border border-<?= $c->direction === 'inbound' ? 'info' : 'primary'; ?> text-uppercase" style="font-size: 0.7rem;">
                                    <?= htmlspecialchars($c->direction); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'completed' => 'success',
                                    'in_progress' => 'info',
                                    'ringing' => 'warning',
                                    'busy' => 'danger',
                                    'no_answer' => 'secondary',
                                    'failed' => 'danger'
                                ];
                                $sBadge = $statusBadges[$c->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sBadge; ?>-subtle text-<?= $sBadge; ?> border border-<?= $sBadge; ?> text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($c->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $mins = floor($c->duration_seconds / 60);
                                $secs = $c->duration_seconds % 60;
                                ?>
                                <span class="font-monospace text-white small"><?= sprintf('%02d:%02d', $mins, $secs); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($c->disposition_code)): ?>
                                    <span class="badge bg-dark border border-secondary text-light font-monospace text-uppercase" style="font-size: 0.72rem;">
                                        <?= htmlspecialchars($c->disposition_code); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Untagged</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c->recording_url)): ?>
                                    <a href="<?= htmlspecialchars($c->recording_url); ?>" target="_blank" class="btn btn-outline-info btn-sm px-2 py-0" title="Listen to Call Recording">
                                        <i class="fa-solid fa-play me-1"></i> Audio
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-secondary small"><?= date('M d, H:i', strtotime($c->started_at)); ?></div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-secondary btn-sm px-2 py-1 tag-disposition-btn" 
                                        data-call-id="<?= $c->call_id; ?>" 
                                        data-code="<?= htmlspecialchars($c->disposition_code ?? ''); ?>"
                                        data-notes="<?= htmlspecialchars($c->disposition_notes ?? ''); ?>"
                                        data-bs-toggle="modal" data-bs-target="#dispositionModal" title="Tag Outcome">
                                    <i class="fa-solid fa-tag"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Click to Call -->
<div class="modal fade" id="clickToCallModal" tabindex="-1" aria-labelledby="clickToCallModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?route=telephony/initiateCall" method="POST" class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="clickToCallModalLabel"><i class="fa-solid fa-phone text-info me-2"></i>Initiate Click-to-Call</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Customer Destination Phone <span class="text-danger">*</span></label>
                    <input type="text" name="customer_phone" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="+919876543210" required>
                    <div class="form-text text-muted">Automatically scrubbed against TRAI DND registry prior to dialing.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Agent Phone / Extension</label>
                    <input type="text" name="agent_phone" class="form-control bg-dark text-white border-secondary font-monospace" value="+18005550199">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="bypass_hours" value="1" id="bypassHoursCheck">
                    <label class="form-check-label small text-secondary" for="bypassHoursCheck">
                        Bypass Calling Hours Gate (Transaction / Critical Support Call only)
                    </label>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-phone me-1"></i> Dial Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Tag Disposition -->
<div class="modal fade" id="dispositionModal" tabindex="-1" aria-labelledby="dispositionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?route=telephony/saveDisposition" method="POST" class="modal-content bg-dark text-white border-secondary">
            <input type="hidden" name="call_id" id="dispCallId">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="dispositionModalLabel"><i class="fa-solid fa-tag text-warning me-2"></i>Tag Call Outcome & Disposition</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Disposition Outcome <span class="text-danger">*</span></label>
                    <select name="disposition_code" id="dispCodeSelect" class="form-select bg-dark text-white border-secondary" required>
                        <option value="interested">Interested - Follow-up Demo</option>
                        <option value="callback_requested">Callback Requested</option>
                        <option value="not_interested">Not Interested / No Requirement</option>
                        <option value="invalid_number">Invalid or Disconnected Number</option>
                        <option value="left_voicemail">Left Voicemail</option>
                        <option value="wrong_contact">Wrong Person / Contact</option>
                        <option value="connected_closed">Deal Closed / Agreed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Call Notes</label>
                    <textarea name="disposition_notes" id="dispNotesText" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Customer requirements, budget details, objections raised..."></textarea>
                </div>
                <div class="mb-3" id="callbackDateField" style="display: none;">
                    <label class="form-label small text-secondary">Scheduled Callback Date & Time</label>
                    <input type="datetime-local" name="callback_due_at" class="form-control bg-dark text-white border-secondary">
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fa-solid fa-floppy-disk me-1"></i> Save Disposition</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dispSelect = document.getElementById('dispCodeSelect');
    const callbackField = document.getElementById('callbackDateField');

    function checkCallback() {
        if (dispSelect.value === 'callback_requested') {
            callbackField.style.display = 'block';
        } else {
            callbackField.style.display = 'none';
        }
    }

    dispSelect.addEventListener('change', checkCallback);

    document.querySelectorAll('.tag-disposition-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('dispCallId').value = this.getAttribute('data-call-id');
            const code = this.getAttribute('data-code');
            const notes = this.getAttribute('data-notes');
            if (code) dispSelect.value = code;
            if (notes) document.getElementById('dispNotesText').value = notes;
            checkCallback();
        });
    });
});
</script>

