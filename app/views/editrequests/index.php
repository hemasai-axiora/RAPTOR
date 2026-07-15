<?php if (!empty($_SESSION['edit_request_error'])): ?>
    <div class="alert alert-danger border-0 shadow mb-3" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['edit_request_error']; unset($_SESSION['edit_request_error']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['edit_request_success'])): ?>
    <div class="alert alert-success border-0 shadow mb-3" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['edit_request_success']; unset($_SESSION['edit_request_success']); ?>
    </div>
<?php endif; ?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Data Edit Requests</h4>
            <p class="text-secondary mb-0">Managers request governed data changes with a comment. Admin approval applies the change.</p>
        </div>
        <?php if (Policy::canApproveDataEdit()): ?>
            <form class="d-flex gap-2" method="GET">
                <input type="hidden" name="route" value="editrequests/index">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <?php if (Policy::canRequestDataEdit()): ?>
    <form action="index.php?route=editrequests/create" method="POST" class="row g-3 p-3 rounded-3 mb-4" style="background: var(--surface-soft); border: 1px solid var(--border-color);">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="col-md-3">
            <label class="form-label text-secondary">Entity Type</label>
            <select name="entity_type" class="form-select" required>
                <?php foreach ($entity_types as $entity): ?>
                    <option value="<?php echo htmlspecialchars($entity); ?>"><?php echo ucwords(str_replace('_', ' ', $entity)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Record ID</label>
            <input type="number" min="1" name="entity_id" class="form-control" required>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Action</label>
            <select name="requested_action" class="form-select" required>
                <option value="update">Update</option>
                <option value="archive">Archive</option>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label text-secondary">Manager Comment</label>
            <input type="text" name="manager_comment" class="form-control" placeholder="Why is this change required?" required>
        </div>
        <div class="col-12">
            <label class="form-label text-secondary">Proposed Changes JSON</label>
            <textarea name="proposed_changes" rows="3" class="form-control" placeholder='{"status":"qualified","lead_value":"50000"}'></textarea>
            <small class="text-secondary">For archive requests, changes can be left empty.</small>
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
    </form>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Entity</th>
                    <th>Action</th>
                    <th>Requested By</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <?php if (Policy::canApproveDataEdit()): ?><th class="text-end">Review</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td class="font-monospace">#<?php echo (int) $request->request_id; ?></td>
                        <td>
                            <?php echo htmlspecialchars($request->entity_type); ?>
                            <span class="text-secondary">#<?php echo (int) $request->entity_id; ?></span>
                            <?php if (!empty($request->proposed_changes) && $request->requested_action === 'update'): ?>
                                <pre class="small mt-2 mb-0 p-2 rounded" style="background: var(--surface-soft);"><?php echo htmlspecialchars($request->proposed_changes); ?></pre>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(ucfirst($request->requested_action)); ?></td>
                        <td><?php echo htmlspecialchars($request->requester_name); ?></td>
                        <td><?php echo htmlspecialchars($request->manager_comment); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning'); ?>-subtle text-<?php echo $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning'); ?>">
                                <?php echo strtoupper($request->status); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($request->requested_at))); ?></td>
                        <?php if (Policy::canApproveDataEdit()): ?>
                        <td class="text-end">
                            <?php if ($request->status === 'pending'): ?>
                                <form action="index.php?route=editrequests/approve/<?php echo (int) $request->request_id; ?>" method="POST" class="d-inline-flex gap-2 mb-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="text" name="reviewed_comment" class="form-control form-control-sm" placeholder="Admin note">
                                    <button class="btn btn-success btn-sm" type="submit">Approve</button>
                                </form>
                                <form action="index.php?route=editrequests/reject/<?php echo (int) $request->request_id; ?>" method="POST" class="d-inline-flex gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="text" name="reviewed_comment" class="form-control form-control-sm" placeholder="Reason">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Reject</button>
                                </form>
                            <?php else: ?>
                                <span class="text-secondary"><?php echo htmlspecialchars($request->reviewer_name ?? 'Reviewed'); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="<?php echo Policy::canApproveDataEdit() ? 8 : 7; ?>" class="text-center text-secondary py-4">No edit requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
