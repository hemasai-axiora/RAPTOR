<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Leads Manager</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Lifecycle pipeline with duplicate checks, ownership, and follow-up dates.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=leads/pipeline" class="btn btn-outline-light btn-sm px-3 py-2">
                <i class="fa-solid fa-grip me-2"></i>Pipeline
            </a>
            <a href="index.php?route=leads/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-user-plus me-2"></i>Capture Lead
            </a>
        </div>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="leads/index">
        <div class="col-md-2">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Quality</label>
            <select name="lead_quality" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($qualities as $quality): ?>
                    <option value="<?php echo $quality; ?>" <?php echo $filters['lead_quality'] === $quality ? 'selected' : ''; ?>><?php echo strtoupper($quality); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Source</label>
            <select name="lead_source" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($sources as $source): ?>
                    <option value="<?php echo htmlspecialchars($source->name); ?>" <?php echo $filters['lead_source'] === $source->name ? 'selected' : ''; ?>><?php echo htmlspecialchars($source->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label text-secondary">Assignee</label>
            <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($assignees as $user): ?>
                    <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Ageing</label>
            <select name="ageing" class="form-select bg-dark border-secondary text-white">
                <option value="">Any</option>
                <option value="7" <?php echo $filters['ageing'] === '7' ? 'selected' : ''; ?>>7+ days</option>
                <option value="30" <?php echo $filters['ageing'] === '30' ? 'selected' : ''; ?>>30+ days</option>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-light w-100" type="submit"><i class="fa-solid fa-filter"></i></button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary table-stack" id="leads-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Lead</th>
                    <th>Company</th>
                    <th>Source</th>
                    <th class="text-center">Quality</th>
                    <th class="text-center">Priority</th>
                    <th class="text-center">Probability</th>
                    <th class="text-end">Value</th>
                    <th>Assignee</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-secondary">No leads match the current filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $qualityClass = [
                                'hot' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                'warm' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                'cold' => 'bg-info-subtle text-info border border-info-subtle',
                            ][$lead->lead_quality] ?? 'bg-secondary-subtle text-secondary';
                            $statusTone = [
                                'new' => 'primary', 'contacted' => 'warning', 'qualified' => 'success',
                                'proposal' => 'info', 'converted' => 'success', 'lost' => 'danger',
                            ][$lead->status] ?? 'secondary';
                            $priorityTone = [
                                'urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary',
                            ][$lead->priority] ?? 'secondary';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td data-label="Lead">
                                <a class="text-white text-decoration-none fw-semibold" href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>">
                                    <?php echo htmlspecialchars($lead->first_name . ' ' . ($lead->last_name ?? '')); ?>
                                </a>
                                <div class="text-secondary" style="font-size:0.78rem;"><?php echo htmlspecialchars($lead->email ?: $lead->phone ?: 'No contact'); ?></div>
                            </td>
                            <td data-label="Company"><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?></td>
                            <td data-label="Source"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?php echo htmlspecialchars($lead->lead_source); ?></span></td>
                            <td data-label="Quality" class="text-center"><span class="badge <?php echo $qualityClass; ?>"><?php echo strtoupper($lead->lead_quality); ?></span></td>
                            <td data-label="Priority" class="text-center"><span class="badge bg-<?php echo $priorityTone; ?>-subtle text-<?php echo $priorityTone; ?> border border-<?php echo $priorityTone; ?>-subtle"><?php echo strtoupper($lead->priority); ?></span></td>
                            <td data-label="Probability" class="text-center fw-semibold text-white"><?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 1); ?>%</td>
                            <td data-label="Value" class="text-end fw-semibold text-success">$<?php echo number_format((float) $lead->lead_value, 2); ?></td>
                            <td data-label="Assignee" class="text-secondary" style="font-size: 0.85rem;"><?php echo htmlspecialchars($lead->assignee_name ?? 'Unassigned'); ?></td>
                            <td data-label="Status"><span class="badge bg-<?php echo $statusTone; ?>-subtle text-<?php echo $statusTone; ?> border border-<?php echo $statusTone; ?>-subtle"><?php echo strtoupper($lead->status); ?></span></td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="btn btn-outline-info btn-sm" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="index.php?route=leads/edit/<?php echo $lead->lead_id; ?>" class="btn btn-outline-light btn-sm" title="Edit"><i class="fa-solid fa-user-pen"></i></a>
                                    <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(function() {
    if ($('#leads-table tbody tr').length > 1) {
        $('#leads-table').DataTable({
            pageLength: 10,
            lengthChange: false,
            info: false,
            searching: true,
            language: { search: 'Search Leads:' }
        });
    }
});
</script>
