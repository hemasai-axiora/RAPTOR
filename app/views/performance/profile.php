<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Performance Profile</h4>
        <div class="text-secondary" style="font-size:0.9rem;"><?php echo $score ? htmlspecialchars($score->user_name . ' - ' . strtoupper($score->period)) : 'No score available'; ?></div>
    </div>
    <a href="index.php?route=performance/index&period=<?php echo urlencode($period); ?>" class="btn btn-outline-light btn-sm">Back</a>
</div>

<?php if (!$score): ?>
    <div class="pulse-card text-center py-5 text-secondary">No performance score has been computed for this user yet.</div>
<?php else: ?>
<?php
$components = [
    'Attendance' => $score->attendance_score,
    'Punctuality' => $score->punctuality_score,
    'Activity' => $score->activity_score,
    'Targets' => $score->target_score,
    'Leads' => $score->lead_score,
    'Follow-ups' => $score->followup_score,
    'Conversions' => $score->conversion_score,
    'Revenue' => $score->revenue_score,
    'Meetings' => $score->meeting_score,
    'Demos' => $score->demo_score,
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
        <div class="pulse-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-secondary small">Overall Score</div>
                    <div class="display-5 text-white fw-bold"><?php echo number_format((float) $score->overall_score, 1); ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle fs-6">
                        <?php echo strtoupper(str_replace('_', ' ', $score->performance_band)); ?>
                    </span>
                    <div class="text-secondary small mt-2"><?php echo htmlspecialchars($score->start_date . ' to ' . $score->end_date); ?></div>
                    <div class="text-secondary small">Team rank #<?php echo htmlspecialchars($score->team_rank ?: '-'); ?></div>
                </div>
            </div>
        </div>

        <div class="pulse-card">
            <h5 class="text-white mb-3">Component Scores</h5>
            <?php foreach ($components as $label => $value): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-secondary"><?php echo htmlspecialchars($label); ?></span>
                        <span class="text-white"><?php echo number_format((float) $value, 1); ?></span>
                    </div>
                    <div class="progress bg-dark" style="height:8px;">
                        <div class="progress-bar" style="width: <?php echo min(100, (float) $value); ?>%; background: var(--primary);"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($can_review): ?>
        <div class="pulse-card mb-4">
            <h5 class="text-white mb-3">Manager Review</h5>
            <form action="index.php?route=performance/review/<?php echo $user_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($score->period); ?>">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($score->start_date); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($score->end_date); ?>">
                <div class="mb-3">
                    <label class="form-label text-secondary">Rating</label>
                    <input type="number" min="1" max="5" name="rating" class="form-control bg-dark border-secondary text-white" placeholder="1-5">
                </div>
                <div class="mb-3">
                    <label class="form-label text-secondary">Remarks</label>
                    <textarea name="remarks" rows="3" class="form-control bg-dark border-secondary text-white"></textarea>
                </div>
                <button class="btn btn-primary w-100" style="background: var(--primary); border: none;">Save Review</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="pulse-card">
            <h5 class="text-white mb-3">Review History</h5>
            <?php if (empty($reviews)): ?>
                <div class="text-secondary">No manager reviews yet.</div>
            <?php endif; ?>
            <?php foreach ($reviews as $review): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white"><?php echo htmlspecialchars($review->reviewer_name); ?> <?php echo $review->rating ? '- ' . (int) $review->rating . '/5' : ''; ?></div>
                    <div class="text-secondary small"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($review->created_at))); ?></div>
                    <div class="text-secondary small mt-1"><?php echo htmlspecialchars($review->remarks ?: 'No remarks'); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
