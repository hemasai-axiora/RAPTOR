<?php
$statusTone = [
    'new' => 'primary', 'contacted' => 'warning', 'qualified' => 'success',
    'proposal' => 'info', 'converted' => 'success', 'lost' => 'danger',
][$lead->status] ?? 'secondary';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="pulse-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h4 class="text-white mb-1"><?php echo htmlspecialchars($lead->first_name . ' ' . ($lead->last_name ?? '')); ?></h4>
                    <div class="text-secondary"><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual lead'); ?></div>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?route=leads/edit/<?php echo $lead->lead_id; ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-user-pen me-2"></i>Edit</a>
                    <a href="index.php?route=leads/index" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>
            </div>

            <?php if (!empty($duplicates)): ?>
                <div class="alert alert-warning border-warning bg-warning bg-opacity-10 text-warning">
                    <div class="fw-semibold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Duplicate warning</div>
                    <?php foreach ($duplicates as $dup): ?>
                        <div>
                            <a class="text-warning" href="index.php?route=leads/view/<?php echo $dup->lead_id; ?>">
                                <?php echo htmlspecialchars($dup->first_name . ' ' . ($dup->last_name ?? '')); ?>
                            </a>
                            <?php echo htmlspecialchars(' matches by phone/email'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Status</div>
                    <span class="badge bg-<?php echo $statusTone; ?>-subtle text-<?php echo $statusTone; ?> border border-<?php echo $statusTone; ?>-subtle"><?php echo strtoupper($lead->status); ?></span>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Owner</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->assignee_name ?? 'Unassigned'); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Team</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->team_name ?? 'No team'); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Email</div>
                    <div class="text-white text-wrap" style="word-break: break-all;"><?php echo htmlspecialchars($lead->email ?: 'No email'); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Phone</div>
                    <div class="text-white text-wrap" style="word-break: break-all;"><?php echo htmlspecialchars((empty($lead->phone) || preg_match('/^0+$/', $lead->phone)) ? 'No phone' : $lead->phone); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Source</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->lead_source); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Campaign</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->campaign_source ?: 'No campaign'); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Product</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->product_name ?? 'No product'); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Probability</div>
                    <div class="text-white fw-semibold"><?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 1); ?>%</div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Expected Value</div>
                    <div class="text-success fw-semibold">$<?php echo number_format((float) $lead->lead_value, 2); ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Next Follow-up</div>
                    <div class="text-white"><?php echo $lead->next_follow_up_at ? htmlspecialchars(date('M d, Y h:i A', strtotime($lead->next_follow_up_at))) : 'Not scheduled'; ?></div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="text-secondary small">Date Taken</div>
                    <div class="text-white"><?php echo $lead->next_follow_up_created_at ? htmlspecialchars(date('M d, Y h:i A', strtotime($lead->next_follow_up_created_at))) : 'N/A'; ?></div>
                </div>
                <div class="col-12">
                    <div class="text-secondary small">Location / Lost Reason</div>
                    <div class="text-white"><?php echo htmlspecialchars($lead->location ?: 'No location'); ?></div>
                    <?php if ($lead->lost_reason): ?>
                        <div class="text-danger mt-1"><?php echo htmlspecialchars($lead->lost_reason); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="pulse-card">
            <h5 class="text-white mb-3">Stage Move</h5>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($statuses as $status): ?>
                    <form action="index.php?route=leads/move/<?php echo $lead->lead_id; ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                        <input type="hidden" name="return" value="leads/view/<?php echo $lead->lead_id; ?>">
                        <button class="btn btn-sm <?php echo $lead->status === $status ? 'btn-primary' : 'btn-outline-light'; ?>" <?php echo $lead->status === $status ? 'disabled' : ''; ?>>
                            <?php echo strtoupper($status); ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pulse-card mt-4">
            <h5 class="text-white mb-3">Schedule Follow-up</h5>
            <form action="index.php?route=followups/schedule" method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="lead_id" value="<?php echo $lead->lead_id; ?>">
                <input type="hidden" name="return" value="leads/view/<?php echo $lead->lead_id; ?>">
                <div class="col-md-4">
                    <label class="form-label text-secondary">Owner</label>
                    <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white" <?php echo Policy::isEmployee() ? 'disabled' : ''; ?>>
                        <?php foreach ($assignees as $user): ?>
                            <option value="<?php echo $user->user_id; ?>" <?php echo (string) $lead->assigned_to_user_id === (string) $user->user_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary">Channel</label>
                    <select name="channel" class="form-select bg-dark border-secondary text-white">
                        <?php foreach ($followup_channels as $channel): ?>
                            <option value="<?php echo $channel; ?>"><?php echo strtoupper($channel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label text-secondary">Due At</label>
                    <input type="datetime-local" name="due_at" class="form-control bg-dark border-secondary text-white" required>
                </div>
                <div class="col-12">
                    <label class="form-label text-secondary">Note</label>
                    <textarea name="note" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="What should happen in this follow-up?"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary" style="background: var(--primary); border: none;">
                        <i class="fa-solid fa-calendar-plus me-2"></i>Schedule
                    </button>
                </div>
            </form>
        </div>

        <div class="pulse-card mt-4">
            <h5 class="text-white mb-3">Quick Activity</h5>
            <div class="row g-4">
                <div class="col-lg-6">
                    <form action="index.php?route=communications/add" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="lead_id" value="<?php echo $lead->lead_id; ?>">
                        <input type="hidden" name="return" value="leads/view/<?php echo $lead->lead_id; ?>">
                        <div class="row g-2">
                            <div class="col-6">
                                <select name="channel" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($communication_channels as $channel): ?><option value="<?php echo $channel; ?>"><?php echo strtoupper($channel); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <select name="direction" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($communication_directions as $direction): ?><option value="<?php echo $direction; ?>"><?php echo strtoupper($direction); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <input type="text" name="outcome" class="form-control bg-dark border-secondary text-white" placeholder="Outcome">
                            </div>
                            <div class="col-12">
                                <textarea name="note" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Communication note"></textarea>
                            </div>
                            <div class="col-12">
                                <input type="file" name="proof" class="form-control bg-dark border-secondary text-white" accept="image/*,.pdf">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-info w-100">Log Communication</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form action="index.php?route=meetings/add" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="lead_id" value="<?php echo $lead->lead_id; ?>">
                        <input type="hidden" name="return" value="leads/view/<?php echo $lead->lead_id; ?>">
                        <div class="row g-2">
                            <div class="col-5">
                                <select name="type" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($meeting_types as $type): ?><option value="<?php echo $type; ?>"><?php echo strtoupper($type); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-7">
                                <input type="text" name="title" class="form-control bg-dark border-secondary text-white" placeholder="Title" required>
                            </div>
                            <?php if (!Policy::isEmployee()): ?>
                            <div class="col-12">
                                <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                                    <?php foreach ($assignees as $user): ?>
                                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $lead->assigned_to_user_id === (string) $user->user_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($user->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <input type="datetime-local" name="scheduled_start" class="form-control bg-dark border-secondary text-white" required>
                            </div>
                            <div class="col-12">
                                <input type="text" name="location" class="form-control bg-dark border-secondary text-white" placeholder="Location">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-warning w-100">Schedule Meeting/Demo</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="pulse-card mb-4">
            <h5 class="text-white mb-3">Lead Timeline</h5>
            <?php if (empty($communications) && empty($meetings)): ?>
                <div class="text-secondary">No communications or meetings yet.</div>
            <?php endif; ?>
            <?php foreach ($communications as $item): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white"><i class="fa-solid fa-comments text-info me-2"></i><?php echo strtoupper($item->channel); ?> - <?php echo htmlspecialchars($item->outcome ?: $item->direction); ?></div>
                    <div class="text-secondary small"><?php echo htmlspecialchars($item->user_name); ?> - <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->happened_at))); ?></div>
                    <?php if ($item->note): ?><div class="text-secondary small"><?php echo htmlspecialchars($item->note); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($meetings as $item): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white"><i class="fa-solid fa-handshake text-warning me-2"></i><?php echo strtoupper($item->type); ?> - <?php echo htmlspecialchars($item->title); ?></div>
                    <div class="text-secondary small"><?php echo htmlspecialchars($item->assignee_name); ?> - <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->scheduled_start))); ?></div>
                    <?php if ($item->outcome): ?><div class="text-secondary small"><?php echo htmlspecialchars($item->outcome); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pulse-card mb-4">
            <h5 class="text-white mb-3">Status History</h5>
            <?php if (empty($status_history)): ?>
                <div class="text-secondary">No status history yet.</div>
            <?php endif; ?>
            <?php foreach ($status_history as $item): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white"><?php echo htmlspecialchars(strtoupper($item->from_status ?: 'created') . ' -> ' . strtoupper($item->to_status)); ?></div>
                    <div class="text-secondary small"><?php echo htmlspecialchars($item->changed_by_name ?? 'System'); ?> - <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->changed_at))); ?></div>
                    <?php if ($item->note): ?><div class="text-secondary small"><?php echo htmlspecialchars($item->note); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pulse-card">
            <h5 class="text-white mb-3">Assignment History</h5>
            <?php if (empty($assignment_history)): ?>
                <div class="text-secondary">No assignment history yet.</div>
            <?php endif; ?>
            <?php foreach ($assignment_history as $item): ?>
                <div class="border-bottom border-secondary border-opacity-10 pb-3 mb-3">
                    <div class="text-white">
                        <?php echo htmlspecialchars(($item->from_user_name ?? 'Unassigned') . ' -> ' . ($item->to_user_name ?? 'Unassigned')); ?>
                    </div>
                    <div class="text-secondary small"><?php echo htmlspecialchars($item->assigned_by_name ?? 'System'); ?> - <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($item->assigned_at))); ?></div>
                    <?php if ($item->note): ?><div class="text-secondary small"><?php echo htmlspecialchars($item->note); ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
