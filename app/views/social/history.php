<?php
// Analytics History Timeline View (Managers/Employees)
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-0">Analytics History</h1>
            <p class="text-secondary mb-0">Complete chronological timeline of manual social updates.</p>
        </div>
        <button onclick="exportHistoryCSV()" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </button>
    </div>

    <!-- Filter Card -->
    <div class="pulse-card mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" id="history-search" class="filter-select w-100" placeholder="Search accounts, posts, or notes..." style="padding: 0.6rem 1rem;">
            </div>
            <div class="col-md-3">
                <select id="platform-filter" class="filter-select w-100" style="padding: 0.6rem 1rem;">
                    <option value="">All Platforms</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Facebook">Facebook</option>
                    <option value="YouTube">YouTube</option>
                    <option value="LinkedIn">LinkedIn</option>
                    <option value="Twitter/X">Twitter/X</option>
                    <option value="WhatsApp Business">WhatsApp Business</option>
                    <option value="Snapchat">Snapchat</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="engagement-filter" class="filter-select w-100" style="padding: 0.6rem 1rem;">
                    <option value="">All Engagement Rates</option>
                    <option value="high">High (> 5%)</option>
                    <option value="med">Medium (1% - 5%)</option>
                    <option value="low">Low (< 1%)</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button onclick="clearHistoryFilters()" class="btn btn-outline-light btn-sm">Clear Filters</button>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="pulse-card card-glow px-0 py-2">
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-hover text-white mb-0" id="history-table">
                <thead class="bg-dark sticky-top" style="z-index: 5;">
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th class="px-4 py-3" onclick="sortTable(0)">Timestamp <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3" onclick="sortTable(1)">Platform <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3" onclick="sortTable(2)">Account <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3" onclick="sortTable(3)">Post / Content <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3 text-center" onclick="sortTable(4)">Likes <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3 text-center" onclick="sortTable(5)">Comments <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3 text-center" onclick="sortTable(6)">Views <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3 text-center" onclick="sortTable(7)">Engagement Rate <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="py-3" onclick="sortTable(8)">Updated By <i class="fa-solid fa-sort small ms-1 text-secondary"></i></th>
                        <th class="px-4 py-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="table-group-divider" style="border-top: 1px solid var(--border-color);">
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-secondary py-5">No social analytics history available.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                            <tr class="align-middle border-bottom border-secondary border-opacity-10" style="transition: background 0.15s ease;">
                                <td class="px-4 py-3 small text-secondary">
                                    <?php echo date('Y-m-d h:i:s A', strtotime($h->created_at)); ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-white px-2 py-1">
                                        <i class="<?php echo $h->platform_icon; ?> me-1 text-primary"></i><?php echo htmlspecialchars($h->platform_name); ?>
                                    </span>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($h->profile_name); ?></td>
                                <td class="small text-secondary">
                                    <?php echo $h->post_content ? htmlspecialchars(substr($h->post_content, 0, 45)) . '...' : '<span class="text-muted italic">Account-level Update</span>'; ?>
                                </td>
                                <td class="text-center fw-semibold"><?php echo number_format($h->likes); ?></td>
                                <td class="text-center"><?php echo number_format($h->comments); ?></td>
                                <td class="text-center text-secondary"><?php echo number_format($h->views); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $h->engagement_rate >= 5.0 ? 'bg-success' : ($h->engagement_rate >= 1.0 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                        <?php echo $h->engagement_rate; ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle-sm me-2 bg-primary text-white d-flex align-items-center justify-content-center fw-semibold" style="width: 24px; height: 24px; border-radius: 50%; font-size: 0.72rem;">
                                            <?php echo strtoupper(substr($h->updated_by_name, 0, 2)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($h->updated_by_name); ?></span>
                                    </div>
                                </td>
                                <td class="px-4 small text-secondary text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($h->custom_notes); ?>">
                                    <?php echo htmlspecialchars($h->custom_notes); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Pagination and Info -->
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top border-secondary border-opacity-10">
            <div class="small text-secondary" id="table-info">Showing all entries</div>
            <div class="pagination-buttons">
                <!-- Javascript handles pagination dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
// Search, filter, sort and export features
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('history-search');
    const platformFilter = document.getElementById('platform-filter');
    const engagementFilter = document.getElementById('engagement-filter');
    const table = document.getElementById('history-table');
    const rows = Array.from(table.getElementsByTagName('tbody')[0].getElementsByTagName('tr'));

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const platform = platformFilter.value;
        const erFilter = engagementFilter.value;

        let visibleCount = 0;

        rows.forEach(row => {
            if (row.cells.length < 5) return; // Skip empty row
            
            const text = row.innerText.toLowerCase();
            const rowPlatform = row.cells[1].innerText;
            const erText = row.cells[7].innerText;
            const erVal = parseFloat(erText) || 0.0;

            const matchesSearch = text.includes(query);
            const matchesPlatform = !platform || rowPlatform.includes(platform);
            
            let matchesER = true;
            if (erFilter === 'high') matchesER = erVal >= 5.0;
            else if (erFilter === 'med') matchesER = erVal >= 1.0 && erVal < 5.0;
            else if (erFilter === 'low') matchesER = erVal < 1.0;

            if (matchesSearch && matchesPlatform && matchesER) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('table-info').innerText = `Showing ${visibleCount} of ${rows.length} entries`;
    }

    searchInput.addEventListener('input', filterTable);
    platformFilter.addEventListener('change', filterTable);
    engagementFilter.addEventListener('change', filterTable);
});

// Clear filters
function clearHistoryFilters() {
    document.getElementById('history-search').value = '';
    document.getElementById('platform-filter').value = '';
    document.getElementById('engagement-filter').value = '';
    
    const table = document.getElementById('history-table');
    const rows = Array.from(table.getElementsByTagName('tbody')[0].getElementsByTagName('tr'));
    rows.forEach(row => row.style.display = '');
    document.getElementById('table-info').innerText = `Showing all ${rows.length} entries`;
}

// Client-side CSV export
function exportHistoryCSV() {
    const table = document.getElementById('history-table');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    let csv = [];
    rows.forEach(row => {
        let cols = Array.from(row.querySelectorAll('th, td')).map(col => {
            // Clean up text content
            let text = col.innerText.replace(/"/g, '""').trim();
            return `"${text}"`;
        });
        csv.push(cols.join(','));
    });

    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = 'social_analytics_history.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Table Sort
let sortDirection = false;
function sortTable(colIndex) {
    const table = document.getElementById('history-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length < 2) return;

    sortDirection = !sortDirection;

    rows.sort((a, b) => {
        let valA = a.cells[colIndex].innerText.trim();
        let valB = b.cells[colIndex].innerText.trim();

        // Check if numeric sorting is needed
        if (colIndex >= 4 && colIndex <= 7) {
            valA = parseFloat(valA.replace(/,/g, '').replace('%', '')) || 0;
            valB = parseFloat(valB.replace(/,/g, '').replace('%', '')) || 0;
            return sortDirection ? valA - valB : valB - valA;
        }

        return sortDirection ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>
