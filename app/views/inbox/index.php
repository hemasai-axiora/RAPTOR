<div class="mb-4">
    <!-- Top Filter Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <h4 class="text-white mb-0"><i class="fa-solid fa-inbox me-2 text-primary"></i>Shared Team Inbox</h4>
            <!-- Tabs -->
            <ul class="nav nav-pills small">
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($current_tab === 'all') ? 'active bg-primary' : 'text-secondary'; ?>" href="index.php?route=inbox/index&tab=all">All Active</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($current_tab === 'mine') ? 'active bg-primary' : 'text-secondary'; ?>" href="index.php?route=inbox/index&tab=mine">Assigned to Me</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($current_tab === 'unassigned') ? 'active bg-primary' : 'text-secondary'; ?>" href="index.php?route=inbox/index&tab=unassigned">Unassigned</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 <?= ($current_tab === 'resolved') ? 'active bg-secondary' : 'text-secondary'; ?>" href="index.php?route=inbox/index&tab=resolved">Resolved</a>
                </li>
            </ul>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=inbox/cannedReplies" class="btn btn-outline-info btn-sm">
                <i class="fa-solid fa-bolt me-1"></i>Canned Replies
            </a>
            <a href="index.php?route=whatsapp/conversations" class="btn btn-outline-success btn-sm">
                <i class="fa-brands fa-whatsapp me-1"></i>WhatsApp Cloud
            </a>
        </div>
    </div>

    <!-- 3-Column Split Pane -->
    <div class="row g-3" style="height: calc(85vh - 70px);">
        <!-- Column 1: Thread List (Width 3) -->
        <div class="col-lg-3 h-100">
            <div class="card bg-dark border-secondary h-100 d-flex flex-column" style="border-radius: 10px;">
                <!-- Search bar in thread list -->
                <div class="p-2 border-bottom border-secondary">
                    <form method="GET" action="index.php">
                        <input type="hidden" name="route" value="inbox/index">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($current_tab); ?>">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-black border-secondary text-secondary"><i class="fa-solid fa-search"></i></span>
                            <input type="text" name="search" class="form-control bg-black text-white border-secondary" placeholder="Search conversations..." value="<?= htmlspecialchars($filters['search'] ?? ''); ?>">
                        </div>
                    </form>
                </div>

                <!-- Thread Scroll list -->
                <div class="overflow-auto flex-grow-1 p-2 d-flex flex-column gap-1">
                    <?php if (empty($threads)): ?>
                        <div class="text-center py-5 text-muted small">
                            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
                            No threads in this view.
                        </div>
                    <?php else: ?>
                        <?php foreach ($threads as $t): ?>
                            <?php 
                            $isSelected = ($active_thread && (int)$active_thread->thread_id === (int)$t->thread_id); 
                            $chanIcon = ($t->last_channel === 'whatsapp') ? 'fa-brands fa-whatsapp text-success' : 'fa-solid fa-envelope text-info';
                            ?>
                            <a href="index.php?route=inbox/index&tab=<?= htmlspecialchars($current_tab); ?>&thread_id=<?= $t->thread_id; ?>" 
                               class="p-2 rounded text-decoration-none d-block <?= $isSelected ? 'bg-primary bg-opacity-25 border border-primary' : 'bg-black bg-opacity-30 border border-secondary text-white'; ?>">
                                
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold text-white text-truncate small" style="max-width: 140px;">
                                        <?= htmlspecialchars(trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? '')) ?: ('+' . $t->primary_phone)); ?>
                                    </div>
                                    <span class="text-secondary small font-monospace" style="font-size: 0.7rem;">
                                        <?= date('H:i', strtotime($t->last_message_at)); ?>
                                    </span>
                                </div>

                                <div class="text-secondary text-truncate small mb-1" style="font-size: 0.75rem;">
                                    <i class="<?= $chanIcon; ?> me-1"></i>
                                    <?= htmlspecialchars($t->last_message_preview ?: 'No messages'); ?>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($t->status); ?></span>
                                    <?php if ((int)$t->unread_count > 0): ?>
                                        <span class="badge bg-success" style="font-size: 0.65rem;"><?= (int)$t->unread_count; ?> new</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Column 2: Omnichannel Conversation Stream & Composer (Width 6) -->
        <div class="col-lg-6 h-100">
            <div class="card bg-dark border-secondary h-100 d-flex flex-column" style="border-radius: 10px;">
                <?php if (!$active_thread): ?>
                    <div class="text-center py-5 text-muted my-auto">
                        <i class="fa-solid fa-comments fa-3x mb-3 d-block text-secondary"></i>
                        Select a conversation from the left to view the thread.
                    </div>
                <?php else: ?>
                    <!-- Thread Header -->
                    <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center bg-black bg-opacity-40">
                        <div class="d-flex align-items-center gap-2">
                            <div class="fw-bold text-white fs-6">
                                <?= htmlspecialchars(trim(($active_thread->first_name ?? '') . ' ' . ($active_thread->last_name ?? '')) ?: ('+' . $active_thread->primary_phone)); ?>
                            </div>
                            <?php if ($active_thread->company_name): ?>
                                <span class="badge bg-secondary small"><?= htmlspecialchars($active_thread->company_name); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Action controls -->
                        <div class="d-flex align-items-center gap-2">
                            <!-- Assign rep -->
                            <form method="POST" action="index.php?route=inbox/assign" class="m-0">
                                <input type="hidden" name="thread_id" value="<?= $active_thread->thread_id; ?>">
                                <select name="assigned_user_id" class="form-select form-select-sm bg-black text-white border-secondary" style="font-size: 0.75rem;" onchange="this.form.submit()">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u->user_id; ?>" <?= ($active_thread->assigned_user_id == $u->user_id) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($u->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <!-- Status changer -->
                            <form method="POST" action="index.php?route=inbox/status" class="m-0">
                                <input type="hidden" name="thread_id" value="<?= $active_thread->thread_id; ?>">
                                <select name="status" class="form-select form-select-sm bg-black text-white border-secondary" style="font-size: 0.75rem;" onchange="this.form.submit()">
                                    <?php foreach ($statuses as $st): ?>
                                        <option value="<?= $st; ?>" <?= ($active_thread->status === $st) ? 'selected' : ''; ?>><?= ucfirst($st); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- Conversation Stream -->
                    <div class="p-3 flex-grow-1 overflow-auto d-flex flex-column gap-3" style="background: #0b1120;" id="inboxStream">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-5 text-muted my-auto small">
                                No messages logged in this thread yet.
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php if ((int)$m->is_private_note === 1): ?>
                                    <!-- Internal Private Team Note (Yellow Card) -->
                                    <div class="p-3 rounded border border-warning bg-warning bg-opacity-10" style="margin: 4px 10%;">
                                        <div class="d-flex justify-content-between align-items-center mb-1 small text-warning">
                                            <span><i class="fa-solid fa-lock me-1"></i>Internal Team Note by <strong><?= htmlspecialchars($m->sender_user_name ?: 'Agent'); ?></strong></span>
                                            <span class="font-monospace" style="font-size: 0.7rem;"><?= date('M d, H:i', strtotime($m->created_at)); ?></span>
                                        </div>
                                        <div class="text-white small"><?= nl2br(htmlspecialchars($m->body)); ?></div>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    $isOutbound = ($m->direction === 'outbound'); 
                                    $chanBadge = ($m->channel === 'whatsapp') ? 'bg-success' : 'bg-info';
                                    ?>
                                    <div class="d-flex flex-column <?= $isOutbound ? 'align-items-end' : 'align-items-start'; ?>">
                                        <div class="p-3 rounded-3" style="max-width: 80%; font-size: 0.88rem; line-height: 1.4; <?= $isOutbound ? 'background: #1e3a8a; color: #fff;' : 'background: #1e293b; color: #f8fafc; border: 1px solid #334155;'; ?>">
                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                                <span class="badge <?= $chanBadge; ?> text-uppercase" style="font-size: 0.65rem;">
                                                    <?= htmlspecialchars($m->channel); ?>
                                                </span>
                                                <span class="text-secondary small font-monospace" style="font-size: 0.7rem; opacity: 0.8;">
                                                    <?= date('M d, H:i', strtotime($m->created_at)); ?>
                                                </span>
                                            </div>
                                            <div><?= nl2br(htmlspecialchars($m->body)); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Dual-Mode Composer -->
                    <div class="p-3 border-top border-secondary bg-black bg-opacity-40">
                        <form method="POST" action="index.php?route=inbox/send">
                            <input type="hidden" name="thread_id" value="<?= $active_thread->thread_id; ?>">

                            <!-- Composer Mode Switcher & Canned Picker -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="is_private_note" id="modeReply" value="0" checked onchange="toggleComposerMode(false)">
                                    <label class="btn btn-outline-primary" for="modeReply"><i class="fa-solid fa-paper-plane me-1"></i>Customer Reply</label>

                                    <input type="radio" class="btn-check" name="is_private_note" id="modeNote" value="1" onchange="toggleComposerMode(true)">
                                    <label class="btn btn-outline-warning" for="modeNote"><i class="fa-solid fa-lock me-1"></i>Internal Note</label>
                                </div>

                                <!-- Canned replies insertion dropdown -->
                                <select class="form-select form-select-sm bg-dark text-white border-secondary w-auto" style="font-size: 0.75rem;" onchange="insertCannedReply(this)">
                                    <option value="">Insert Canned Reply...</option>
                                    <?php foreach ($canned_replies as $cr): ?>
                                        <option value="<?= htmlspecialchars(CannedReply::interpolate($cr->body_text, $contact)); ?>">
                                            <?= htmlspecialchars($cr->shortcut); ?> - <?= htmlspecialchars($cr->title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="input-group">
                                <textarea name="body" id="composerInput" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Type your reply (or select a canned template)..." required></textarea>
                                <button type="submit" id="btnComposerSend" class="btn btn-primary px-4">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Column 3: Customer 360 Context Sidebar (Width 3) -->
        <div class="col-lg-3 h-100">
            <div class="card bg-dark border-secondary h-100 p-3 overflow-auto" style="border-radius: 10px;">
                <?php if ($contact): ?>
                    <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                        <i class="fa-solid fa-id-card me-2 text-info"></i>Customer 360
                    </h6>

                    <div class="text-center mb-3">
                        <div class="avatar bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fs-3 mb-2" style="width: 54px; height: 54px;">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h6 class="text-white mb-0"><?= htmlspecialchars(trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: 'Anonymous Contact'); ?></h6>
                        <div class="small text-secondary"><?= htmlspecialchars($contact->company_name ?: 'Individual'); ?></div>
                    </div>

                    <div class="d-flex flex-column gap-2 small border-top border-secondary pt-3 mb-3">
                        <?php if ($contact->primary_email): ?>
                            <div class="text-secondary"><i class="fa-regular fa-envelope me-2"></i><?= htmlspecialchars($contact->primary_email); ?></div>
                        <?php endif; ?>
                        <?php if ($contact->primary_phone): ?>
                            <div class="text-secondary"><i class="fa-solid fa-phone me-2"></i><?= htmlspecialchars($contact->primary_phone); ?></div>
                        <?php endif; ?>
                        <div class="text-secondary"><i class="fa-solid fa-diagram-project me-2"></i>Stage: <strong><?= strtoupper($contact->lifecycle_stage); ?></strong></div>
                        <div class="text-secondary"><i class="fa-solid fa-bolt me-2 text-warning"></i>Score: <strong><?= (int)$contact->lead_score; ?></strong> (<?= ucfirst($contact->lead_quality); ?>)</div>
                    </div>

                    <?php if ((int)$contact->is_suppressed === 1): ?>
                        <div class="alert alert-danger p-2 mb-3 small">
                            <i class="fa-solid fa-ban me-1"></i><strong>Contact Suppressed:</strong> Customer is opted out of outbound marketing.
                        </div>
                    <?php endif; ?>

                    <a href="index.php?route=contacts/view/<?= $contact->contact_id; ?>" class="btn btn-outline-info btn-sm w-100 mt-auto">
                        <i class="fa-solid fa-eye me-1"></i>Open Full 360° Profile
                    </a>
                <?php else: ?>
                    <div class="text-center py-5 text-muted small my-auto">
                        Select a conversation to inspect Customer 360 data.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleComposerMode(isNote) {
        var input = document.getElementById('composerInput');
        var btn = document.getElementById('btnComposerSend');
        if (isNote) {
            input.placeholder = "Write an internal note (visible only to sales team)...";
            input.style.borderColor = "#f59e0b";
            btn.className = "btn btn-warning px-4";
        } else {
            input.placeholder = "Type your customer reply...";
            input.style.borderColor = "#334155";
            btn.className = "btn btn-primary px-4";
        }
    }

    function insertCannedReply(select) {
        if (!select.value) return;
        var input = document.getElementById('composerInput');
        input.value = select.value;
        select.value = '';
        input.focus();
    }

    var stream = document.getElementById('inboxStream');
    if (stream) { stream.scrollTop = stream.scrollHeight; }
</script>

