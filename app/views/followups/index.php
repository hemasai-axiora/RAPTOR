<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">My Follow-ups Today</h4>
            <div class="text-secondary" style="font-size:0.9rem;"><?php echo (int) $today_count; ?> scheduled item(s) due today.</div>
        </div>
        <a href="index.php?route=leads/index" class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-address-book me-2"></i>Lead List
        </a>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="followups/index">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-3">
                <label class="form-label text-secondary">Owner</label>
                <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                    <option value="">All visible</option>
                    <?php foreach ($assignees as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
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
                    <th>Due</th>
                    <th>Channel</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Note</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($followups)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-secondary">No follow-ups found for this filter.</td></tr>
                <?php endif; ?>

                <?php foreach ($followups as $item): ?>
                    <?php
                        $statusTone = [
                            'scheduled' => 'primary',
                            'completed' => 'success',
                            'missed' => 'danger',
                            'cancelled' => 'secondary',
                        ][$item->status] ?? 'secondary';
                    ?>
                    <tr>
                        <td data-label="Lead">
                            <a class="text-white fw-semibold text-decoration-none" href="index.php?route=leads/view/<?php echo $item->lead_id; ?>">
                                <?php echo htmlspecialchars($item->first_name . ' ' . ($item->last_name ?? '')); ?>
                            </a>
                            <div class="text-secondary small"><?php echo htmlspecialchars($item->lead_company_name ?: $item->lead_email ?: $item->lead_phone ?: 'No contact'); ?></div>
                        </td>
                        <td data-label="Due"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->due_at))); ?></td>
                        <td data-label="Channel"><span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo strtoupper($item->channel); ?></span></td>
                        <td data-label="Owner" class="text-secondary"><?php echo htmlspecialchars($item->assignee_name); ?></td>
                        <td data-label="Status"><span class="badge bg-<?php echo $statusTone; ?>-subtle text-<?php echo $statusTone; ?> border border-<?php echo $statusTone; ?>-subtle"><?php echo strtoupper($item->status); ?></span></td>
                        <td data-label="Note" class="text-secondary" style="max-width:260px;"><?php echo htmlspecialchars($item->note ?: $item->outcome ?: '-'); ?></td>
                        <td data-label="Actions" class="text-end">
                            <?php if ($item->status === 'scheduled'): ?>
                                <form class="d-inline-flex gap-2 flex-wrap justify-content-end" action="index.php?route=followups/complete/<?php echo $item->follow_up_id; ?>" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="text" name="outcome" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Outcome" style="width: 150px;">
                                    <button class="btn btn-outline-success btn-sm" title="Complete"><i class="fa-solid fa-check"></i></button>
                                </form>
                                <form class="d-inline" action="index.php?route=followups/cancel/<?php echo $item->follow_up_id; ?>" method="POST" onsubmit="return confirm('Cancel this follow-up?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <button class="btn btn-outline-secondary btn-sm" title="Cancel"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="text-secondary small"><?php echo htmlspecialchars($item->outcome ?: 'Closed'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
