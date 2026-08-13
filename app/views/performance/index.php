<?php
// Performance Ranking View
?>

<!-- Header Title & Refresh Action -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-primary, #0f172a);">
            🏆 Employee Performance Ranking & Leaderboard
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

<!-- Main Rankings Table & Scoring Weights Sidebar -->
<div class="row g-4">
    <div class="col-xl-<?php echo $is_admin ? '8' : '12'; ?>">
        <div class="pulse-card p-3 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                    📊 Employee Performance Leaderboard
                </h5>
                <span class="badge bg-secondary text-white font-monospace">
                    Total Evaluated: <?php echo count($scores); ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                        <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                            <th style="width: 100px;">Rank</th>
                            <th>Employee Name</th>
                            <th>Team</th>
                            <th class="text-end" style="width: 220px;">Overall Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scores)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-secondary">
                                    No employee performance scores computed yet. Click <strong>Recalculate Scores</strong> to generate rankings.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($scores as $i => $score): ?>
                                <?php
                                    $rankNum = $i + 1;
                                    $rankBadge = '<span class="badge bg-secondary font-monospace" style="font-size: 0.85rem;">#' . $rankNum . '</span>';
                                    $rowStyle = '';
                                    if ($i === 0) {
                                        $rankBadge = '<span class="badge text-dark font-monospace fw-bold" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); font-size: 0.88rem;">🥇 #1</span>';
                                        $rowStyle = 'background: rgba(245, 158, 11, 0.08);';
                                    } elseif ($i === 1) {
                                        $rankBadge = '<span class="badge text-dark font-monospace fw-bold" style="background: linear-gradient(135deg, #cbd5e1, #94a3b8); font-size: 0.88rem;">🥈 #2</span>';
                                        $rowStyle = 'background: rgba(148, 163, 184, 0.06);';
                                    } elseif ($i === 2) {
                                        $rankBadge = '<span class="badge text-white font-monospace fw-bold" style="background: linear-gradient(135deg, #d97706, #b45309); font-size: 0.88rem;">🥉 #3</span>';
                                        $rowStyle = 'background: rgba(180, 83, 9, 0.05);';
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color); <?php echo $rowStyle; ?>">
                                    <td><?php echo $rankBadge; ?></td>
                                    <td>
                                        <div class="fw-bold d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                                            <span>👤</span> <?php echo htmlspecialchars($score->user_name); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 0.8rem; padding: 0.35rem 0.65rem; border-radius: 6px;">
                                            👥 <?php echo htmlspecialchars($score->team_name ?: 'Unassigned'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-2" style="min-width: 160px;">
                                            <div class="progress bg-dark flex-grow-1" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo min(100, (float)$score->overall_score); ?>%;"></div>
                                            </div>
                                            <span class="fw-bold font-monospace fs-5" style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$score->overall_score, 1); ?></span>
                                        </div>
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
