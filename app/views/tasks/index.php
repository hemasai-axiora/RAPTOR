<?php
$columns = ['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
$grouped = ['pending' => [], 'in_progress' => [], 'completed' => []];
foreach ($tasks as $task) {
    $grouped[$task->status][] = $task;
}
$completionPct = $metrics['total'] > 0 ? round(($metrics['approved'] / $metrics['total']) * 100) : 0;
?>

<?php if (!empty($_SESSION['task_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['task_error']); unset($_SESSION['task_error']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['task_success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['task_success']); unset($_SESSION['task_success']); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-1">Operations Task Board</h4>
        <div class="text-secondary" style="font-size:0.9rem;">
            <?php echo $metrics['approved']; ?> approved of <?php echo $metrics['total']; ?> tasks · <?php echo $completionPct; ?>% completion · <?php echo $metrics['carried']; ?> carried forward
        </div>
    </div>
    <?php if ($can_assign): ?>
        <button class="btn btn-primary btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#addTaskModal" style="background: var(--primary); border: none; border-radius: 8px;">
            <i class="fa-solid fa-plus me-2"></i>Assign Task
        </button>
    <?php endif; ?>
</div>

<form method="GET" action="index.php" class="pulse-card mb-4">
    <input type="hidden" name="route" value="tasks/index">
    <div class="row g-3 align-items-end">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-4">
                <label class="form-label text-secondary">Owner</label>
                <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                    <option value="">All visible</option>
                    <?php foreach ($assignees as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-4">
            <label class="form-label text-secondary">Review Status</label>
            <select name="review_status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($review_statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['review_status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-light w-100" type="submit"><i class="fa-solid fa-filter me-2"></i>Filter</button>
        </div>
    </div>
</form>

<div class="row g-4">
    <?php foreach ($columns as $status => $label): ?>
        <div class="col-lg-4">
            <div class="p-3 bg-dark bg-opacity-20 border border-secondary border-opacity-10 rounded-4 h-100" style="min-height: 500px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0" style="font-weight: 600;"><?php echo htmlspecialchars($label); ?></h5>
                    <span class="badge bg-secondary"><?php echo count($grouped[$status]); ?></span>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php if (empty($grouped[$status])): ?>
                        <div class="text-secondary small">No tasks here.</div>
                    <?php endif; ?>

                    <?php foreach ($grouped[$status] as $task): ?>
                        <?php
                            $priorityTone = ['low' => 'info', 'medium' => 'warning', 'high' => 'danger'][$task->priority] ?? 'secondary';
                            $reviewTone = [
                                'not_submitted' => 'secondary',
                                'pending_review' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                            ][$task->review_status] ?? 'secondary';
                        ?>
                        <div class="pulse-card p-3 d-flex flex-column gap-2" style="background: var(--panel-dark); border-radius: 12px;">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="badge bg-<?php echo $priorityTone; ?> bg-opacity-10 text-<?php echo $priorityTone; ?> border border-<?php echo $priorityTone; ?> border-opacity-20">
                                    <?php echo strtoupper($task->priority); ?>
                                </span>
                                <?php if ($task->is_carry_forward): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">CARRY</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-white fw-semibold"><?php echo htmlspecialchars($task->title); ?></div>
                            <p class="text-secondary mb-0" style="font-size:0.85rem;"><?php echo htmlspecialchars($task->description ?: 'No description provided.'); ?></p>

                            <div class="progress bg-dark" style="height: 7px;">
                                <div class="progress-bar" style="width: <?php echo (int) $task->progress_percent; ?>%; background: var(--primary);"></div>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span><?php echo (int) $task->progress_percent; ?>%</span>
                                <span><?php echo htmlspecialchars(date('M d', strtotime($task->deadline))); ?></span>
                            </div>

                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="badge bg-<?php echo $reviewTone; ?>-subtle text-<?php echo $reviewTone; ?> border border-<?php echo $reviewTone; ?>-subtle">
                                    <?php echo strtoupper(str_replace('_', ' ', $task->review_status)); ?>
                                </span>
                                <span class="text-secondary small"><i class="fa-regular fa-user me-1"></i><?php echo htmlspecialchars($task->assignee_name); ?></span>
                            </div>

                            <?php if ($task->proof_url): ?>
                                <a class="btn btn-outline-info btn-sm" href="index.php?route=file/show&key=<?php echo urlencode($task->proof_url); ?>" target="_blank">
                                    <i class="fa-solid fa-paperclip me-1"></i>View Proof
                                </a>
                            <?php endif; ?>

                            <?php if ($task->review_remark): ?>
                                <div class="text-secondary small">Review: <?php echo htmlspecialchars($task->review_remark); ?></div>
                            <?php endif; ?>

                            <?php if ($task->status !== 'completed'): ?>
                                <form action="index.php?route=tasks/progress/<?php echo $task->task_id; ?>" method="POST" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <div class="row g-2">
                                        <div class="col-5">
                                            <input type="number" min="0" max="100" name="progress_percent" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo (int) $task->progress_percent; ?>" title="Progress">
                                        </div>
                                        <div class="col-5">
                                            <input type="number" min="0" step="0.25" name="actual_hours" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($task->actual_hours ?? '0.00'); ?>" title="Actual hours">
                                        </div>
                                        <div class="col-2">
                                            <button class="btn btn-outline-light btn-sm w-100" title="Save progress"><i class="fa-solid fa-floppy-disk"></i></button>
                                        </div>
                                        <div class="col-12">
                                            <input type="text" name="remarks" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($task->remarks ?? ''); ?>" placeholder="Progress remarks">
                                        </div>
                                    </div>
                                </form>

                                <form action="index.php?route=tasks/complete/<?php echo $task->task_id; ?>" method="POST" enctype="multipart/form-data" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <div class="mb-2">
                                        <input type="file" name="proof" class="form-control form-control-sm bg-dark border-secondary text-white" accept="image/*,.pdf" <?php echo empty($task->proof_url) ? 'required' : ''; ?>>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-5">
                                            <input type="number" min="0" step="0.25" name="actual_hours" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($task->actual_hours ?? '0.00'); ?>" placeholder="Hours">
                                        </div>
                                        <div class="col-7">
                                            <input type="text" name="remarks" class="form-control form-control-sm bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($task->remarks ?? ''); ?>" placeholder="Completion note">
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-success btn-sm w-100 mt-2"><i class="fa-solid fa-check me-1"></i>Submit Complete</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($can_review && $task->review_status === 'pending_review'): ?>
                                <form action="index.php?route=tasks/review/<?php echo $task->task_id; ?>" method="POST" class="border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                    <input type="text" name="review_remark" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" placeholder="Review remark">
                                    <div class="d-flex gap-2">
                                        <button name="decision" value="approved" class="btn btn-outline-success btn-sm flex-fill">Approve</button>
                                        <button name="decision" value="rejected" class="btn btn-outline-danger btn-sm flex-fill">Reject</button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <?php if ($can_assign): ?>
                                <div class="d-flex gap-2 border-top border-secondary border-opacity-10 pt-2 mt-1">
                                    <?php foreach (['pending', 'in_progress', 'completed'] as $target): ?>
                                        <?php if ($target === $task->status) { continue; } ?>
                                            <button class="btn btn-outline-secondary btn-sm btn-move" data-id="<?php echo $task->task_id; ?>" data-status="<?php echo $target; ?>" title="Move to <?php echo htmlspecialchars($target); ?>">
                                                <?php echo strtoupper(substr($target, 0, 1)); ?>
                                            </button>
                                    <?php endforeach; ?>
                                    <span class="ms-auto badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($can_assign): ?>
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addTaskModalLabel">Assign Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=tasks/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary">Task Title *</label>
                        <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Assign Owner *</label>
                        <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white" required>
                            <option value="">Select member</option>
                            <?php foreach ($assignees as $user): ?>
                                <option value="<?php echo $user->user_id; ?>"><?php echo htmlspecialchars($user->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Start</label>
                            <input type="datetime-local" name="start_date" class="form-control bg-dark border-secondary text-white">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Deadline *</label>
                            <input type="datetime-local" name="deadline" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Priority</label>
                            <select name="priority" class="form-select bg-dark border-secondary text-white">
                                <option value="low">LOW</option>
                                <option value="medium" selected>MEDIUM</option>
                                <option value="high">HIGH</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary">Estimated Hours</label>
                            <input type="number" min="0" step="0.25" name="estimated_hours" class="form-control bg-dark border-secondary text-white" value="0.00">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Description</label>
                        <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3"></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Remarks</label>
                        <textarea name="remarks" class="form-control bg-dark border-secondary text-white" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none;">Assign</button>
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
});
</script>
