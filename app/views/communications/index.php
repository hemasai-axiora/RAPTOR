<?php if (!empty($_SESSION['communication_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['communication_error']); unset($_SESSION['communication_error']); ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['communication_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['communication_success']); unset($_SESSION['communication_success']); ?>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-comments text-primary me-2"></i>Communications Log</h4>
            <div class="text-secondary" style="font-size:0.9rem;">Log, bulk upload, and track WhatsApp, SMS, Calls, Email, and Meeting touchpoints.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?route=communications/exportCsv&<?php echo http_build_query($_GET); ?>" class="btn btn-outline-success btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i>Export CSV
            </a>
            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                <i class="fa-solid fa-file-import me-1"></i>Bulk Upload (WhatsApp/SMS/Calls)
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommunicationModal" style="background: var(--primary); border: none;">
                <i class="fa-solid fa-phone-volume me-1"></i>Log Communication
            </button>
        </div>
    </div>

    <!-- Enhanced Filter Form -->
    <form method="GET" action="index.php" class="row g-2 align-items-center mb-4">
        <input type="hidden" name="route" value="communications/index">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-2">
                <select name="user_id" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                    <option value="">👤 All Team Members</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) ($filters['user_id'] ?? '') === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <select name="channel" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Channels (WhatsApp/SMS/Call)</option>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?php echo $channel; ?>" <?php echo ($filters['channel'] ?? '') === $channel ? 'selected' : ''; ?>>
                        <?php 
                            $icon = 'fa-comment';
                            if ($channel === 'whatsapp') $icon = 'fa-whatsapp text-success';
                            elseif ($channel === 'sms') $icon = 'fa-message text-warning';
                            elseif ($channel === 'call') $icon = 'fa-phone text-info';
                            elseif ($channel === 'email') $icon = 'fa-envelope text-primary';
                        ?>
                        <?php echo strtoupper($channel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="direction" class="form-select bg-dark border-secondary text-white" onchange="this.form.submit()">
                <option value="">All Directions</option>
                <?php foreach ($directions as $direction): ?>
                    <option value="<?php echo $direction; ?>" <?php echo ($filters['direction'] ?? '') === $direction ? 'selected' : ''; ?>><?php echo strtoupper($direction); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="text" name="search" class="form-control bg-dark border-secondary text-white" placeholder="Search lead, phone, note..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_from'] ?? '', 0, 10)); ?>">
        </div>
        <div class="col-md-2">
            <div class="input-group">
                <input type="date" name="date_to" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_to'] ?? '', 0, 10)); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary table-stack">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Lead Details</th>
                    <th>Logged By</th>
                    <th>Channel</th>
                    <th>Direction</th>
                    <th>Outcome & Notes</th>
                    <th>Happened At</th>
                    <th class="text-end">Proof / Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($communications)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-secondary">No communication records found for the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($communications as $item): ?>
                    <?php 
                        $displayPhone = '';
                        if (!empty($item->lead_phone)) {
                            $displayPhone = $item->lead_phone;
                        } elseif (!empty($item->lead_email)) {
                            $displayPhone = $item->lead_email;
                        } elseif (!empty($item->note) && preg_match('/(\+?[0-9]{7,15}|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $item->note, $matches)) {
                            $displayPhone = $matches[0];
                        }
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td data-label="Lead">
                            <?php if ($item->lead_id): ?>
                                <a class="text-white text-decoration-none fw-semibold" href="index.php?route=leads/view/<?php echo $item->lead_id; ?>">
                                    <i class="fa-solid fa-user me-1 text-primary"></i><?php echo htmlspecialchars(trim($item->first_name . ' ' . ($item->last_name ?? ''))); ?>
                                </a>
                                <?php if ($displayPhone): ?>
                                    <div class="text-info small font-monospace"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($displayPhone); ?></div>
                                <?php else: ?>
                                    <div class="text-secondary small"><?php echo htmlspecialchars($item->lead_company_name ?: 'Individual'); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($displayPhone): ?>
                                    <div class="text-warning fw-semibold small font-monospace"><i class="fa-solid fa-phone me-1"></i><?php echo htmlspecialchars($displayPhone); ?></div>
                                <?php endif; ?>
                                <span class="text-secondary small"><i class="fa-solid fa-user-slash me-1"></i>Unlinked Lead</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Person">
                            <span class="badge bg-dark border border-secondary text-white">👤 <?php echo htmlspecialchars($item->user_name); ?></span>
                        </td>
                        <td data-label="Channel">
                            <?php 
                                $c = strtolower($item->channel);
                                $badgeStyle = 'background: rgba(37,99,235,0.15); color: #60a5fa; border: 1px solid rgba(37,99,235,0.3);';
                                $icon = 'fa-comment';
                                if ($c === 'whatsapp') {
                                    $badgeStyle = 'background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3);';
                                    $icon = 'fa-whatsapp';
                                } elseif ($c === 'sms') {
                                    $badgeStyle = 'background: rgba(234,179,8,0.15); color: #fde047; border: 1px solid rgba(234,179,8,0.3);';
                                    $icon = 'fa-message';
                                } elseif ($c === 'call') {
                                    $badgeStyle = 'background: rgba(6,182,212,0.15); color: #22d3ee; border: 1px solid rgba(6,182,212,0.3);';
                                    $icon = 'fa-phone';
                                } elseif ($c === 'email') {
                                    $badgeStyle = 'background: rgba(168,85,247,0.15); color: #c084fc; border: 1px solid rgba(168,85,247,0.3);';
                                    $icon = 'fa-envelope';
                                }
                            ?>
                            <span class="badge" style="<?php echo $badgeStyle; ?>">
                                <i class="fa-brands <?php echo $icon; ?> fa-solid me-1"></i><?php echo strtoupper($item->channel); ?>
                            </span>
                        </td>
                        <td data-label="Direction">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                <?php echo strtoupper($item->direction); ?>
                            </span>
                        </td>
                        <td data-label="Outcome">
                            <div class="text-white fw-semibold small"><?php echo htmlspecialchars($item->outcome ?: 'Log Entry'); ?></div>
                            <div class="text-secondary small text-truncate" style="max-width: 280px;"><?php echo htmlspecialchars($item->note ?: ''); ?></div>
                        </td>
                        <td data-label="When" class="font-monospace small text-white">
                            <?php echo htmlspecialchars(formatToLocalTime($item->happened_at, 'Y-m-d H:i')); ?>
                        </td>
                        <td data-label="Proof / Actions" class="text-end">
                            <div class="d-inline-flex gap-1">
                                <?php if ($item->proof_url): ?>
                                    <a class="btn btn-outline-info btn-sm" target="_blank" href="index.php?route=file/show&key=<?php echo urlencode($item->proof_url); ?>" title="View Proof">
                                        <i class="fa-solid fa-paperclip"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-warning btn-sm edit-comm-btn" 
                                        data-id="<?php echo $item->communication_id; ?>"
                                        data-channel="<?php echo htmlspecialchars($item->channel); ?>"
                                        data-direction="<?php echo htmlspecialchars($item->direction); ?>"
                                        data-outcome="<?php echo htmlspecialchars($item->outcome ?? ''); ?>"
                                        data-note="<?php echo htmlspecialchars($item->note ?? ''); ?>"
                                        data-happened="<?php echo date('Y-m-d\TH:i', strtotime($item->happened_at)); ?>"
                                        title="Edit Outcome & Notes">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form action="index.php?route=communications/delete/<?php echo $item->communication_id; ?>" method="POST" onsubmit="return confirm('Delete this communication record?');">
                                    <button class="btn btn-outline-danger btn-sm" type="submit" title="Delete Log"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bulk Upload Modal (WhatsApp, SMS, Calls, Email) -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-file-import text-info me-2"></i>Bulk Upload Communications (WhatsApp / SMS / Calls / Email)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?route=communications/bulkUpload" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <ul class="nav nav-tabs border-secondary mb-3" id="bulkTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-white" id="csv-tab" data-bs-toggle="tab" data-bs-target="#csv-pane" type="button" role="tab">
                                <i class="fa-solid fa-file-csv me-1 text-success"></i>Upload CSV File
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-white" id="paste-tab" data-bs-toggle="tab" data-bs-target="#paste-pane" type="button" role="tab">
                                <i class="fa-solid fa-paste me-1 text-warning"></i>Quick Bulk Paste
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="bulkTabsContent">
                        <!-- CSV Tab -->
                        <div class="tab-pane fade show active" id="csv-pane" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Select CSV File</label>
                                <input type="file" name="csv_file" class="form-control bg-dark border-secondary text-white" accept=".csv,.txt">
                            </div>
                            <div class="alert alert-info bg-dark border-secondary text-secondary small">
                                <div class="fw-semibold text-white mb-1"><i class="fa-solid fa-circle-info text-info me-1"></i>CSV Columns Standard Format:</div>
                                <code>phone_or_email_or_lead_id, channel, direction, outcome, notes, happened_at</code>
                                <div class="mt-2">
                                    <a href="index.php?route=communications/sampleCsv" class="btn btn-sm btn-outline-info">
                                        <i class="fa-solid fa-download me-1"></i>Download Sample CSV Template
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Paste Tab -->
                        <div class="tab-pane fade" id="paste-pane" role="tabpanel">
                            <div class="mb-2">
                                <label class="form-label text-secondary">Paste Bulk Records (1 touch point per line)</label>
                                <textarea name="bulk_text" class="form-control bg-dark border-secondary text-white font-monospace" rows="6" placeholder="+919876543210, whatsapp, sent, Template Sent, Followed up regarding quote
john@example.com, email, sent, Email Sent, Sent monthly brochure
9876543211, call, made, Connected, Discussed requirements"></textarea>
                            </div>
                            <div class="text-secondary small">Format: <code>Phone/Email/LeadID, Channel, Direction, Outcome, Notes</code></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">
                        <i class="fa-solid fa-upload me-1"></i>Start Bulk Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Communication Log Modal -->
<div class="modal fade" id="addCommunicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-phone-volume text-primary me-2"></i>Log Communication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?route=communications/add" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Lead</label>
                        <select name="lead_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">No linked lead</option>
                            <?php foreach ($leads as $lead): ?>
                                <option value="<?php echo $lead->lead_id; ?>"><?php echo htmlspecialchars($lead->first_name . ' ' . ($lead->last_name ?? '') . ' - ' . ($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Channel</label>
                            <select name="channel" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($channels as $channel): ?><option value="<?php echo $channel; ?>"><?php echo strtoupper($channel); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Direction</label>
                            <select name="direction" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($directions as $direction): ?><option value="<?php echo $direction; ?>"><?php echo strtoupper($direction); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Happened At</label>
                            <input type="datetime-local" name="happened_at" class="form-control bg-dark border-secondary text-white" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Duration Minutes</label>
                            <input type="number" min="0" name="duration_minutes" class="form-control bg-dark border-secondary text-white" value="0">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Outcome</label>
                        <input type="text" name="outcome" class="form-control bg-dark border-secondary text-white" placeholder="Interested, no answer, callback requested">
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Note</label>
                        <textarea name="note" class="form-control bg-dark border-secondary text-white" rows="3"></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Proof Screenshot</label>
                        <input type="file" name="proof" class="form-control bg-dark border-secondary text-white" accept="image/*,.pdf">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" style="background: var(--primary); border: none;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Communication Modal (Outcome & Notes) -->
<div class="modal fade" id="editCommunicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Outcome & Notes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCommForm" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Channel</label>
                            <select name="channel" id="edit_channel" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($channels as $channel): ?><option value="<?php echo $channel; ?>"><?php echo strtoupper($channel); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Direction</label>
                            <select name="direction" id="edit_direction" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($directions as $direction): ?><option value="<?php echo $direction; ?>"><?php echo strtoupper($direction); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Happened At</label>
                        <input type="datetime-local" name="happened_at" id="edit_happened_at" class="form-control bg-dark border-secondary text-white">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Outcome</label>
                        <input type="text" name="outcome" id="edit_outcome" class="form-control bg-dark border-secondary text-white" placeholder="Interested, Connected, Left Voicemail, Template Sent...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Notes / Touchpoint Log</label>
                        <textarea name="note" id="edit_note" class="form-control bg-dark border-secondary text-white" rows="4" placeholder="Enter updated details or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa-solid fa-check me-1"></i>Update Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-comm-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const channel = this.getAttribute('data-channel');
            const direction = this.getAttribute('data-direction');
            const outcome = this.getAttribute('data-outcome');
            const note = this.getAttribute('data-note');
            const happened = this.getAttribute('data-happened');

            document.getElementById('editCommForm').action = 'index.php?route=communications/update/' + id;
            document.getElementById('edit_channel').value = channel;
            document.getElementById('edit_direction').value = direction;
            document.getElementById('edit_outcome').value = outcome;
            document.getElementById('edit_note').value = note;
            document.getElementById('edit_happened_at').value = happened;

            const modal = new bootstrap.Modal(document.getElementById('editCommunicationModal'));
            modal.show();
        });
    });
});
</script>
