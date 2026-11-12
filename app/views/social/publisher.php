<div class="pulse-card mb-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-share-nodes me-2 text-primary"></i>Social Media Live Publishing & Multi-Channel Connectors</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Publish live announcements and schedule corporate updates across LinkedIn, Facebook, Instagram, and X with token health monitoring.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=socialPublisher/leadAds" class="btn btn-outline-info btn-sm px-3 py-2">
                <i class="fa-brands fa-meta me-1"></i> Meta Lead Ads Sync
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#composePostModal">
                <i class="fa-solid fa-pen-nib me-1"></i> Compose Post
            </button>
        </div>
    </div>

    <!-- Connected OAuth Channels Grid -->
    <h6 class="text-secondary small text-uppercase fw-bold mb-3">Connected Brand Channels & Token Health</h6>
    <div class="row g-3 mb-4">
        <?php
        $platformIcons = [
            'linkedin' => ['icon' => 'fa-brands fa-linkedin', 'color' => 'info', 'title' => 'LinkedIn Organization'],
            'facebook' => ['icon' => 'fa-brands fa-facebook', 'color' => 'primary', 'title' => 'Facebook Official Page'],
            'instagram' => ['icon' => 'fa-brands fa-instagram', 'color' => 'danger', 'title' => 'Instagram Professional'],
            'twitter_x' => ['icon' => 'fa-brands fa-x-twitter', 'color' => 'light', 'title' => 'X / Twitter Corporate']
        ];
        ?>
        <?php foreach ($tokens as $t): ?>
            <?php
            $info = $platformIcons[$t->platform] ?? ['icon' => 'fa-solid fa-globe', 'color' => 'secondary', 'title' => ucfirst($t->platform)];
            ?>
            <div class="col-md-4">
                <div class="p-3 rounded bg-dark border border-secondary border-opacity-25 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="<?= $info['icon']; ?> text-<?= $info['color']; ?> fa-lg"></i>
                            <span class="text-white fw-bold"><?= $info['title']; ?></span>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success text-uppercase" style="font-size: 0.7rem;">
                            ACTIVE
                        </span>
                    </div>
                    <div class="text-secondary small font-monospace text-truncate mb-2"><?= htmlspecialchars($t->account_name); ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        Token Valid Until: <?= date('M d, Y', strtotime($t->token_expires_at)); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Publishing Queue & History -->
    <h6 class="text-secondary small text-uppercase fw-bold mb-3">Publishing Stream & Scheduled Dispatch Queue</h6>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>PLATFORM</th>
                    <th>POST MESSAGE & CONTENT</th>
                    <th>SCHEDULED / PUBLISHED TIME</th>
                    <th>STATUS</th>
                    <th>EXTERNAL POST ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-plus fa-3x mb-3 d-block opacity-50"></i>
                            No social posts in queue. Click "Compose Post" to schedule your next multi-channel update.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queue as $q): ?>
                        <tr>
                            <td>
                                <?php
                                $badgeColor = match ($q->platform) {
                                    'linkedin' => 'info',
                                    'facebook' => 'primary',
                                    'instagram' => 'danger',
                                    'twitter_x' => 'light',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badgeColor; ?>-subtle text-<?= $badgeColor; ?> border border-<?= $badgeColor; ?> text-uppercase font-monospace" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($q->platform); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-white small text-truncate" style="max-width: 420px; white-space: pre-wrap; word-break: break-word;">
                                    <?= htmlspecialchars($q->message_text); ?>
                                </div>
                                <?php if (!empty($q->media_url)): ?>
                                    <div class="mt-1">
                                        <a href="<?= htmlspecialchars($q->media_url); ?>" target="_blank" class="text-info small text-decoration-none">
                                            <i class="fa-solid fa-image me-1"></i> Attached Media Asset
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-white small"><?= date('M d, Y H:i', strtotime($q->scheduled_at)); ?></div>
                                <?php if (!empty($q->published_at)): ?>
                                    <div class="text-muted" style="font-size: 0.75rem;">Dispatched: <?= date('M d, H:i', strtotime($q->published_at)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'published' => 'success',
                                    'pending' => 'warning',
                                    'publishing' => 'info',
                                    'failed' => 'danger'
                                ];
                                $stBadge = $statusBadges[$q->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $stBadge; ?>-subtle text-<?= $stBadge; ?> border border-<?= $stBadge; ?> text-uppercase" style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($q->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($q->external_post_id)): ?>
                                    <span class="font-monospace text-secondary small"><?= htmlspecialchars($q->external_post_id); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Compose & Schedule Post -->
<div class="modal fade" id="composePostModal" tabindex="-1" aria-labelledby="composePostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="index.php?route=socialPublisher/schedule" method="POST" class="modal-content bg-dark text-white border-secondary">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="composePostModalLabel"><i class="fa-solid fa-pen-nib text-primary me-2"></i>Compose & Schedule Social Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Target Platform <span class="text-danger">*</span></label>
                        <select name="platform" class="form-select bg-dark text-white border-secondary" required>
                            <option value="linkedin">LinkedIn (Corporate Page)</option>
                            <option value="facebook">Facebook (Brand Page)</option>
                            <option value="instagram">Instagram (Business Profile)</option>
                            <option value="twitter_x">X / Twitter</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Schedule Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control bg-dark text-white border-secondary">
                        <div class="form-text text-muted">Leave empty to dispatch immediately.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Post Content & Hashtags <span class="text-danger">*</span></label>
                        <textarea name="message_text" rows="5" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="We are excited to announce our new enterprise release... #Innovation #SaaS #Raptor" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-secondary">Media Asset URL (Image / Video)</label>
                        <input type="url" name="media_url" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="https://cdn.raptor.io/media/announcement_banner.png">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="publish_now" value="1" id="publishNowCheck">
                            <label class="form-check-label small text-secondary" for="publishNowCheck">
                                Publish immediately (bypass queue timer)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Dispatch Post</button>
            </div>
        </form>
    </div>
</div>

