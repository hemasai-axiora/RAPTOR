<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Leads Manager</h4>
            <div class="text-secondary" style="font-size: 0.9rem;">Lifecycle pipeline with duplicate checks, ownership, and follow-up dates.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=leads/downloadSampleCsv" class="btn btn-outline-info btn-sm px-3 py-2" title="Download Sample CSV Template">
                <i class="fa-solid fa-file-csv me-2"></i>Sample CSV
            </a>
            <button type="button" class="btn btn-outline-success btn-sm px-3 py-2" data-bs-toggle="modal" data-bs-target="#bulkUploadLeadsModal">
                <i class="fa-solid fa-file-import me-2"></i>Bulk Upload CSV
            </button>
            <a href="index.php?route=leads/pipeline" class="btn btn-outline-light btn-sm px-3 py-2">
                <i class="fa-solid fa-grip me-2"></i>Pipeline
            </a>
            <a href="index.php?route=leads/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-user-plus me-2"></i>Capture Lead
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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
                    <th>Lead ID</th>
                    <th>Lead Name</th>
                    <th>Company</th>
                    <th>Source</th>
                    <th class="text-center">Quality</th>
                    <th class="text-center">Priority</th>
                    <th class="text-center">Probability</th>
                    <th class="text-end">Value</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-4 text-secondary">No leads match the current filters.</td>
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
                            <td data-label="Lead ID">
                                <span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($lead->lead_code ?: ('LD-' . date('Y') . '-' . sprintf('%05d', $lead->lead_id))); ?></span>
                            </td>
                            <td data-label="Lead Name">
                                <a class="text-white text-decoration-none fw-semibold" href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>">
                                    <?php echo htmlspecialchars(trim($lead->first_name . ' ' . ($lead->last_name ?? ''))); ?>
                                </a>
                                <div class="text-secondary" style="font-size:0.78rem;"><?php echo htmlspecialchars($lead->email ?: $lead->phone ?: 'No contact'); ?></div>
                            </td>
                            <td data-label="Company"><?php echo htmlspecialchars($lead->lead_company_name ?: $lead->client_company_name ?: 'Individual'); ?></td>
                            <td data-label="Source"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?php echo htmlspecialchars($lead->lead_source); ?></span></td>
                            <td data-label="Quality" class="text-center"><span class="badge <?php echo $qualityClass; ?>"><?php echo strtoupper($lead->lead_quality); ?></span></td>
                            <td data-label="Priority" class="text-center"><span class="badge bg-<?php echo $priorityTone; ?>-subtle text-<?php echo $priorityTone; ?> border border-<?php echo $priorityTone; ?>-subtle"><?php echo strtoupper($lead->priority); ?></span></td>
                            <td data-label="Probability" class="text-center fw-semibold text-white"><?php echo number_format((float) ($lead->probability ?? $lead->conversion_probability), 1); ?>%</td>
                            <td data-label="Value" class="text-end fw-semibold text-success">$<?php echo number_format((float) $lead->lead_value, 2); ?></td>
                            <td data-label="Owner" class="text-secondary" style="font-size: 0.85rem;"><?php echo htmlspecialchars($lead->owner_employee_name ?: $lead->assignee_name ?: 'Unassigned'); ?></td>
                            <td data-label="Status"><span class="badge bg-<?php echo $statusTone; ?>-subtle text-<?php echo $statusTone; ?> border border-<?php echo $statusTone; ?>-subtle"><?php echo strtoupper($lead->status); ?></span></td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="btn btn-outline-info btn-sm" title="Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="index.php?route=leads/edit/<?php echo $lead->lead_id; ?>" class="btn btn-outline-light btn-sm" title="Edit"><i class="fa-solid fa-user-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Bulk Upload Leads via CSV -->
<div class="modal fade" id="bulkUploadLeadsModal" tabindex="-1" aria-labelledby="bulkUploadLeadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bulkUploadLeadsModalLabel">
                    <i class="fa-solid fa-file-csv text-success me-2"></i>Bulk Upload Leads via CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php?route=leads/uploadCsv" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="modal-body">
                    <p class="text-secondary small mb-3">
                        Upload a CSV spreadsheet containing lead columns: <code>First Name</code>, <code>Company Name</code>, <code>Email Address</code>, <code>Phone Number</code>, <code>Lead Source</code>, <code>Estimated Value ($)</code>, <code>Notes</code>.
                    </p>

                    <div class="mb-4 p-4 rounded-3 text-center" style="border: 2px dashed rgba(255, 255, 255, 0.2); background: rgba(0,0,0,0.15);">
                        <i class="fa-solid fa-cloud-arrow-up text-primary fs-1 mb-2"></i>
                        <div class="text-white fw-semibold mb-1">Select CSV File to Upload</div>
                        <span class="text-secondary small d-block mb-3">Supports .csv files up to 5MB</span>
                        <input type="file" name="csv_file" id="csv_file" class="form-control form-control-sm bg-dark border-secondary text-white" accept=".csv" required>
                    </div>

                    <div class="text-center pt-2 border-top border-secondary border-opacity-25">
                        <a href="index.php?route=leads/downloadSampleCsv" class="small text-info text-decoration-none">
                            <i class="fa-solid fa-download me-1"></i>Download Sample CSV Template (.csv)
                        </a>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-file-import me-1"></i>Import & Process CSV</button>
                </div>
            </form>
        </div>
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
