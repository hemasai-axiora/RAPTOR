<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Performance Ranking</h4>
        <div class="text-secondary" style="font-size:0.9rem;"><?php echo htmlspecialchars(strtoupper($period) . ' - ' . $start . ' to ' . $end); ?></div>
    </div>
    <?php if ($can_manage): ?>
        <form action="index.php?route=performance/recompute" method="POST" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
            <input type="hidden" name="start" value="<?php echo htmlspecialchars($start); ?>">
            <input type="hidden" name="end" value="<?php echo htmlspecialchars($end); ?>">
            <button class="btn btn-outline-info btn-sm"><i class="fa-solid fa-rotate me-2"></i>Refresh Scores</button>
        </form>
    <?php endif; ?>
</div>

<form class="pulse-card mb-4" method="GET" action="index.php">
    <input type="hidden" name="route" value="performance/index">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label text-secondary">Period</label>
            <select name="period" class="form-select bg-dark border-secondary text-white">
                <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">Start</label>
            <input type="date" name="start" value="<?php echo htmlspecialchars($start); ?>" class="form-control bg-dark border-secondary text-white">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">End</label>
            <input type="date" name="end" value="<?php echo htmlspecialchars($end); ?>" class="form-control bg-dark border-secondary text-white">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-light w-100">Apply</button>
        </div>
    </div>
</form>

<div class="row g-4">
    <div class="col-xl-<?php echo $is_admin ? '8' : '12'; ?>">
        <div class="pulse-card">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle table-stack">
                    <thead>
                        <tr class="text-secondary">
                            <th>Rank</th><th>Employee</th><th>Team</th><th>Overall</th><th>Band</th><th>Key Scores</th><th class="text-end">Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scores)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-secondary">No scores computed yet. Refresh scores to generate rankings.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($scores as $i => $score): ?>
                            <?php
                                $tone = [
                                    'excellent' => 'success',
                                    'good' => 'info',
                                    'average' => 'warning',
                                    'needs_attention' => 'danger',
                                ][$score->performance_band] ?? 'secondary';
                            ?>
                            <tr>
                                <td data-label="Rank"><span class="badge bg-secondary">#<?php echo $score->team_rank ?: ($i + 1); ?></span></td>
                                <td data-label="Employee" class="text-white fw-semibold"><?php echo htmlspecialchars($score->user_name); ?></td>
                                <td data-label="Team"><?php echo htmlspecialchars($score->team_name ?? 'No team'); ?></td>
                                <td data-label="Overall">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress bg-dark flex-grow-1" style="height:8px; min-width:100px;">
                                            <div class="progress-bar" style="width: <?php echo min(100, (float) $score->overall_score); ?>%; background: var(--primary);"></div>
                                        </div>
                                        <span class="text-white fw-semibold"><?php echo number_format((float) $score->overall_score, 1); ?></span>
                                    </div>
                                </td>
                                <td data-label="Band"><span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle"><?php echo strtoupper(str_replace('_', ' ', $score->performance_band)); ?></span></td>
                                <td data-label="Key Scores" class="text-secondary small">
                                    Target <?php echo number_format((float) $score->target_score, 0); ?> · Lead <?php echo number_format((float) $score->lead_score, 0); ?> · Follow-up <?php echo number_format((float) $score->followup_score, 0); ?>
                                </td>
                                <td data-label="Profile" class="text-end">
                                    <a class="btn btn-outline-info btn-sm" href="index.php?route=performance/profile/<?php echo $score->user_id; ?>&period=<?php echo urlencode($period); ?>"><i class="fa-solid fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <div class="col-xl-4">
        <div class="pulse-card">
            <h5 class="text-white mb-3">Scoring Weights</h5>
            <form action="index.php?route=performance/weights" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <?php foreach ($weights as $weight): ?>
                    <div class="mb-3">
                        <label class="form-label text-secondary"><?php echo htmlspecialchars($weight->label); ?></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" name="weights[<?php echo htmlspecialchars($weight->weight_key); ?>]" value="<?php echo htmlspecialchars($weight->weight_percent); ?>" class="form-control bg-dark border-secondary text-white">
                            <span class="input-group-text bg-dark border-secondary text-secondary">%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-primary w-100" style="background: var(--primary); border: none;">Save Weights</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
