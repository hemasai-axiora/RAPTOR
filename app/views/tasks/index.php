<?php
$columns = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$columnEmojis = ['pending' => '📋', 'in_progress' => '🚀', 'completed' => '🏆'];
$emptyEmojis = ['pending' => '🎯', 'in_progress' => '🛸', 'completed' => '🏆'];
$emptyMessages = [
    'pending' => 'All caught up! No pending tasks in queue.',
    'in_progress' => 'No tasks currently in progress.',
    'completed' => 'No completed tasks recorded yet.'
];

$grouped = ['pending' => [], 'in_progress' => [], 'completed' => []];
foreach ($tasks as $task) {
    $grouped[$task->status][] = $task;
}
$completionPct = $metrics['total'] > 0 ? round(($metrics['approved'] / $metrics['total']) * 100) : 0;

if (!function_exists('getInitialsBadge')) {
    function getInitialsBadge($name) {
        $words = explode(' ', trim($name));
        $in = '';
        foreach ($words as $w) { if (!empty($w)) $in .= strtoupper($w[0]); }
        return substr($in, 0, 2) ?: 'U';
    }
}
?>

<style>
/* Modern Operations Task Board UI Styling */
.task-board-header {
    font-family: 'Poppins', 'Inter', system-ui, sans-serif;
}
.stat-card {
    background: var(--panel-dark, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    transition: all 0.22s ease-in-out;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12) !important;
}

.kanban-column-card {
    background: var(--surface-soft, #f8fafc);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1.25rem;
    min-height: 520px;
}

.task-card {
    background: var(--panel-dark, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 1.15rem;
    transition: all 0.22s ease-in-out;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
}
.task-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(37, 99, 235, 0.15) !important;
}

/* Priority Color-Coded Accent Borders */
.task-card.priority-high {
    border-left: 5px solid #ef4444 !important;
}
.task-card.priority-medium {
    border-left: 5px solid #f59e0b !important;
}
.task-card.priority-low {
    border-left: 5px solid #10b981 !important;
}

/* Pill Progress Bar */
.task-progress-track {
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
[data-theme="dark"] .task-progress-track {
    background: rgba(255, 255, 255, 0.12);
}
.task-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease;
}

/* Assignee Avatar Circle */
.avatar-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}

