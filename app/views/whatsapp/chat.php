<div class="mb-4">
    <!-- Breadcrumb & Nav -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?route=whatsapp/conversations" class="text-secondary text-decoration-none small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to WhatsApp Inbox
        </a>
        <div class="small text-secondary">
            Conversation #<?= $conversation->conversation_id; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Chat Pane -->
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary d-flex flex-column" style="border-radius: 10px; height: 75vh;">
                <!-- Chat Header -->
                <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center bg-black bg-opacity-30">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center fs-4" style="width: 44px; height: 44px;">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white mb-0">
                                <?= htmlspecialchars(trim(($conversation->first_name ?? '') . ' ' . ($conversation->last_name ?? '')) ?: ('+' . $conversation->phone_number)); ?>
                            </div>
                            <div class="small text-secondary font-monospace">+<?= htmlspecialchars($conversation->phone_number); ?></div>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_session_active): ?>
                            <span class="badge bg-success-subtle text-success border border-success">
                                <i class="fa-solid fa-circle-check me-1"></i>24-hr Free Window Active
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary text-muted" title="Meta requires a pre-approved template outside 24h">
                                <i class="fa-solid fa-lock me-1"></i>Window Expired (Template Required)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages Thread Body -->
                <div class="p-4 flex-grow-1 overflow-auto d-flex flex-column gap-3" style="background: #0a0f1d;" id="chatScroll">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5 text-muted my-auto">
                            <i class="fa-brands fa-whatsapp fa-3x mb-2 d-block text-secondary"></i>
                            No messages in this conversation yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php $isOut = ($msg->direction === 'outbound'); ?>
                            <div class="d-flex flex-column <?= $isOut ? 'align-items-end' : 'align-items-start'; ?>">
                                <div class="p-3 rounded-3 position-relative" 
                                     style="max-width: 75%; font-size: 0.9rem; line-height: 1.4; <?= $isOut ? 'background: #075e54; color: #fff;' : 'background: #1f2c34; color: #e9edef;'; ?>">
                                    
                                    <?php if ($msg->message_type === 'template'): ?>
                                        <div class="badge bg-black bg-opacity-40 text-info small mb-1">
                                            <i class="fa-solid fa-layer-group me-1"></i><?= htmlspecialchars($msg->template_name ?: 'Template'); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div><?= nl2br(htmlspecialchars($msg->body ?? '')); ?></div>

                                    <div class="d-flex justify-content-end align-items-center gap-1 mt-1 small" style="font-size: 0.7rem; opacity: 0.8;">
                                        <span><?= date('H:i', strtotime($msg->created_at)); ?></span>
                                        <?php if ($isOut): ?>
                                            <?php if ($msg->status === 'read'): ?>
                                                <i class="fa-solid fa-check-double text-info" title="Read"></i>
                                            <?php elseif ($msg->status === 'delivered'): ?>
                                                <i class="fa-solid fa-check-double" title="Delivered"></i>
                                            <?php elseif ($msg->status === 'sent'): ?>
                                                <i class="fa-solid fa-check" title="Sent"></i>
                                            <?php elseif ($msg->status === 'failed'): ?>
                                                <i class="fa-solid fa-circle-exclamation text-danger" title="<?= htmlspecialchars($msg->error_message ?? 'Failed'); ?>"></i>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Footer / Input Form -->
                <div class="p-3 border-top border-secondary bg-black bg-opacity-40">
                    <?php if ($is_session_active): ?>
                        <!-- Active 24-hr window: Free-form messaging permitted -->
                        <form method="POST" action="index.php?route=whatsapp/send" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                            <input type="hidden" name="conversation_id" value="<?= $conversation->conversation_id; ?>">
                            <input type="text" name="body" class="form-control bg-dark text-white border-secondary" placeholder="Type your WhatsApp reply (Free 24-hr session)..." autocomplete="off" required>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fa-solid fa-paper-plane me-1"></i>Send
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Window expired: Must select pre-approved Meta Template -->
                        <div class="alert alert-dark border-secondary p-2 mb-2 small text-secondary">
                            <i class="fa-solid fa-circle-info text-warning me-1"></i>
                            <strong>24-Hour Window Expired:</strong> To re-open a conversation with this prospect, Meta requires sending an approved Message Template.
                        </div>
                        <form method="POST" action="index.php?route=whatsapp/send" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                            <input type="hidden" name="conversation_id" value="<?= $conversation->conversation_id; ?>">
                            <select name="template_id" class="form-select form-select-sm bg-dark text-white border-secondary" required>
                                <option value="">Select an Approved Message Template...</option>
                                <?php foreach ($templates as $tmpl): ?>
                                    <option value="<?= $tmpl->template_id; ?>"><?= htmlspecialchars($tmpl->name); ?> (<?= $tmpl->category; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm px-3 text-nowrap">
                                <i class="fa-solid fa-paper-plane me-1"></i>Send Template
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer 360 Contact Card -->
        <div class="col-lg-4">
            <div class="card bg-dark border-secondary p-3 mb-4" style="border-radius: 10px;">
                <h6 class="text-white border-bottom border-secondary pb-2 mb-3">
                    <i class="fa-solid fa-id-card me-2 text-info"></i>Customer 360 Profile
                </h6>
                <?php if ($contact): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-4" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-white"><?= htmlspecialchars(trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: 'Anonymous Contact'); ?></div>
                            <div class="small text-secondary"><?= htmlspecialchars($contact->company_name ?: 'Individual'); ?></div>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2 small mb-3">
                        <?php if (!empty($contact->primary_email)): ?>
                            <div class="text-secondary"><i class="fa-regular fa-envelope me-2"></i><?= htmlspecialchars($contact->primary_email); ?></div>
                        <?php endif; ?>
                        <div class="text-secondary"><i class="fa-solid fa-phone me-2"></i>+<?= htmlspecialchars($conversation->phone_number); ?></div>
                        <div class="text-secondary"><i class="fa-solid fa-diagram-project me-2"></i>Stage: <strong><?= strtoupper($contact->lifecycle_stage); ?></strong></div>
                        <div class="text-secondary"><i class="fa-solid fa-bolt me-2 text-warning"></i>Score: <strong><?= (int)$contact->lead_score; ?></strong></div>
                    </div>

                    <?php if ((int)$contact->is_suppressed === 1): ?>
                        <div class="alert alert-danger p-2 mb-3 small">
                            <i class="fa-solid fa-ban me-1"></i><strong>Contact Suppressed / Opted-out:</strong> All marketing outreach is blocked.
                        </div>
                    <?php endif; ?>

                    <a href="index.php?route=contacts/view/<?= $contact->contact_id; ?>" class="btn btn-outline-info btn-sm w-100">
                        <i class="fa-solid fa-eye me-1"></i>View Full Customer 360 Profile
                    </a>
                <?php else: ?>
                    <div class="text-muted small">No linked Customer 360 record. When the contact submits a form or email, their profiles will automatically merge.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-scroll chat to bottom
    var chat = document.getElementById('chatScroll');
    if (chat) { chat.scrollTop = chat.scrollHeight; }
</script>
