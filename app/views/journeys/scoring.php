<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-star me-2 text-warning"></i>Behavioral Lead Scoring Matrix</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Configure point allocations for prospect activities and manage automated transitions between Cold, Warm, and Hot tiers.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=journeys/index" class="btn btn-outline-secondary btn-sm px-3 py-2">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Journeys
            </a>
            <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#newScoringRuleModal">
                <i class="fa-solid fa-plus me-1"></i>New Scoring Rule
            </button>
        </div>
    </div>

    <!-- Tier Thresholds Explainer Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-3 rounded bg-dark border border-secondary border-opacity-25">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-secondary text-uppercase">Cold Tier</span>
                    <span class="text-secondary font-monospace small">&lt; 15 pts</span>
                </div>
                <div class="text-white fw-bold">Top of Funnel Prospect</div>
                <div class="text-muted small mt-1">Subscribed or low engagement. Kept in automated nurturing workflows.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded bg-dark border border-warning border-opacity-25">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-warning text-dark text-uppercase">Warm Tier</span>
                    <span class="text-warning font-monospace small">15 - 49 pts</span>
                </div>
                <div class="text-white fw-bold">Marketing Qualified Lead (MQL)</div>
                <div class="text-muted small mt-1">Active multi-channel interest. Trigger high-intent nurture campaigns.</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded bg-dark border border-danger border-opacity-25">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-danger text-uppercase">Hot Tier</span>
                    <span class="text-danger font-monospace small">50+ pts</span>
                </div>
                <div class="text-white fw-bold">Sales Qualified Lead (SQL)</div>
                <div class="text-muted small mt-1">High conversion propensity. Auto-routed to sales rep for direct outreach.</div>
            </div>
        </div>
    </div>

    <!-- Rules Table -->
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="border-radius: 8px; overflow: hidden;">
            <thead class="table-dark" style="border-bottom: 2px solid #333;">
                <tr class="text-secondary small">
                    <th>RULE NAME</th>
                    <th>EVENT IDENTIFIER</th>
                    <th>POINT DELTA</th>
                    <th>DAILY FREQUENCY CAP</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-star fa-3x mb-3 d-block opacity-50"></i>
                            No scoring rules defined yet. Click "New Scoring Rule" to configure points.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rules as $r): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-white"><?= htmlspecialchars($r->rule_name); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace" style="font-size: 0.78rem;">
                                    <?= htmlspecialchars($r->event_type); ?>
                                </span>
                            </td>
                            <td>
                                <?php $delta = (int)$r->score_delta; ?>
                                <span class="badge bg-<?= $delta >= 0 ? 'success' : 'danger'; ?> font-monospace fw-bold px-2 py-1" style="font-size: 0.85rem;">
                                    <?= $delta >= 0 ? "+{$delta}" : "{$delta}"; ?> points
                                </span>
                            </td>
                            <td>
                                <span class="text-secondary font-monospace"><?= (int)$r->max_daily_occurrences; ?>x per day</span>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success border border-success text-uppercase" style="font-size: 0.72rem;">
                                    ACTIVE
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New / Update Scoring Rule -->
<div class="modal fade" id="newScoringRuleModal" tabindex="-1" aria-labelledby="newScoringRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="index.php?route=journeys/saveScoringRule" method="POST" class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="newScoringRuleModalLabel"><i class="fa-solid fa-star text-warning me-2"></i>Configure Lead Scoring Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-secondary">Rule Display Name <span class="text-danger">*</span></label>
                    <input type="text" name="rule_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Pricing Page Visit" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">Event Identifier <span class="text-danger">*</span></label>
                    <input type="text" name="event_type" class="form-control bg-dark text-white border-secondary font-monospace" placeholder="e.g. pricing_visit" required>
                    <div class="form-text text-muted">Machine identifier triggered via tracking snippet or system events.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Score Delta (+/-) <span class="text-danger">*</span></label>
                        <input type="number" name="score_delta" class="form-control bg-dark text-white border-secondary font-monospace" value="15" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary">Max Daily Cap</label>
                        <input type="number" name="max_daily_occurrences" class="form-control bg-dark text-white border-secondary font-monospace" value="5" min="1" max="50">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Rule</button>
            </div>
        </form>
    </div>
</div>

