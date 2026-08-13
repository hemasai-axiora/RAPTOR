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

<!-- Company Top Performers Showcase (3 Side-by-Side Cards) -->
<?php if (!empty($scores)): ?>
    <div class="mb-4">
        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
            🌟 Company Top Performers Showcase
        </h5>
        <div class="row g-3">
            <!-- 🥇 #1 Rank Performer Card -->
            <?php if (isset($scores[0])): $p1 = $scores[0]; 
                $bandStyle1 = match($p1->performance_band) {
                    'excellent' => 'background: #10b981; color: #ffffff !important;',
                    'good' => 'background: #06b6d4; color: #ffffff !important;',
                    'average' => 'background: #f59e0b; color: #ffffff !important;',
                    default => 'background: #ef4444; color: #ffffff !important;',
                };
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pulse-card p-3 shadow-lg position-relative h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.04)); border: 2px solid #f59e0b; border-radius: 16px;">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge px-3 py-2 fw-bold text-dark" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); font-size: 0.82rem; border-radius: 8px;">
                                    🥇 #1 TOP PERFORMER
                                </span>
                                <span class="badge px-2 py-1 fw-bold" style="<?php echo $bandStyle1; ?> border-radius: 6px; font-size: 0.72rem;">
                                    <?php echo strtoupper(str_replace('_', ' ', $p1->performance_band)); ?>
                                </span>
                            </div>
                            <div class="my-3">
                                <div class="fs-5 fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                                    👑 <?php echo htmlspecialchars($p1->user_name); ?>
                                </div>
                                <span class="badge" style="background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 0.78rem;">
                                    👥 <?php echo htmlspecialchars($p1->team_name ?: 'No Team'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="border-top border-secondary border-opacity-20 pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-secondary small fw-semibold">Overall Score</span>
                                <span class="fs-4 fw-extrabold text-warning font-monospace"><?php echo number_format((float)$p1->overall_score, 1); ?> <small style="font-size:0.75rem;" class="text-secondary">/ 100</small></span>
                            </div>
                            <div class="progress bg-dark" style="height: 9px; border-radius: 6px;">
                                <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: <?php echo min(100, (float)$p1->overall_score); ?>%;"></div>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2 text-secondary" style="font-size: 0.76rem;">
                                <span>🎯 Target: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p1->target_score, 0); ?>%</strong></span>
                                <span>⚡ Activity: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p1->activity_score, 0); ?>%</strong></span>
                                <span>📞 Follow-up: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p1->followup_score, 0); ?>%</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 🥈 #2 Rank Performer Card -->
            <?php if (isset($scores[1])): $p2 = $scores[1]; 
                $bandStyle2 = match($p2->performance_band) {
                    'excellent' => 'background: #10b981; color: #ffffff !important;',
                    'good' => 'background: #06b6d4; color: #ffffff !important;',
                    'average' => 'background: #f59e0b; color: #ffffff !important;',
                    default => 'background: #ef4444; color: #ffffff !important;',
                };
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pulse-card p-3 shadow-lg position-relative h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, rgba(148, 163, 184, 0.15), rgba(71, 85, 105, 0.04)); border: 2px solid #94a3b8; border-radius: 16px;">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge px-3 py-2 fw-bold text-dark" style="background: linear-gradient(135deg, #cbd5e1, #94a3b8); font-size: 0.82rem; border-radius: 8px;">
                                    🥈 #2 RUNNER UP
                                </span>
                                <span class="badge px-2 py-1 fw-bold" style="<?php echo $bandStyle2; ?> border-radius: 6px; font-size: 0.72rem;">
                                    <?php echo strtoupper(str_replace('_', ' ', $p2->performance_band)); ?>
                                </span>
                            </div>
                            <div class="my-3">
                                <div class="fs-5 fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                                    🥈 <?php echo htmlspecialchars($p2->user_name); ?>
                                </div>
                                <span class="badge" style="background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 0.78rem;">
                                    👥 <?php echo htmlspecialchars($p2->team_name ?: 'No Team'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="border-top border-secondary border-opacity-20 pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-secondary small fw-semibold">Overall Score</span>
                                <span class="fs-4 fw-extrabold text-info font-monospace"><?php echo number_format((float)$p2->overall_score, 1); ?> <small style="font-size:0.75rem;" class="text-secondary">/ 100</small></span>
                            </div>
                            <div class="progress bg-dark" style="height: 9px; border-radius: 6px;">
                                <div class="progress-bar bg-info" style="width: <?php echo min(100, (float)$p2->overall_score); ?>%;"></div>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2 text-secondary" style="font-size: 0.76rem;">
                                <span>🎯 Target: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p2->target_score, 0); ?>%</strong></span>
                                <span>⚡ Activity: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p2->activity_score, 0); ?>%</strong></span>
                                <span>📞 Follow-up: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p2->followup_score, 0); ?>%</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 🥉 #3 Rank Performer Card -->
            <?php if (isset($scores[2])): $p3 = $scores[2]; 
                $bandStyle3 = match($p3->performance_band) {
                    'excellent' => 'background: #10b981; color: #ffffff !important;',
                    'good' => 'background: #06b6d4; color: #ffffff !important;',
                    'average' => 'background: #f59e0b; color: #ffffff !important;',
                    default => 'background: #ef4444; color: #ffffff !important;',
                };
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pulse-card p-3 shadow-lg position-relative h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, rgba(217, 119, 6, 0.12), rgba(180, 83, 9, 0.04)); border: 2px solid #b45309; border-radius: 16px;">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge px-3 py-2 fw-bold text-white" style="background: linear-gradient(135deg, #d97706, #b45309); font-size: 0.82rem; border-radius: 8px;">
                                    🥉 #3 THIRD PLACE
                                </span>
                                <span class="badge px-2 py-1 fw-bold" style="<?php echo $bandStyle3; ?> border-radius: 6px; font-size: 0.72rem;">
                                    <?php echo strtoupper(str_replace('_', ' ', $p3->performance_band)); ?>
                                </span>
                            </div>
                            <div class="my-3">
                                <div class="fs-5 fw-bold mb-1 d-flex align-items-center gap-2" style="color: var(--text-primary, #0f172a);">
                                    🥉 <?php echo htmlspecialchars($p3->user_name); ?>
                                </div>
                                <span class="badge" style="background: #2563eb; color: #ffffff !important; font-weight: 600; font-size: 0.78rem;">
                                    👥 <?php echo htmlspecialchars($p3->team_name ?: 'No Team'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="border-top border-secondary border-opacity-20 pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-secondary small fw-semibold">Overall Score</span>
                                <span class="fs-4 fw-extrabold text-warning font-monospace"><?php echo number_format((float)$p3->overall_score, 1); ?> <small style="font-size:0.75rem;" class="text-secondary">/ 100</small></span>
                            </div>
                            <div class="progress bg-dark" style="height: 9px; border-radius: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo min(100, (float)$p3->overall_score); ?>%;"></div>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2 text-secondary" style="font-size: 0.76rem;">
                                <span>🎯 Target: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p3->target_score, 0); ?>%</strong></span>
                                <span>⚡ Activity: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p3->activity_score, 0); ?>%</strong></span>
                                <span>📞 Follow-up: <strong style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$p3->followup_score, 0); ?>%</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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
                            <th style="width: 80px;">Rank</th>
                            <th>Employee Name</th>
                            <th>Team</th>
                            <th>Overall Score</th>
                            <th>Performance Band</th>
                            <th>Key Breakdown</th>
                            <th class="text-end">Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scores)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-secondary">
                                    No employee performance scores computed yet. Click <strong>Recalculate Scores</strong> to generate rankings.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($scores as $i => $score): ?>
                                <?php
                                    $rankNum = $i + 1;
                                    $bandStyle = match($score->performance_band) {
                                        'excellent' => 'background: #10b981; color: #ffffff !important;',
                                        'good' => 'background: #06b6d4; color: #ffffff !important;',
                                        'average' => 'background: #f59e0b; color: #ffffff !important;',
                                        default => 'background: #ef4444; color: #ffffff !important;',
                                    };

                                    $rankBadge = '<span class="badge bg-secondary">#' . $rankNum . '</span>';
                                    $rowStyle = '';
                                    if ($i === 0) {
                                        $rankBadge = '<span class="badge text-dark font-monospace fw-bold" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); font-size: 0.85rem;">🥇 #1</span>';
                                        $rowStyle = 'background: rgba(245, 158, 11, 0.08);';
                                    } elseif ($i === 1) {
                                        $rankBadge = '<span class="badge text-dark font-monospace fw-bold" style="background: linear-gradient(135deg, #cbd5e1, #94a3b8); font-size: 0.85rem;">🥈 #2</span>';
                                        $rowStyle = 'background: rgba(148, 163, 184, 0.06);';
                                    } elseif ($i === 2) {
                                        $rankBadge = '<span class="badge text-white font-monospace fw-bold" style="background: linear-gradient(135deg, #d97706, #b45309); font-size: 0.85rem;">🥉 #3</span>';
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
                                    <td>
                                        <div class="d-flex align-items-center gap-2" style="min-width: 140px;">
                                            <div class="progress bg-dark flex-grow-1" style="height: 10px; border-radius: 6px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo min(100, (float)$score->overall_score); ?>%;"></div>
                                            </div>
                                            <span class="fw-bold font-monospace" style="color: var(--text-primary, #0f172a);"><?php echo number_format((float)$score->overall_score, 1); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge px-2 py-1 fw-bold" style="<?php echo $bandStyle; ?> border-radius: 6px; font-size: 0.78rem;">
                                            <?php echo strtoupper(str_replace('_', ' ', $score->performance_band)); ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small">
                                        <div class="d-flex flex-wrap gap-2">
                                            <span title="Target Achievement">🎯 <?php echo number_format((float)$score->target_score, 0); ?>%</span>
                                            <span title="Activity Volume">⚡ <?php echo number_format((float)$score->activity_score, 0); ?>%</span>
                                            <span title="Follow-up Discipline">📞 <?php echo number_format((float)$score->followup_score, 0); ?>%</span>
                                            <span title="Lead Generation">🧲 <?php echo number_format((float)$score->lead_score, 0); ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($can_manage || (int)$score->user_id === (int)$_SESSION['user_id']): ?>
                                            <a class="btn btn-outline-info btn-sm px-3 py-1 fw-semibold" href="index.php?route=performance/profile/<?php echo $score->user_id; ?>&period=<?php echo urlencode($period); ?>" style="border-radius: 8px;">
                                                <i class="fa-solid fa-eye me-1"></i> View Profile
                                            </a>
                                        <?php else: ?>
                                            <span class="text-secondary small fst-italic" title="Profile viewing restricted">—</span>
                                        <?php endif; ?>
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