.empty-kanban-state {
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--text-secondary, #64748b);
    border: 2px dashed var(--border-color, #cbd5e1);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.5);
}
[data-theme="dark"] .empty-kanban-state {
    background: rgba(0, 0, 0, 0.2);
}

/* Form input styling for theme adaptability */
.task-card .form-control, .task-card .form-select {
    background-color: var(--surface-soft, #f8fafc) !important;
    border-color: var(--border-color, #cbd5e1) !important;
    color: var(--text-primary, #0f172a) !important;
}
</style>

<?php if (!empty($_SESSION['task_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['task_error']); unset($_SESSION['task_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['task_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['task_success']); unset($_SESSION['task_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Header Title & Action -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 task-board-header">
    <div>
        <h3 class="mb-1 fw-bold" style="color: var(--text-primary, #0f172a);">🦅 Operations Task Board</h3>
        <p class="text-secondary mb-0" style="font-size:0.9rem;">
            Assign team tasks, track process stages, log execution hours, and review deliverables.
        </p>
    </div>
    <?php if ($can_assign): ?>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-danger px-3 py-2 fw-semibold shadow-sm" id="btn-bulk-delete-tasks" style="display: none; border-radius: 10px;">
                🗑️ Delete Selected (<span id="selected-tasks-count">0</span>)
            </button>
            <button type="button" class="btn btn-outline-danger px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#deleteByEmployeeModal" style="border-radius: 10px;">
                👤 Delete Tasks by Employee
            </button>
            <button class="btn btn-primary px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal" style="background: var(--primary, #2563eb); border: none; border-radius: 10px;">
                ➕ Assign Task
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Stat Cards Overview -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card d-flex align-items-center shadow-sm">
            <div class="rounded-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #10b981, #059669); width: 46px; height: 46px; color: #fff; font-size: 1.35rem;">
                🏆
            </div>
            <div>
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size:0.75rem;">Approved Tasks</span>
                <h4 class="mb-0 mt-1 fw-bold text-success"><?php echo $metrics['approved']; ?> <span class="fs-6 text-secondary font-monospace">/ <?php echo $metrics['total']; ?></span></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card d-flex align-items-center shadow-sm">
            <div class="rounded-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); width: 46px; height: 46px; color: #fff; font-size: 1.35rem;">
                ⚡
            </div>
            <div>
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size:0.75rem;">Completion Rate</span>
                <h4 class="mb-0 mt-1 fw-bold text-primary"><?php echo $completionPct; ?>%</h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card d-flex align-items-center shadow-sm">
            <div class="rounded-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="background: linear-gradient(135deg, #f59e0b, #d97706); width: 46px; height: 46px; color: #fff; font-size: 1.35rem;">
                ⏩
            </div>
            <div>
                <span class="text-secondary small fw-semibold text-uppercase" style="font-size:0.75rem;">Carried Forward</span>
                <h4 class="mb-0 mt-1 fw-bold" style="color: #d97706;"><?php echo $metrics['carried']; ?> <span class="fs-6 text-secondary">Tasks</span></h4>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="index.php" class="pulse-card p-3 mb-4 shadow-sm" style="background: var(--panel-dark, #ffffff); border-radius: 14px; border: 1px solid var(--border-color, #e2e8f0);">
    <input type="hidden" name="route" value="tasks/index">
    <div class="row g-3 align-items-end">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-3">
                <label class="form-label text-secondary small fw-semibold">Owner / Employee</label>
                <select name="assigned_to_user_id" class="form-select">
                    <option value="">👤 All Owners</option>
                    <?php foreach ($assignees as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-secondary small fw-semibold">Assignee Team</label>
                <select name="team_id" class="form-select">
                    <option value="">👥 All Teams</option>
                    <?php if (!empty($teams)): ?>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t->team_id; ?>" <?php echo (string) ($filters['team_id'] ?? '') === (string) $t->team_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-<?php echo Policy::isEmployee() ? '6' : '3'; ?>">
            <label class="form-label text-secondary small fw-semibold">Review Status</label>
            <select name="review_status" class="form-select">
                <option value="">🔍 All Statuses</option>
                <?php foreach ($review_statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['review_status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-<?php echo Policy::isEmployee() ? '6' : '3'; ?>">
            <button class="btn btn-primary w-100 fw-semibold" type="submit" style="background: var(--primary, #2563eb); border: none; padding: 0.55rem 1rem;">
                🎯 Filter Tasks
            </button>
        </div>
    </div>
</form>

<!-- 3-Column Kanban Board -->
<div class="row g-4">
    <?php foreach ($columns as $status => $label): ?>
        <div class="col-lg-4">
            <div class="kanban-column-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 border-secondary border-opacity-10">
                    <h5 class="mb-0 fw-bold" style="color: var(--text-primary, #0f172a);">
                        <?php echo $columnEmojis[$status]; ?> <?php echo htmlspecialchars($label); ?>
                    </h5>
                    <span class="badge px-3 py-1 rounded-pill fw-bold shadow-sm" style="background: #2563eb; color: #ffffff;">
                        <?php echo count($grouped[$status]); ?>
                    </span>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php if (empty($grouped[$status])): ?>
                        <div class="empty-kanban-state">
                            <div style="font-size: 2.2rem;" class="mb-2"><?php echo $emptyEmojis[$status]; ?></div>
                            <div class="fw-semibold text-secondary small"><?php echo $emptyMessages[$status]; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($grouped[$status] as $task): ?>
                        <?php
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
                        <div class="task-card <?php echo $priorityClass; ?> d-flex flex-column gap-2">
                            
                            <!-- Card Header Badges & Actions -->
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($can_assign): ?>
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
                                    <?php if ($can_assign): ?>
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
                            <div class="fw-bold fs-6 mt-1" style="color: var(--text-primary, #0f172a);"><?php echo htmlspecialchars($task->title); ?></div>
                            <p class="text-secondary mb-1" style="font-size:0.86rem; line-height: 1.4;"><?php echo htmlspecialchars($task->description ?: 'No description provided.'); ?></p>

                            <!-- Gradient Progress Bar -->
                            <div class="mt-1">
                                <div class="d-flex justify-content-between text-secondary small fw-semibold mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $pct; ?>%</span>
                                </div>
                                <div class="task-progress-track">
                                    <div class="task-progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $fillGradient; ?>;"></div>
                                </div>
                            </div>

                            <!-- Assignee & Details Row -->
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-2 pt-2 border-top border-secondary border-opacity-10">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($task->assignee_name)): ?>
                                        <div class="avatar-circle" title="Owner: <?php echo htmlspecialchars($task->assignee_name); ?>">
                                            <?php echo getInitialsBadge($task->assignee_name); ?>
                                        </div>
                                        <span class="text-secondary small fw-semibold" title="Assigned Owner"><?php echo htmlspecialchars($task->assignee_name); ?></span>
                                    <?php elseif (!empty($task->team_name)): ?>
                                        <div class="avatar-circle" style="background: linear-gradient(135deg, #059669, #10b981);" title="Team: <?php echo htmlspecialchars($task->team_name); ?>">
                                            👥
                                        </div>
                                        <span class="text-secondary small fw-semibold" title="Assigned Team"><?php echo htmlspecialchars($task->team_name); ?></span>
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
                                    <span class="badge bg-primary bg-opacity-15 text-primary border border-primary border-opacity-20 px-2 py-1">
                                        👥 Team: <?php echo htmlspecialchars($task->team_name); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Celebratory Banners -->
                            <?php if ($task->status === 'completed' && $task->review_status === 'approved'): ?>
                                <div class="alert alert-success border-0 py-2 px-3 my-1 rounded-3 text-success d-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.12); font-size: 0.82rem; font-weight: 600;">
                                    <span>🎊</span> Nice work — this task is completed & approved!
                                </div>
                            <?php elseif ($task->review_status === 'pending_review'): ?>
                                <div class="alert alert-warning border-0 py-2 px-3 my-1 rounded-3 text-warning d-flex align-items-center gap-2" style="background: rgba(245, 158, 11, 0.12); font-size: 0.82rem; font-weight: 600;">
                                    <span>👀</span> Work submitted — waiting for manager review!
                                </div>
                            <?php endif; ?>

                            <!-- Proof Link & Remarks -->
                            <?php if ($task->proof_url): ?>
                                <a class="btn btn-outline-primary btn-sm my-1" href="index.php?route=file/show&key=<?php echo urlencode($task->proof_url); ?>" target="_blank">
                                    📎 View Proof Document
                                </a>
                            <?php endif; ?>

                            <?php if ($task->review_remark): ?>
                                <div class="text-secondary small fst-italic">💬 Review Note: <?php echo htmlspecialchars($task->review_remark); ?></div>
                            <?php endif; ?>

                            <!-- In-Progress Form Actions -->
                            <?php if ($task->status !== 'completed'): ?>
                                <form action="index.php?route=tasks/progress/<?php echo $task->task_id; ?>" method="POST" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <div class="row g-2">
                                        <div class="col-5">
                                            <input type="number" min="0" max="100" name="progress_percent" class="form-control form-control-sm" value="<?php echo (int) $task->progress_percent; ?>" title="Progress %" placeholder="% Progress">
                                        </div>
                                        <div class="col-5">
                                            <input type="number" min="0" step="0.25" name="actual_hours" class="form-control form-control-sm" value="<?php echo htmlspecialchars($task->actual_hours ?? '0.00'); ?>" title="Actual hours" placeholder="Hours">
                                        </div>
                                        <div class="col-2">
                                            <button class="btn btn-outline-primary btn-sm w-100" title="Save progress">💾</button>
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?php echo htmlspecialchars($task->remarks ?? ''); ?>" placeholder="Progress remarks...">
                                        </div>
                                    </div>
                                </form>

                                <form action="index.php?route=tasks/complete/<?php echo $task->task_id; ?>" method="POST" enctype="multipart/form-data" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <div class="mb-2">
                                        <label class="form-label text-secondary small fw-semibold mb-1">Attach Proof Document</label>
                                        <input type="file" name="proof" class="form-control form-control-sm" accept="image/*,.pdf" <?php echo empty($task->proof_url) ? 'required' : ''; ?>>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-5">
                                            <input type="number" min="0" step="0.25" name="actual_hours" class="form-control form-control-sm" value="<?php echo htmlspecialchars($task->actual_hours ?? '0.00'); ?>" placeholder="Hours">
                                        </div>
                                        <div class="col-7">
                                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?php echo htmlspecialchars($task->remarks ?? ''); ?>" placeholder="Completion note...">
                                        </div>
                                    </div>
                                    <button class="btn btn-success btn-sm w-100 mt-2 fw-semibold" style="background: #10b981; border: none;">🎉 Submit Complete</button>
                                </form>
                            <?php endif; ?>

                            <!-- Manager Review Actions -->
                            <?php if ($can_review && $task->review_status === 'pending_review'): ?>
                                <form action="index.php?route=tasks/review/<?php echo $task->task_id; ?>" method="POST" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="text" name="review_remark" class="form-control form-control-sm mb-2" placeholder="Review remark / feedback...">
                                    <div class="d-flex gap-2">
                                        <button name="decision" value="approved" class="btn btn-success btn-sm flex-fill fw-semibold">👍 Approve</button>
                                        <button name="decision" value="rejected" class="btn btn-danger btn-sm flex-fill fw-semibold">👎 Reject</button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- Quick Move Buttons -->
                            <?php if ($can_assign): ?>
                                <div class="d-flex gap-2 border-top border-secondary border-opacity-10 pt-2 mt-1 align-items-center">
                                    <span class="text-secondary small me-1">Move:</span>
                                    <?php foreach (['pending', 'in_progress', 'completed'] as $target): ?>
                                        <?php if ($target === $task->status) { continue; } ?>
                                            <button class="btn btn-outline-secondary btn-sm btn-move px-2 py-0" data-id="<?php echo $task->task_id; ?>" data-status="<?php echo $target; ?>" title="Move to <?php echo htmlspecialchars($target); ?>">
                                                <?php echo $columnEmojis[$target]; ?> <?php echo strtoupper(substr($target, 0, 1)); ?>
                                            </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Assign Task Modal -->
<?php if ($can_assign): ?>
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: var(--panel-dark, #ffffff); border-radius: 16px;">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold" id="addTaskModalLabel">➕ Assign New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=tasks/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Task Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Task title / objective...">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Assign to Team</label>
                            <select name="team_id" class="form-select">
                                <option value="">Select Team (Optional)</option>
                                <?php if (!empty($teams)): ?>
                                    <?php foreach ($teams as $t): ?>
                                        <option value="<?php echo $t->team_id; ?>"><?php echo htmlspecialchars($t->name); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Assign Employee Owner</label>
                            <select name="assigned_to_user_id" class="form-select">
                                <option value="">Select Employee (Optional)</option>
                                <?php foreach ($assignees as $user): ?>
                                    <option value="<?php echo $user->user_id; ?>"><?php echo htmlspecialchars($user->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Start Date & Time</label>
                            <input type="datetime-local" name="start_date" class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Deadline *</label>
                            <input type="datetime-local" name="deadline" class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">🌱 LOW</option>
                                <option value="medium" selected>⚡ MEDIUM</option>
                                <option value="high">🔥 HIGH</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Estimated Hours</label>
                            <input type="number" min="0" step="0.25" name="estimated_hours" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Provide detailed task requirements..."></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary small fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Additional notes or references..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" style="background: var(--primary, #2563eb); border: none;">➕ Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Delete Tasks by Employee Modal -->
<div class="modal fade" id="deleteByEmployeeModal" tabindex="-1" aria-labelledby="deleteByEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: var(--panel-dark, #ffffff); border-radius: 16px;">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold text-danger" id="deleteByEmployeeModalLabel">👤 Delete Tasks by Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=tasks/deleteByEmployee" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL tasks assigned to the selected employee? This action cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="alert alert-warning border-0 py-2 px-3 mb-3 rounded-3 text-warning" style="background: rgba(245, 158, 11, 0.12); font-size: 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Selecting an employee will permanently remove all tasks assigned to them from the task board.
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Select Employee *</label>
                        <select name="employee_user_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($assignees as $user): ?>
                                <option value="<?php echo $user->user_id; ?>"><?php echo htmlspecialchars($user->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">🗑️ Delete All Employee Tasks</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Delete Selected Tasks Modal -->
<div class="modal fade" id="bulkDeleteTasksModal" tabindex="-1" aria-labelledby="bulkDeleteTasksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: var(--panel-dark, #ffffff); border-radius: 16px;">
            <div class="modal-header border-bottom border-secondary border-opacity-10">
                <h5 class="modal-title fw-bold text-danger" id="bulkDeleteTasksModalLabel">🗑️ Delete Selected Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=tasks/deleteMultiple" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div id="bulk_task_ids_container"></div>
                <div class="modal-body">
                    <div class="alert alert-danger border-0 py-2 px-3 mb-3 rounded-3 text-danger" style="background: rgba(239, 68, 68, 0.12); font-size: 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Are you sure you want to delete <strong id="modal_bulk_task_count">0</strong> selected task(s)? This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">🗑️ Confirm & Delete Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(function() {
    $(document).on('click', '.btn-move', function() {
        $.post('index.php?route=tasks/updateStatus', {
            task_id: $(this).data('id'),
            status: $(this).data('status'),
            csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
        }, function(res) {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.message || 'Failed to update task status.');
            }
        }, 'json');
    });

    $(document).on('change', '.task-select-checkbox', function() {
        const checkedCount = $('.task-select-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#selected-tasks-count').text(checkedCount);
            $('#btn-bulk-delete-tasks').fadeIn(150);
        } else {
            $('#btn-bulk-delete-tasks').fadeOut(150);
        }
    });

    $('#btn-bulk-delete-tasks').on('click', function() {
        const selectedCheckboxes = $('.task-select-checkbox:checked');
        if (selectedCheckboxes.length === 0) return;

        let container = $('#bulk_task_ids_container').empty();
        selectedCheckboxes.each(function() {
            container.append('<input type="hidden" name="task_ids[]" value="' + $(this).val() + '">');
        });

        $('#modal_bulk_task_count').text(selectedCheckboxes.length);
        $('#bulkDeleteTasksModal').modal('show');
    });
});
</script>
