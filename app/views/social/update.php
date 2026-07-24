<?php
// Log Social Analytics View (Employee)
$hasPlatforms = false;
if (!empty($assignedAccounts) && isset($platforms) && isset($groupedAccounts)) {
    foreach ($platforms as $plat) {
        if (isset($groupedAccounts[$plat->platform_id])) {
            $hasPlatforms = true;
            break;
        }
    }
}
?>
<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 text-white mb-0">Update Social Analytics</h1>
            <p class="text-secondary mb-0">Select assigned account, choose post, and log engagement stats.</p>
        </div>
        <a href="index.php?route=social/history" class="btn btn-outline-light btn-sm">
            <i class="fa-solid fa-clock-rotate-left me-1"></i> View History
        </a>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Input Form -->
        <div class="col-lg-8 mb-4">
            <div class="pulse-card card-glow">
                <form action="index.php?route=social/update" method="POST" id="analytics-form">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row g-3 mb-4">
                        <!-- Platform Selection -->
                        <div class="col-md-6">
                            <label for="platform_id" class="form-label text-secondary">Platform</label>
                            <select name="platform_id" id="platform_id" class="filter-select w-100" required <?php echo !$hasPlatforms ? 'disabled' : ''; ?> style="padding: 0.6rem 1rem;">
                                <option value=""><?php echo $hasPlatforms ? 'Select Platform' : 'No accounts available'; ?></option>
                                <?php if ($hasPlatforms): ?>
                                    <?php foreach ($platforms as $plat): ?>
                                        <?php if (isset($groupedAccounts[$plat->platform_id])): ?>
                                            <option value="<?php echo $plat->platform_id; ?>"><?php echo htmlspecialchars($plat->name); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Account Selection -->
                        <div class="col-md-6">
                            <label for="account_id" class="form-label text-secondary">Social Account</label>
                            <select name="account_id" id="account_id" class="filter-select w-100" required disabled style="padding: 0.6rem 1rem;">
                                <option value=""><?php echo $hasPlatforms ? 'Select Account' : 'No accounts available'; ?></option>
                            </select>
                        </div>
                    </div>

                    <h5 class="text-white mb-3 border-bottom pb-2 border-secondary" style="font-size: 1rem;"><i class="fa-solid fa-gauge me-2 text-primary"></i>Analytics Metrics</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="likes" class="form-label text-secondary small">Likes</label>
                            <input type="number" name="likes" id="likes" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="comments" class="form-label text-secondary small">Comments</label>
                            <input type="number" name="comments" id="comments" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="shares" class="form-label text-secondary small">Shares</label>
                            <input type="number" name="shares" id="shares" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="views" class="form-label text-secondary small">Views</label>
                            <input type="number" name="views" id="views" class="form-control" min="1" value="1" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="reach" class="form-label text-secondary small">Reach</label>
                            <input type="number" name="reach" id="reach" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="impressions" class="form-label text-secondary small">Impressions</label>
                            <input type="number" name="impressions" id="impressions" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="clicks" class="form-label text-secondary small">Clicks</label>
                            <input type="number" name="clicks" id="clicks" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="followers_gained" class="form-label text-secondary small">Followers Gained</label>
                            <input type="number" name="followers_gained" id="followers_gained" class="form-control" min="0" value="0" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="engagement_rate" class="form-label text-secondary mb-0">Engagement Rate (%)</label>
                            <span class="badge bg-secondary" id="er-calc-label">Auto-calculating</span>
                        </div>
                        <input type="text" name="engagement_rate" id="engagement_rate" class="form-control bg-dark border-secondary" readonly value="0.00">
                    </div>

                    <div class="mb-4">
                        <label for="custom_notes" class="form-label text-secondary">Custom Notes</label>
                        <textarea name="custom_notes" id="custom_notes" class="form-control" rows="3" placeholder="Add details about audience growth, campaign context, etc..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; padding: 0.75rem;">
                            <i class="fa-solid fa-cloud-arrow-up me-2"></i>Submit Analytics Update
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Accounts status -->
        <div class="col-lg-4">
            <div class="pulse-card">
                <h5 class="text-white mb-3"><i class="fa-solid fa-list-check me-2 text-primary"></i>Assigned Accounts</h5>
                <div class="list-group list-group-flush" style="background: transparent;">
                    <?php if (empty($assignedAccounts)): ?>
                        <div class="text-secondary py-3 text-center">No assigned accounts.</div>
                    <?php else: ?>
                        <?php foreach ($assignedAccounts as $acc): ?>
                            <div class="list-group-item bg-transparent border-secondary text-white px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">
                                            <i class="<?php echo $acc->platform_icon; ?> me-2 text-primary"></i><?php echo htmlspecialchars($acc->profile_name); ?>
                                        </div>
                                        <div class="text-secondary small mt-1"><?php echo htmlspecialchars($acc->company_name); ?></div>
                                    </div>
                                    <span class="badge bg-success bg-opacity-15 text-success">Assigned</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Analytics & Lead Updates History Table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="pulse-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-white mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Submitted Analytics &amp; Lead History</h5>
                    <button type="button" onclick="exportHistoryCSV()" class="btn btn-sm btn-success fw-bold">
                        <i class="fa-solid fa-file-excel me-1"></i>Export Excel (CSV)
                    </button>
                </div>
                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                    <table class="table table-hover text-white align-middle mb-0" id="history-table" style="font-size: 0.85rem;">
                        <thead class="bg-dark sticky-top">
                            <tr class="text-secondary">
                                <th>Timestamp</th>
                                <th>Platform</th>
                                <th>Account</th>
                                <th>Post / Content</th>
                                <th class="text-center">Likes</th>
                                <th class="text-center">Comments</th>
                                <th class="text-center">Shares</th>
                                <th class="text-center">Views</th>
                                <th class="text-center">Reach</th>
                                <th class="text-center">Impressions</th>
                                <th class="text-center">Clicks</th>
                                <th class="text-center">Followers</th>
                                <th class="text-center text-success">Leads</th>
                                <th>Lead Details</th>
                                <th class="text-center">Engagement Rate</th>
                                <th>Updated By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="17" class="text-center text-secondary py-4">No social analytics logged yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td class="small text-secondary text-nowrap"><?php echo date('Y-m-d H:i', strtotime($h->created_at)); ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-20 text-white"><?php echo htmlspecialchars($h->platform_name); ?></span></td>
                                        <td class="fw-bold" style="color: var(--text-color, #0f172a);"><?php echo htmlspecialchars($h->profile_name); ?></td>
                                        <td class="small text-secondary"><?php echo $h->post_content ? htmlspecialchars(substr($h->post_content, 0, 30)) . '...' : 'Account-level Update'; ?></td>
                                        <td class="text-center"><?php echo number_format($h->likes); ?></td>
                                        <td class="text-center"><?php echo number_format($h->comments); ?></td>
                                        <td class="text-center"><?php echo number_format($h->shares); ?></td>
                                        <td class="text-center"><?php echo number_format($h->views); ?></td>
                                        <td class="text-center"><?php echo number_format($h->reach ?? 0); ?></td>
                                        <td class="text-center"><?php echo number_format($h->impressions ?? 0); ?></td>
                                        <td class="text-center"><?php echo number_format($h->clicks ?? 0); ?></td>
                                        <td class="text-center"><?php echo number_format($h->followers_gained ?? 0); ?></td>
                                        <td class="text-center"><span class="badge bg-success fw-bold"><?php echo number_format($h->leads_generated ?? 0); ?></span></td>
                                        <td class="small text-secondary"><?php echo !empty($h->lead_details) ? htmlspecialchars($h->lead_details) : '-'; ?></td>
                                        <td class="text-center"><span class="badge bg-info text-dark fw-bold"><?php echo $h->engagement_rate; ?>%</span></td>
                                        <td class="small text-nowrap"><?php echo htmlspecialchars($h->updated_by_name); ?></td>
                                        <td class="small text-secondary"><?php echo !empty($h->custom_notes) ? htmlspecialchars($h->custom_notes) : '-'; ?></td>
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
    // Map account grouping in JS
    const groupedAccounts = <?php echo json_encode((object)$groupedAccounts); ?>;
    
    const platformSelect = document.getElementById('platform_id');
    const accountSelect = document.getElementById('account_id');
    const postSelect = document.getElementById('post_id');

    // Platform change trigger
    platformSelect.addEventListener('change', function() {
        const pid = this.value;
        accountSelect.innerHTML = pid ? '<option value="">Select Account</option>' : '<option value="">No Accounts Available</option>';
        postSelect.innerHTML = '<option value="">Account-level metrics (No post)</option>';
        accountSelect.disabled = true;
        postSelect.disabled = true;

        if (pid && groupedAccounts[pid]) {
            groupedAccounts[pid].forEach(acc => {
                const opt = document.createElement('option');
                opt.value = acc.account_id;
                opt.textContent = acc.profile_name + ' (' + acc.company_name + ')';
                accountSelect.appendChild(opt);
            });
            accountSelect.disabled = false;
        }
    });

    // Account change trigger
    accountSelect.addEventListener('change', function() {
        const aid = this.value;
        const pid = platformSelect.value;
        postSelect.innerHTML = '<option value="">Account-level metrics (No post)</option>';
        postSelect.disabled = true;

        if (pid && aid) {
            fetch('index.php?route=social/getPostsByAccount&accountId=' + aid)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0 && platformSelect.value && accountSelect.value) {
                        res.data.forEach(post => {
                            const opt = document.createElement('option');
                            opt.value = post.post_id;
                            // truncate content for option text
                            const truncated = post.content.length > 40 ? post.content.substring(0, 40) + '...' : post.content;
                            opt.textContent = 'Post: "' + truncated + '"';
                            postSelect.appendChild(opt);
                        });
                        postSelect.disabled = false;
                    }
                })
                .catch(err => console.error('Error fetching posts:', err));
        }
    });

    // Auto-calculate engagement rate
    const likesInput = document.getElementById('likes');
    const commentsInput = document.getElementById('comments');
    const sharesInput = document.getElementById('shares');
    const viewsInput = document.getElementById('views');
    const erInput = document.getElementById('engagement_rate');

    function calculateER() {
        const likes = parseInt(likesInput.value) || 0;
        const comments = parseInt(commentsInput.value) || 0;
        const shares = parseInt(sharesInput.value) || 0;
        const views = parseInt(viewsInput.value) || 1;
        
        let er = ((likes + comments + shares) / (views <= 0 ? 1 : views)) * 100;
        er = Math.min(100.0, Math.max(0.0, er));
        erInput.value = er.toFixed(2);
    }

    [likesInput, commentsInput, sharesInput, viewsInput].forEach(inp => {
        inp.addEventListener('input', calculateER);
    });
});

// Client-side CSV export
function exportHistoryCSV() {
    const table = document.getElementById('history-table');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('tr'));
    
    let csv = [];
    rows.forEach(row => {
        let cols = Array.from(row.querySelectorAll('th, td')).map(col => {
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
</script>
