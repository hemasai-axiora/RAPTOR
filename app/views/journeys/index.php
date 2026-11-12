<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-route me-2 text-primary"></i>Marketing Automation Journeys</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Visual event-driven workflows that nurture leads across Email, WhatsApp, lead scoring, and sales rep assignments.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=journeys/scoring" class="btn btn-outline-warning btn-sm px-3 py-2">
                <i class="fa-solid fa-star me-1"></i> Lead Scoring Rules
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newJourneyModal">
                <i class="fa-solid fa-plus me-1"></i>Create Journey
            </button>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Total Journeys</div>
                <div class="fs-3 fw-bold text-white"><?= count($journeys); ?></div>
                <div class="text-muted small mt-1">Multi-step sequences</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Active Workflows</div>
                <?php
                $activeCount = count(array_filter($journeys, fn($j) => $j->status === 'active'));
                ?>
                <div class="fs-3 fw-bold text-success"><?= $activeCount; ?></div>
                <div class="text-muted small mt-1">Running automated triggers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Total Contacts Enrolled</div>
                <?php
                $totalEnrolled = array_reduce($journeys, fn($carry, $j) => $carry + (int)$j->total_enrolled, 0);
                ?>
                <div class="fs-3 fw-bold text-info"><?= number_format($totalEnrolled); ?></div>
                <div class="text-muted small mt-1">Nurtured leads & customers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="text-secondary small text-uppercase fw-bold">Completed Journeys</div>
                <?php
                $totalCompleted = array_reduce($journeys, fn($carry, $j) => $carry + (int)$j->total_completed, 0);
                $completionRate = $totalEnrolled > 0 ? round(($totalCompleted / $totalEnrolled) * 100, 1) : 0;
                ?>
                <div class="fs-3 fw-bold text-warning"><?= number_format($totalCompleted); ?> <span class="fs-6 text-muted font-monospace">(<?= $completionRate; ?>%)</span></div>
                <div class="text-muted small mt-1">Fully converted / finished</div>
            </div>
        </div>
    </div>

    <!-- Journeys Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>JOURNEY NAME</th>
                    <th>INBOUND TRIGGER</th>
                    <th>STEPS</th>
                    <th>STATUS</th>
                    <th>ENROLLED</th>
                    <th>COMPLETED</th>
                    <th class="text-end">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($journeys)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-route fa-3x mb-3 d-block opacity-50"></i>
                            No automation journeys configured yet. Click "Create Journey" to launch your first sequence.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($journeys as $j): ?>
                        <tr>
                            <td>
                                <a href="index.php?route=journeys/builder&id=<?= $j->journey_id; ?>" class="fw-bold text-white text-decoration-none">
                                    <?= htmlspecialchars($j->name); ?>
                                </a>
                                <?php if (!empty($j->description)): ?>
                                    <div class="text-secondary small text-truncate" style="max-width: 280px;"><?= htmlspecialchars($j->description); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary text-light font-monospace" style="font-size: 0.78rem;">
                                    <i class="fa-solid fa-bolt me-1 text-warning"></i><?= htmlspecialchars($triggers[$j->trigger_type] ?? $j->trigger_type); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary text-white font-monospace">
                                    <?= (int)$j->step_count; ?> steps
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusBadges = [
                                    'draft' => 'secondary',
                                    'active' => 'success',
                                    'paused' => 'warning',
                                    'archived' => 'danger'
                                ];
                                $sBadge = $statusBadges[$j->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $sBadge; ?>-subtle text-<?= $sBadge; ?> border border-<?= $sBadge; ?> text-uppercase" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($j->status); ?>
                                </span>
                            </td>
                            <td>
                                <span class="font-monospace text-info fw-semibold"><?= number_format((int)$j->total_enrolled); ?></span>
                            </td>
                            <td>
                                <span class="font-monospace text-success fw-semibold"><?= number_format((int)$j->total_completed); ?></span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="index.php?route=journeys/builder&id=<?= $j->journey_id; ?>" class="btn btn-outline-primary btn-sm px-2 py-1" title="Visual Step Builder">
                                        <i class="fa-solid fa-diagram-project"></i> Builder
                                    </a>
                                    <form action="index.php?route=journeys/toggleStatus" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
                                        <input type="hidden" name="journey_id" value="<?= $j->journey_id; ?>">
                                        <?php if ($j->status === 'active'): ?>
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="btn btn-outline-warning btn-sm px-2 py-1" title="Pause Journey">
                                                <i class="fa-solid fa-pause"></i>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-outline-success btn-sm px-2 py-1" title="Activate Journey">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Journey -->
<div class="modal fade" id="newJourneyModal" tabindex="-1" aria-labelledby="newJourneyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?route=journeys/create" method="POST" class="modal-content bg-dark text-white border-secondary">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ($csrf_token ?? ''); ?>">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="newJourneyModalLabel"><i class="fa-solid fa-route text-primary me-2"></i>Create New Automation Journey</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Journey Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Inbound Demo Request Sequence" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Trigger Event <span class="text-danger">*</span></label>
                    <select name="trigger_type" class="form-select bg-dark text-white border-secondary" required>
                        <option value="" disabled selected>Select Inbound Trigger</option>
                        <?php foreach ($triggers as $typeKey => $typeLabel): ?>
                            <option value="<?= $typeKey; ?>"><?= htmlspecialchars($typeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted">What starts this journey for a contact.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Description</label>
                    <textarea name="description" rows="2" class="form-control bg-dark text-white border-secondary" placeholder="Brief notes on journey goals, audience, and channels..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right me-1"></i> Proceed to Builder</button>
            </div>
        </form>
    </div>
</div>

