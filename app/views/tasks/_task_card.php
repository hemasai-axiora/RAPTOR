<?php
// Task Card Partial View
$p = strtolower($task->priority);
$priorityClass = 'priority-' . ($p === 'high' ? 'high' : ($p === 'medium' ? 'medium' : 'low'));

$priorityLabel = [
    'high' => '🔥 HIGH',
    'medium' => '⚡ MEDIUM',
    'low' => '🌱 LOW'
][$p] ?? '🌱 LOW';

$reviewTone = [
    'not_submitted' => 'secondary',
    'pending_review' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
][$task->review_status] ?? 'secondary';

$reviewEmoji = [
    'not_submitted' => '⏳',
    'pending_review' => '👀',
    'approved' => '✅',
    'rejected' => '❌',
][$task->review_status] ?? '⏳';

$pct = (int) $task->progress_percent;
$fillGradient = ($pct >= 100 || $task->status === 'completed') ? 'linear-gradient(90deg, #10b981, #06b6d4)' : 'linear-gradient(90deg, #f59e0b, #10b981)';
?>

<div class="task-card <?php echo $priorityClass; ?> d-flex flex-column gap-2 shadow-sm">
    <!-- Card Header Badges & Actions -->
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($can_delete)): ?>
                <input type="checkbox" class="form-check-input task-select-checkbox me-1" value="<?php echo $task->task_id; ?>" title="Select task for bulk delete">
            <?php endif; ?>
            <span class="badge px-2 py-1 fw-bold" style="<?php 
                if ($p === 'high') echo 'background: rgba(239,68,68,0.15); color: #dc2626; border: 1px solid rgba(239,68,68,0.3);';
                elseif ($p === 'medium') echo 'background: rgba(245,158,11,0.15); color: #d97706; border: 1px solid rgba(245,158,11,0.3);';
                else echo 'background: rgba(16,185,129,0.15); color: #059669; border: 1px solid rgba(16,185,129,0.3);';
            ?>">
                <?php echo $priorityLabel; ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-1">
            <?php if ($task->is_carry_forward): ?>
                <span class="badge px-2 py-1 fw-bold" style="background: rgba(245, 158, 11, 0.2); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.4);">⏩ CARRY FORWARD</span>
            <?php endif; ?>
            <?php if (!empty($can_delete)): ?>
                <form action="index.php?route=tasks/delete/<?php echo $task->task_id; ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete task #<?php echo $task->task_id; ?> (<?php echo htmlspecialchars(addslashes($task->title)); ?>)?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <button type="submit" class="btn btn-link text-danger p-0 ms-1" style="font-size: 0.9rem;" title="Delete Task">
                        🗑️
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Title & Description -->
    <div>
        <h6 class="fw-bold mb-1" style="color: var(--text-primary, #0f172a); font-size: 0.98rem;">
            <?php echo htmlspecialchars($task->title); ?>
        </h6>
        <?php if (!empty($task->description)): ?>
            <?php
                $cleanDesc = html_entity_decode($task->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            ?>
            <p class="text-secondary small mb-1" style="font-size: 0.83rem; line-height: 1.35;">
                <?php echo nl2br(htmlspecialchars(trim($cleanDesc))); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Progress Track Bar -->
    <div class="my-1">
        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 0.76rem;">
            <span class="text-secondary fw-semibold">Progress</span>
            <span class="fw-bold font-monospace" style="color: var(--text-primary, #0f172a);"><?php echo $pct; ?>%</span>
        </div>
        <div class="task-progress-track">
            <div class="task-progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $fillGradient; ?>;"></div>
        </div>
    </div>

    <!-- Assignee & Deadline Info -->
    <div class="d-flex justify-content-between align-items-center pt-1 border-top border-secondary border-opacity-10 mt-1">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($task->assignee_name)): ?>
                <div class="avatar-circle" title="Owner: <?php echo htmlspecialchars($task->assignee_name); ?>">
                    <?php echo getInitialsBadge($task->assignee_name); ?>
                </div>
                <span class="fw-semibold small" style="color: var(--text-primary, #0f172a);" title="Assigned Owner"><?php echo htmlspecialchars($task->assignee_name); ?></span>
            <?php elseif (!empty($task->team_name)): ?>
                <div class="avatar-circle" style="background: linear-gradient(135deg, #059669, #10b981);" title="Team: <?php echo htmlspecialchars($task->team_name); ?>">
                    👥
                </div>
                <span class="fw-semibold small" style="color: var(--text-primary, #0f172a);" title="Assigned Team"><?php echo htmlspecialchars($task->team_name); ?></span>
            <?php else: ?>
                <span class="text-secondary small fst-italic">Unassigned</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-1 text-secondary small">
            <span>📅 <?php echo htmlspecialchars(date('M d', strtotime($task->deadline))); ?></span>
        </div>
    </div>

    <!-- Review Status Chip & Team Badge -->
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge px-2 py-1 fw-semibold bg-<?php echo $reviewTone; ?>-subtle text-<?php echo $reviewTone; ?> border border-<?php echo $reviewTone; ?>-subtle">
            <?php echo $reviewEmoji; ?> <?php echo strtoupper(str_replace('_', ' ', $task->review_status)); ?>
        </span>
        <?php if (!empty($task->team_name) && !empty($task->assignee_name)): ?>
            <span class="badge px-2 py-1 fw-bold" style="background: #2563eb; color: #ffffff !important; font-size: 0.78rem;">
                👥 Team: <?php echo htmlspecialchars($task->team_name); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Proof Document Link -->
    <?php if (!empty($task->proof_url)): ?>
        <?php
            $proofUrl = trim($task->proof_url);
            if (strpos($proofUrl, 'http://') !== 0 && strpos($proofUrl, 'https://') !== 0) {
                $proofHref = 'index.php?route=file/show&key=' . urlencode($proofUrl);
            } else {
                $proofHref = $proofUrl;
            }
        ?>
        <div class="mt-1">
            <a href="<?php echo htmlspecialchars($proofHref); ?>" target="_blank" class="btn btn-outline-info btn-sm w-100 fw-semibold" style="font-size: 0.78rem;">
                📄 View Proof Document
            </a>
        </div>
    <?php endif; ?>

    <!-- Review Form / Actions -->
    <?php if ($task->status === 'completed'): ?>
        <?php if (!empty($can_review)): ?>
            <form action="index.php?route=tasks/review/<?php echo $task->task_id; ?>" method="POST" class="mt-2 p-2 rounded-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color, #e2e8f0);">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="mb-2">
                    <input type="text" name="review_remark" class="form-control form-control-sm" placeholder="Review remark / feedback..." value="<?php echo htmlspecialchars($task->review_remark ?? ''); ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="review_action" value="approve" class="btn btn-success btn-sm flex-grow-1 fw-bold">
                        🔥 Approve
                    </button>
                    <button type="submit" name="review_action" value="reject" class="btn btn-danger btn-sm flex-grow-1 fw-bold">
                        🥊 Reject
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Move Buttons -->
    <div class="d-flex justify-content-between align-items-center pt-2 mt-1 border-top border-secondary border-opacity-10">
        <span class="text-secondary small fw-semibold">Move:</span>
        <div class="btn-group btn-group-sm">
            <?php if ($task->status !== 'pending'): ?>
                <button type="button" class="btn btn-outline-secondary btn-move" data-id="<?php echo $task->task_id; ?>" data-status="pending" title="Move to Pending">
                    📋 P
                </button>
            <?php endif; ?>
            <?php if ($task->status !== 'in_progress'): ?>
                <button type="button" class="btn btn-outline-primary btn-move" data-id="<?php echo $task->task_id; ?>" data-status="in_progress" title="Move to In Progress">
                    🚀 I
                </button>
            <?php endif; ?>
            <?php if ($task->status !== 'completed'): ?>
                <button type="button" class="btn btn-outline-success btn-move" data-id="<?php echo $task->task_id; ?>" data-status="completed" title="Move to Completed">
                    🏆 C
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
