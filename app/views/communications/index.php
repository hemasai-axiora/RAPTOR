<?php if (!empty($_SESSION['communication_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['communication_error']); unset($_SESSION['communication_error']); ?></div>
<?php endif; ?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Communications Log</h4>
            <div class="text-secondary" style="font-size:0.9rem;">Calls, WhatsApp, SMS, email, and social touches are self-reported with optional proof.</div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCommunicationModal" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-phone-volume me-2"></i>Log Communication
        </button>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="communications/index">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-3">
                <label class="form-label text-secondary">Person</label>
                <select name="user_id" class="form-select bg-dark border-secondary text-white">
                    <option value="">All visible</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label text-secondary">Channel</label>
            <select name="channel" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?php echo $channel; ?>" <?php echo $filters['channel'] === $channel ? 'selected' : ''; ?>><?php echo strtoupper($channel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Direction</label>
            <select name="direction" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($directions as $direction): ?>
                    <option value="<?php echo $direction; ?>" <?php echo $filters['direction'] === $direction ? 'selected' : ''; ?>><?php echo strtoupper($direction); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">From</label>
            <input type="date" name="date_from" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_from'], 0, 10)); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">To</label>
            <input type="date" name="date_to" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_to'], 0, 10)); ?>">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-light w-100" type="submit"><i class="fa-solid fa-filter"></i></button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Lead</th>
                    <th>Person</th>
                    <th>Channel</th>
                    <th>Direction</th>
                    <th>Outcome</th>
                    <th>When</th>
                    <th class="text-end">Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($communications)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-secondary">No communications found.</td></tr>
                <?php endif; ?>
                <?php foreach ($communications as $item): ?>
                    <tr>
                        <td data-label="Lead">
                            <?php if ($item->lead_id): ?>
                                <a class="text-white text-decoration-none fw-semibold" href="index.php?route=leads/view/<?php echo $item->lead_id; ?>">
                                    <?php echo htmlspecialchars(trim($item->first_name . ' ' . ($item->last_name ?? ''))); ?>
                                </a>
                                <div class="text-secondary small"><?php echo htmlspecialchars($item->lead_company_name ?: 'Individual'); ?></div>
                            <?php else: ?>
                                <span class="text-secondary">No lead linked</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Person"><?php echo htmlspecialchars($item->user_name); ?></td>
                        <td data-label="Channel"><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo strtoupper($item->channel); ?></span></td>
                        <td data-label="Direction"><?php echo strtoupper($item->direction); ?></td>
                        <td data-label="Outcome">
                            <div class="text-white"><?php echo htmlspecialchars($item->outcome ?: '-'); ?></div>
                            <div class="text-secondary small"><?php echo htmlspecialchars($item->note ?: ''); ?></div>
                        </td>
                        <td data-label="When"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->happened_at))); ?></td>
                        <td data-label="Proof" class="text-end">
                            <?php if ($item->proof_url): ?>
                                <a class="btn btn-outline-info btn-sm" target="_blank" href="index.php?route=file/show&key=<?php echo urlencode($item->proof_url); ?>"><i class="fa-solid fa-paperclip"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addCommunicationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Log Communication</h5>
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
