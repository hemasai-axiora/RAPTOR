<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-brands fa-whatsapp me-2 text-success"></i>WhatsApp Team Inbox</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Official Meta Cloud API live conversations, 24-hr session tracking, and agent assignment.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=whatsapp/templates" class="btn btn-outline-info btn-sm px-3 py-2">
                <i class="fa-solid fa-layer-group me-1"></i>Message Templates
            </a>
            <a href="index.php?route=whatsapp/broadcasts" class="btn btn-outline-primary btn-sm px-3 py-2">
                <i class="fa-solid fa-bullhorn me-1"></i>Broadcasts
            </a>
            <a href="index.php?route=whatsapp/settings" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-gear me-1"></i>API Settings
            </a>
        </div>
    </div>

    <!-- Conversations Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>RECIPIENT / CONTACT</th>
                    <th>LAST MESSAGE</th>
                    <th>SESSION STATUS (24-HR WINDOW)</th>
                    <th>ASSIGNED AGENT</th>
                    <th>LAST ACTIVITY</th>
                    <th class="text-end">ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($conversations)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fa-brands fa-whatsapp fa-2x mb-2 d-block"></i>
                            No active WhatsApp conversations found. Messages sent or received via the Meta Cloud API will appear here.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($conversations as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white">
                                    <a href="index.php?route=whatsapp/chat/<?= $c->conversation_id; ?>" class="text-decoration-none text-info">
                                        <?= htmlspecialchars(trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: ('+' . $c->phone_number)); ?>
                                    </a>
                                    <?php if ((int)$c->unread_count > 0): ?>
                                        <span class="badge bg-success ms-1"><?= (int)$c->unread_count; ?> new</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-secondary">
                                    <span class="font-monospace">+<?= htmlspecialchars($c->phone_number); ?></span>
                                    <?php if (!empty($c->company_name)): ?>
                                        <span class="mx-1">•</span> <?= htmlspecialchars($c->company_name); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="max-width: 250px;">
                                <div class="text-truncate small text-secondary">
                                    <?php if ($c->last_message_direction === 'outbound'): ?>
                                        <i class="fa-solid fa-reply me-1 text-info"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-arrow-down-left me-1 text-success"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($c->last_message_body ?: 'No messages yet'); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $isActive = !empty($c->session_expires_at) && strtotime($c->session_expires_at) > time();
                                if ($isActive):
                                    $diffSecs = strtotime($c->session_expires_at) - time();
                                    $hoursLeft = round($diffSecs / 3600, 1);
                                ?>
                                    <span class="badge bg-success-subtle text-success border border-success">
                                        <i class="fa-solid fa-clock me-1"></i>Active (<?= $hoursLeft; ?>h left)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-muted">
                                        <i class="fa-solid fa-lock me-1"></i>Expired (Template Req)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-secondary">
                                <?= htmlspecialchars($c->assigned_user_name ?: 'Unassigned'); ?>
                            </td>
                            <td class="small text-secondary">
                                <?= date('M d, H:i', strtotime($c->updated_at)); ?>
                            </td>
                            <td class="text-end">
                                <a href="index.php?route=whatsapp/chat/<?= $c->conversation_id; ?>" class="btn btn-outline-success btn-sm px-3 py-1">
                                    <i class="fa-solid fa-message me-1"></i>Open Chat
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
