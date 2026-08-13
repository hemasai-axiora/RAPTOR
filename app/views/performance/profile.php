<?php
// Performance Profile View
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-primary, #0f172a);">
            👤 Employee Performance Profile
        </h3>
        <div class="text-secondary small">
            <?php echo $score ? htmlspecialchars($score->user_name . ' — ' . strtoupper($score->period) . ' Evaluation') : 'No evaluation data available'; ?>
        </div>
    </div>
    <a href="index.php?route=performance/index&period=<?php echo urlencode($period); ?>" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold" style="border-radius: 10px;">
        ← Back to Leaderboard
    </a>
</div>

<?php if (!$score): ?>
    <div class="pulse-card text-center py-5 text-secondary" style="background: var(--panel-dark, #ffffff); border-radius: 16px;">
        <i class="fa-solid fa-chart-line fs-1 text-secondary mb-3 d-block"></i>
        No performance score has been computed for this user yet.
    </div>
<?php else: ?>
<?php
$components = [
    'Attendance' => ['score' => $score->attendance_score, 'icon' => '📅', 'desc' => 'Days Present vs Total Workdays'],
    'Punctuality' => ['score' => $score->punctuality_score, 'icon' => '⏰', 'desc' => 'On-Time Check-ins vs Days Present'],
    'Activity Volume' => ['score' => $score->activity_score, 'icon' => '⚡', 'desc' => 'Logged Comms, Tasks, Follow-ups & Lead Actions'],
    'Target Achievement' => ['score' => $score->target_score, 'icon' => '🎯', 'desc' => 'Average Target Completion Progress'],
    'Lead Generation' => ['score' => $score->lead_score, 'icon' => '🧲', 'desc' => 'New Leads Acquired in Period'],
    'Follow-up Discipline' => ['score' => $score->followup_score, 'icon' => '📞', 'desc' => 'Completed/Scheduled Follow-ups Ratio'],
    'Conversions' => ['score' => $score->conversion_score, 'icon' => '🚀', 'desc' => 'Converted Leads Count'],
    'Revenue' => ['score' => $score->revenue_score, 'icon' => '💰', 'desc' => 'Converted Lead Value Generated'],
    'Meetings' => ['score' => $score->meeting_score, 'icon' => '🤝', 'desc' => 'Completed Client Meetings'],
    'Demos' => ['score' => $score->demo_score, 'icon' => '💻', 'desc' => 'Completed Product Demos'],
];

$tone = [
    'excellent' => 'success',
    'good' => 'info',
    'average' => 'warning',
    'needs_attention' => 'danger',
][$score->performance_band] ?? 'secondary';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Overview Card -->
        <div class="pulse-card p-4 mb-4 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fs-4">👤</span>
                        <h4 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($score->user_name); ?></h4>
                        <span class="badge" style="background: #2563eb; color: #ffffff; font-weight: 600; font-size: 0.85rem; padding: 0.35rem 0.65rem; border-radius: 6px;">
                            👥 <?php echo htmlspecialchars($score->team_name ?? 'Unassigned'); ?>
                        </span>
                    </div>
                    <div class="text-secondary small">
                        Evaluation Window: <span class="text-white font-monospace"><?php echo htmlspecialchars($score->start_date . ' to ' . $score->end_date); ?></span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-secondary small fw-semibold">Overall Rating</div>
                    <div class="display-6 text-white fw-extrabold font-monospace my-1">
                        <?php echo number_format((float)$score->overall_score, 1); ?> <small style="font-size:1rem;" class="text-secondary">/ 100</small>
                    </div>
                    <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle fs-6 px-3 py-1 fw-bold">
                        <?php echo strtoupper(str_replace('_', ' ', $score->performance_band)); ?>
                    </span>
                    <div class="text-secondary small mt-2">
                        🏆 Team Rank <strong class="text-white">#<?php echo htmlspecialchars($score->team_rank ?: '-'); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Component Scores Grid -->
        <div class="pulse-card p-4 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <h5 class="fw-bold mb-4 text-white d-flex align-items-center gap-2">
                📊 Detailed Performance Components
            </h5>
            <div class="row g-3">
                <?php foreach ($components as $label => $item): ?>
                    <?php
                        $val = (float) $item['score'];
                        $compTone = $val >= 85 ? 'success' : ($val >= 70 ? 'info' : ($val >= 50 ? 'warning' : 'danger'));
                    ?>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color, #334155);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-white d-flex align-items-center gap-2">
                                    <span><?php echo $item['icon']; ?></span> <?php echo htmlspecialchars($label); ?>
                                </span>
                                <span class="fw-bold font-monospace text-<?php echo $compTone; ?>">
                                    <?php echo number_format($val, 1); ?>%
                                </span>
                            </div>
                            <div class="text-secondary small mb-2" style="font-size: 0.76rem;"><?php echo htmlspecialchars($item['desc']); ?></div>
                            <div class="progress bg-dark" style="height: 8px; border-radius: 4px;">
                                <div class="progress-bar bg-<?php echo $compTone; ?>" style="width: <?php echo min(100, $val); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Manager Review & History Sidebar -->
    <div class="col-lg-4">
        <?php if ($can_review): ?>
        <div class="pulse-card p-3 mb-4 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <h5 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                ✍️ Add Manager Review
            </h5>
            <form action="index.php?route=performance/review/<?php echo $user_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($score->period); ?>">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($score->start_date); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($score->end_date); ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Rating (1 to 5 Stars) *</label>
                    <select name="rating" class="form-select bg-dark border-secondary text-white" required>
                        <option value="5">⭐⭐⭐⭐⭐ 5 - Exceptional</option>
                        <option value="4" selected>⭐⭐⭐⭐ 4 - Exceeds Expectations</option>
                        <option value="3">⭐⭐⭐ 3 - Meets Expectations</option>
                        <option value="2">⭐⭐ 2 - Needs Improvement</option>
                        <option value="1">⭐ 1 - Unsatisfactory</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Review Remarks / Feedback</label>
                    <textarea name="remarks" rows="3" class="form-control bg-dark border-secondary text-white" placeholder="Write feedback or guidance for employee..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold py-2" style="background: var(--primary, #2563eb); border: none; border-radius: 10px;">
                    💾 Save Manager Review
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="pulse-card p-3 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #e2e8f0);">
            <h5 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                📝 Review History
            </h5>
            <?php if (empty($reviews)): ?>
                <div class="text-secondary small text-center py-3">No manager reviews recorded yet.</div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-white fw-semibold">👤 <?php echo htmlspecialchars($review->reviewer_name); ?></span>
                            <?php if ($review->rating): ?>
                                <span class="badge bg-warning bg-opacity-20 text-warning">
                                    <?php echo str_repeat('⭐', (int)$review->rating); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-secondary small" style="font-size:0.75rem;">
                            <?php echo htmlspecialchars(formatToLocalTime($review->created_at, 'M d, Y h:i A')); ?>
                        </div>
                        <p class="text-white small mb-0 mt-2 p-2 rounded-2" style="background: rgba(255,255,255,0.03);">
                            <?php echo htmlspecialchars($review->remarks ?: 'No additional remarks provided.'); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
