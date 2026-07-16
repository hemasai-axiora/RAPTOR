<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">System Audit Trail</h1>
            <p class="text-muted mb-0">Review all administrative actions, data edits, security, and access control changes.</p>
        </div>
    </div>

    <!-- Audit Logs Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
            <h5 class="m-0 font-weight-bold text-dark fw-bold">Recent System Logs</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($data['logs']); ?> Entries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="audit-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3" style="width: 180px;">Timestamp</th>
                            <th class="py-3" style="width: 200px;">User</th>
                            <th class="py-3">Action Description</th>
                            <th class="py-3" style="width: 150px;">Component</th>
                            <th class="py-3 text-center" style="width: 100px;">Record ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data['logs'])): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">
                                    <i class="fa-solid fa-list-check fs-2 mb-3 d-block text-muted"></i>
                                    No audit logs available.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data['logs'] as $log): ?>
                                <tr>
                                    <td class="ps-4 py-3 font-monospace text-muted small">
                                        <?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?>
                                    </td>
                                    <td class="py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light text-secondary d-flex align-items-center justify-content-center rounded-circle fw-bold small" style="width: 32px; height: 32px;">
                                                <?php echo strtoupper(substr($log->user_name ?? 'SYS', 0, 2)); ?>
                                            </div>
                                            <div class="ms-2">
                                                <div class="fw-bold text-dark text-sm"><?php echo htmlspecialchars($log->user_name ?? 'System Process'); ?></div>
                                                <small class="text-muted-custom font-monospace text-capitalize" style="font-size: 10px;">
                                                    <?php echo htmlspecialchars($log->role_name ?? 'core'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-secondary text-sm"><?php echo htmlspecialchars($log->action); ?></div>
                                        <?php if (!empty($log->meta_data)): ?>
                                            <?php 
                                                $meta = json_decode($log->meta_data, true); 
                                                if (is_array($meta)): 
                                            ?>
                                                <div class="mt-1 small text-muted font-monospace bg-light p-2 rounded border" style="font-size: 10px; max-height: 120px; overflow-y: auto;">
                                                    <pre class="m-0 text-dark"><?php echo htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT)); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border-0 font-monospace text-uppercase text-xs">
                                            <?php echo htmlspecialchars($log->target_table ?? 'general'); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-center font-monospace small">
                                        <?php echo $log->target_id ? '#' . $log->target_id : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.text-sm {
    font-size: 0.85rem !important;
}
.text-xs {
    font-size: 10px !important;
}
.text-muted-custom {
    color: #adb5bd !important;
}
</style>
