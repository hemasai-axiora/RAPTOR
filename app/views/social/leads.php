<?php
// Dedicated Lead Generation View (Marketing Hub)
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-0"><i class="fa-solid fa-user-plus text-primary me-2"></i>Lead Generation Hub</h1>
            <p class="text-secondary mb-0">Record generated marketing leads manually or bulk upload via CSV file.</p>
        </div>
        <div>
            <a href="index.php?route=social/downloadSampleLeadsCsv" class="btn btn-outline-info btn-sm fw-semibold me-2">
                <i class="fa-solid fa-file-csv me-1"></i>Download Sample CSV Template
            </a>
            <a href="index.php?route=social/update" class="btn btn-outline-light btn-sm">
                <i class="fa-solid fa-chart-line me-1"></i>Update Social Stats
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

    <!-- Top Row: Manual Entry & CSV Upload -->
    <div class="row g-4 mb-4">
        <!-- Manual Lead Entry Card -->
        <div class="col-lg-7">
            <div class="pulse-card card-glow">
                <h5 class="text-white mb-3 border-bottom pb-2 border-secondary" style="font-size: 1.05rem;">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Enter Generated Lead Manually
                </h5>
                <form action="index.php?route=social/addLeadFromMarketing" method="POST" id="lead-manual-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="platform_id" class="form-label text-secondary small">Source Social Media Platform</label>
                            <select name="platform_id" id="platform_id" class="filter-select w-100" style="padding: 0.6rem 1rem;">
                                <option value="">Select Platform Source</option>
                                <?php if (!empty($platforms)): ?>
                                    <?php foreach ($platforms as $p): ?>
                                        <option value="<?php echo $p->platform_id; ?>"><?php echo htmlspecialchars($p->name); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="company_name" class="form-label text-secondary small">Lead Company / Business Name</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" placeholder="Company Name (Optional)">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="name" class="form-label text-secondary small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label text-secondary small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" required placeholder="name@company.com">
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label text-secondary small">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="phone" class="form-control" required placeholder="+1 555-0199">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="lead_value" class="form-label text-secondary small">Estimated Lead Value ($)</label>
                            <input type="number" step="0.01" name="lead_value" id="lead_value" class="form-control" value="0.00" placeholder="0.00">
                        </div>
                        <div class="col-md-8">
                            <label for="notes" class="form-label text-secondary small">Inquiry Notes / Product Details</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Specific service, campaign context, product interest...">
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; padding: 0.7rem;">
                            <i class="fa-solid fa-plus-circle me-2"></i>Submit &amp; Register Generated Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk CSV Upload Card -->
        <div class="col-lg-5">
            <div class="pulse-card h-100 d-flex flex-column justify-content-between">
                <div>
                    <h5 class="text-white mb-3 border-bottom pb-2 border-secondary" style="font-size: 1.05rem;">
                        <i class="fa-solid fa-file-csv text-success me-2"></i>Bulk Upload Leads via CSV
                    </h5>
                    <p class="text-secondary small mb-3">
                        Upload a CSV spreadsheet containing lead columns: <code>Full Name</code>, <code>Email Address</code>, <code>Phone Number</code>, <code>Company Name</code>, <code>Platform</code>, <code>Notes</code>.
                    </p>

                    <form action="index.php?route=social/uploadLeadsCsv" method="POST" enctype="multipart/form-data" id="csv-upload-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="mb-4 p-4 rounded-3 text-center" style="border: 2px dashed rgba(255, 255, 255, 0.2); background: rgba(0,0,0,0.15);">
                            <i class="fa-solid fa-cloud-arrow-up text-primary fs-1 mb-2"></i>
                            <div class="text-white fw-semibold mb-1">Select CSV File to Upload</div>
                            <span class="text-secondary small d-block mb-3">Supports .csv files up to 5MB</span>
                            <input type="file" name="csv_file" id="csv_file" class="form-control form-control-sm" accept=".csv" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success fw-bold" style="padding: 0.7rem;">
                                <i class="fa-solid fa-file-import me-2"></i>Import &amp; Process CSV Leads
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-3 text-center pt-2 border-top border-secondary border-opacity-25">
                    <a href="index.php?route=social/downloadSampleLeadsCsv" class="small text-info text-decoration-none">
                        <i class="fa-solid fa-download me-1"></i>Download Sample CSV Template (.csv)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Generated Leads Directory & History Table -->
    <div class="row">
        <div class="col-12">
            <div class="pulse-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="text-white mb-0"><i class="fa-solid fa-address-card text-primary me-2"></i>Generated Leads Directory</h5>
                        <span class="text-secondary small">All marketing leads captured manually and imported via CSV.</span>
                    </div>
                    <button type="button" onclick="exportLeadsTableCSV()" class="btn btn-sm btn-outline-success fw-bold">
                        <i class="fa-solid fa-file-excel me-1"></i>Export Leads (CSV)
                    </button>
                </div>

                <!-- Search & Filter Bar -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="leadSearchInput" class="form-control bg-dark text-white border-secondary" placeholder="Search by name, email, phone, company, or platform...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="leadSourceFilter" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">All Lead Sources</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="csv">CSV Import</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="leadQualityFilter" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="">All Lead Qualities</option>
                            <option value="hot">Hot</option>
                            <option value="warm">Warm</option>
                            <option value="cold">Cold</option>
                        </select>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" id="leads-directory-table" style="font-size: 0.88rem;">
                        <thead class="bg-dark sticky-top">
                            <tr class="text-secondary">
                                <th>ID</th>
                                <th>Created At</th>
                                <th>Lead Name</th>
                                <th>Email Address</th>
                                <th>Phone Number</th>
                                <th>Company</th>
                                <th>Platform / Source</th>
                                <th>Logged By</th>
                                <th>Quality</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="leadsTableBody">
                            <?php if (empty($leads)): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-secondary py-4">No generated leads found in registry.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $ld): ?>
                                    <tr class="lead-row" 
                                        data-name="<?php echo htmlspecialchars(strtolower(($ld->first_name ?? '') . ' ' . ($ld->last_name ?? ''))); ?>"
                                        data-email="<?php echo htmlspecialchars(strtolower($ld->email ?? '')); ?>"
                                        data-phone="<?php echo htmlspecialchars(strtolower($ld->phone ?? '')); ?>"
                                        data-company="<?php echo htmlspecialchars(strtolower($ld->company_name ?? '')); ?>"
                                        data-source="<?php echo htmlspecialchars(strtolower($ld->lead_source ?? '')); ?>"
                                        data-quality="<?php echo htmlspecialchars(strtolower($ld->lead_quality ?? 'warm')); ?>">
                                        
                                        <td class="small text-secondary">#<?php echo $ld->lead_id; ?></td>
                                        <td class="small text-secondary text-nowrap"><?php echo date('Y-m-d H:i', strtotime($ld->created_at)); ?></td>
                                        <td class="fw-bold" style="color: var(--text-color, #0f172a);"><?php echo htmlspecialchars(trim(($ld->first_name ?? '') . ' ' . ($ld->last_name ?? ''))); ?></td>
                                        <td><code class="px-2 py-1 rounded" style="background: rgba(37, 99, 235, 0.1); color: #2563eb !important; font-weight: 600;"><?php echo htmlspecialchars($ld->email ?? 'N/A'); ?></code></td>
                                        <td class="fw-semibold" style="color: var(--text-color, #334155);"><?php echo htmlspecialchars($ld->phone ?? 'N/A'); ?></td>
                                        <td class="small text-secondary"><?php echo htmlspecialchars($ld->company_name ?? 'Independent'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-20 text-white">
                                                <i class="fa-solid fa-share-nodes me-1 text-primary"></i><?php echo htmlspecialchars($ld->lead_source ?? 'Social Media'); ?>
                                            </span>
                                        </td>
                                        <td class="small text-nowrap">
                                            <span class="badge px-2 py-1 fw-bold" style="background: #2563eb; color: #ffffff !important;">
                                                <i class="fa-solid fa-user me-1 text-white"></i><?php echo htmlspecialchars($ld->assignee_name ?? 'Employee'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo ($ld->lead_quality ?? 'warm') === 'hot' ? 'bg-danger' : (($ld->lead_quality ?? 'warm') === 'warm' ? 'bg-warning text-dark' : 'bg-info text-dark'); ?> text-uppercase">
                                                <?php echo htmlspecialchars($ld->lead_quality ?? 'warm'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success text-uppercase"><?php echo htmlspecialchars($ld->status ?? 'new'); ?></span>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('leadSearchInput');
    const sourceFilter = document.getElementById('leadSourceFilter');
    const qualityFilter = document.getElementById('leadQualityFilter');
    const rows = document.querySelectorAll('.lead-row');

    function filterTable() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const sourceVal = sourceFilter ? sourceFilter.value.toLowerCase().trim() : '';
        const qualityVal = qualityFilter ? qualityFilter.value.toLowerCase().trim() : '';

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            const phone = row.getAttribute('data-phone') || '';
            const company = row.getAttribute('data-company') || '';
            const source = row.getAttribute('data-source') || '';
            const quality = row.getAttribute('data-quality') || '';

            const matchesSearch = !query || name.includes(query) || email.includes(query) || phone.includes(query) || company.includes(query) || source.includes(query);
            const matchesSource = !sourceVal || source.includes(sourceVal);
            const matchesQuality = !qualityVal || quality === qualityVal;

            if (matchesSearch && matchesSource && matchesQuality) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (sourceFilter) sourceFilter.addEventListener('change', filterTable);
    if (qualityFilter) qualityFilter.addEventListener('change', filterTable);
});

// CSV Export for Leads Directory Table
function exportLeadsTableCSV() {
    const table = document.getElementById('leads-directory-table');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tr'));
    
    let csv = [];
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        let cols = Array.from(row.querySelectorAll('th, td')).map(col => {
            let text = col.innerText.replace(/"/g, '""').trim();
            return `"${text}"`;
        });
        csv.push(cols.join(','));
    });

    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = 'generated_leads_registry.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
