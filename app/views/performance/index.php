<?php
// Performance Ranking View
?>

<!-- Header Title & Refresh Action -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-primary, #0f172a);">
            🏆 Employee Performance Leaderboard
        </h3>
        <div class="text-secondary small">
            <i class="fa-solid fa-calendar-range me-1 text-primary"></i>
            <span class="fw-semibold text-uppercase"><?php echo htmlspecialchars($period); ?></span> Evaluation Period: 
            <span class="font-monospace fw-semibold" style="color: var(--text-primary, #0f172a);"><?php echo htmlspecialchars($start); ?></span> to 
            <span class="font-monospace fw-semibold" style="color: var(--text-primary, #0f172a);"><?php echo htmlspecialchars($end); ?></span>
        </div>
    </div>
    <?php if ($can_manage): ?>
        <form action="index.php?route=performance/recompute" method="POST" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
            <input type="hidden" name="start" value="<?php echo htmlspecialchars($start); ?>">
            <input type="hidden" name="end" value="<?php echo htmlspecialchars($end); ?>">
            <button type="submit" class="btn btn-primary btn-sm px-3 py-2 fw-semibold shadow-sm" style="background: var(--primary, #2563eb); border: none; border-radius: 10px;">
                <i class="fa-solid fa-rotate me-2"></i>Recalculate Scores
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<form class="pulse-card p-3 mb-4 shadow-sm" method="GET" action="index.php" style="background: var(--panel-dark, #ffffff); border-radius: 14px; border: 1px solid var(--border-color, #e2e8f0);">
    <input type="hidden" name="route" value="performance/index">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Evaluation Period</label>
            <select name="period" class="form-select">
                <option value="daily" <?php echo $period === 'daily' ? 'selected' : ''; ?>>📅 Daily</option>
                <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>📆 Weekly</option>
                <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>🗓️ Monthly</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">Start Date</label>
            <input type="date" name="start" value="<?php echo htmlspecialchars($start); ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary small fw-semibold">End Date</label>
            <input type="date" name="end" value="<?php echo htmlspecialchars($end); ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-outline-secondary w-100 fw-semibold" style="padding: 0.55rem 1rem;">
                🎯 Apply Filter
            </button>
        </div>
    </div>
</form>

<!-- Single Clean Leaderboard Table (All Members on 1 Page) & Scoring Weights Sidebar -->
<div class="row g-4">
    <div class="col-xl-<?php echo $is_admin ? '8' : '12'; ?>">
        <div class="pulse-card p-4 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                    📊 Employee Performance Leaderboard
                </h5>
                <span class="badge bg-secondary text-white font-monospace">
                    All Members: <?php echo count($scores); ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0 4px;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color, #e2e8f0); color: var(--text-secondary, #64748b);">
                            <th style="width: 120px; font-weight: 700; font-size: 1rem; padding: 12px 16px;">Rank</th>
                            <th style="font-weight: 700; font-size: 1rem; padding: 12px 16px;">Name</th>
                            <th style="width: 140px; font-weight: 700; font-size: 1rem; padding: 12px 16px;" class="text-end">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scores)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-secondary">
                                    No employee performance scores computed yet. Click <strong>Recalculate Scores</strong> to generate rankings.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($scores as $i => $score): ?>
                                <?php
                                    $rankNum = $i + 1;
                                    $rankLabel = '#' . $rankNum;
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color, #e2e8f0); background: transparent;">
                                    <td style="padding: 14px 16px; font-weight: 600; font-size: 1.05rem; color: var(--text-primary, #0f172a);">
                                        <?php echo $rankLabel; ?>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <div class="fw-semibold fs-6" style="color: var(--text-primary, #0f172a);">
                                            <?php echo htmlspecialchars($score->user_name); ?>
                                            <?php if (!empty($score->team_name)): ?>
                                                <span class="badge ms-2" style="background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 0.75rem;">
                                                    👥 <?php echo htmlspecialchars($score->team_name); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 16px;" class="text-end">
                                        <span class="fw-bold font-monospace fs-5" style="color: var(--text-primary, #0f172a);">
                                            <?php echo number_format((float)$score->overall_score, 1); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Admin Weight Configuration Sidebar -->
    <?php if ($is_admin): ?>
    <div class="col-xl-4">
        <div class="pulse-card p-3 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                ⚙️ Scoring Metric Weights
            </h5>
            <p class="text-secondary small mb-3">
                Adjust weight percentages for each performance component. Total weights should sum up to 100%.
            </p>

            <form action="index.php?route=performance/weights" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <?php foreach ($weights as $weight): ?>
                    <?php
                        $icon = [
                            'attendance' => '📅',
                            'punctuality' => '⏰',
                            'activity' => '⚡',
                            'target' => '🎯',
                            'lead' => '🧲',
                            'followup' => '📞',
                            'conversion' => '🚀',
                            'revenue' => '💰',
                            'meeting' => '🤝',
                            'demo' => '💻',
                        ][$weight->weight_key] ?? '📊';
                    ?>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold d-flex align-items-center gap-2 mb-1">
                            <span><?php echo $icon; ?></span> <?php echo htmlspecialchars($weight->label); ?>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" max="100" name="weights[<?php echo htmlspecialchars($weight->weight_key); ?>]" value="<?php echo htmlspecialchars($weight->weight_percent); ?>" class="form-control font-monospace">
                            <span class="input-group-text text-secondary">%</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary w-100 fw-semibold mt-2 py-2" style="background: var(--primary, #2563eb); border: none; border-radius: 10px;">
                    💾 Save Scoring Weights
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
