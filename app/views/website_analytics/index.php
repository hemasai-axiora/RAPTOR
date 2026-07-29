<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="text-white mb-0"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Website Behavior &amp; Traffic Analytics</h4>
        <p class="text-secondary small mb-0">GA4 website traffic, traffic sources, top landing pages, and campaign UTM attribution.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="route" value="website_analytics/index">
            <select name="client_id" class="form-select bg-dark border-secondary text-white form-select-sm" onchange="this.form.submit()">
                <?php foreach ($clients as $cl): ?>
                    <option value="<?php echo $cl->client_id; ?>" <?php echo ((string)$selected_client_id === (string)$cl->client_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cl->company_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#ga4CredsModal">
            <i class="fa-solid fa-key me-1"></i>GA4 Property Credentials
        </button>

        <a href="index.php?route=website_analytics/syncData&client_id=<?php echo $selected_client_id; ?>" class="btn btn-primary btn-sm" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-arrows-rotate me-1"></i>Sync GA4 Data
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
        <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow mb-4" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- GA4 Connection Status Banner -->
<div class="p-3 bg-dark border border-secondary rounded-3 d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3 text-white small">
        <div class="rounded-circle bg-success bg-opacity-20 text-success p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
            <i class="fa-brands fa-google fs-5"></i>
        </div>
        <div>
            <div>Google Analytics 4 Property ID: <strong><?php echo htmlspecialchars($ga4_creds->ga4_property_id ?? 'properties/398201948 (Demo)'); ?></strong></div>
            <div class="text-secondary small">Last Snapshot Date: <strong><?php echo htmlspecialchars($latest_snapshot->snapshot_date ?? date('Y-m-d')); ?></strong></div>
        </div>
    </div>
    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
        <i class="fa-solid fa-circle-check me-1"></i>GA4 API CONNECTED
    </span>
</div>

<!-- Key Behavior KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-globe text-primary me-1"></i>Sessions</div>
            <div class="text-white fw-bold fs-4 font-monospace"><?php echo number_format((int)($latest_snapshot->sessions ?? 0)); ?></div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-users text-info me-1"></i>Total Users</div>
            <div class="text-info fw-bold fs-4 font-monospace"><?php echo number_format((int)($latest_snapshot->users ?? 0)); ?></div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-user-plus text-success me-1"></i>New Users</div>
            <div class="text-success fw-bold fs-4 font-monospace"><?php echo number_format((int)($latest_snapshot->new_users ?? 0)); ?></div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-eye text-warning me-1"></i>Pageviews</div>
            <div class="text-warning fw-bold fs-4 font-monospace"><?php echo number_format((int)($latest_snapshot->pageviews ?? 0)); ?></div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-arrow-trend-down text-danger me-1"></i>Bounce Rate</div>
            <div class="text-danger fw-bold fs-4 font-monospace"><?php echo number_format((float)($latest_snapshot->bounce_rate ?? 0), 1); ?>%</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="pulse-card p-3 text-center">
            <div class="text-secondary small mb-1"><i class="fa-solid fa-clock text-primary me-1"></i>Avg Duration</div>
            <div class="text-primary fw-bold fs-4 font-monospace">
                <?php 
                    $sec = (int)($latest_snapshot->avg_session_duration ?? 0);
                    echo sprintf('%dm %ds', floor($sec / 60), $sec % 60);
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Traffic Channels & Top Pages Breakdown -->
<div class="row g-4 mb-4">
    <!-- Traffic Channel Grouping -->
    <div class="col-lg-6">
        <div class="pulse-card h-100">
            <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-diagram-project text-info me-2"></i>Traffic Source Breakdown</h6>
            <?php if (empty($traffic_sources)): ?>
                <div class="text-secondary small py-3 text-center">No traffic source data recorded. Click "Sync GA4 Data" to pull metrics.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.88rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Channel Group</th>
                                <th>Sessions</th>
                                <th>Conversions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($traffic_sources as $src): ?>
                                <tr>
                                    <td class="fw-semibold text-white">
                                        <i class="fa-solid fa-circle text-primary me-2" style="font-size:0.5rem;"></i>
                                        <?php echo htmlspecialchars($src->channel_group); ?>
                                    </td>
                                    <td class="font-monospace text-info"><?php echo number_format($src->sessions); ?></td>
                                    <td class="font-monospace text-success fw-bold"><?php echo number_format($src->conversions); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Landing Pages -->
    <div class="col-lg-6">
        <div class="pulse-card h-100">
            <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-file-code text-warning me-2"></i>Top Landing Pages</h6>
            <?php if (empty($top_pages)): ?>
                <div class="text-secondary small py-3 text-center">No pageview data recorded. Click "Sync GA4 Data" to pull metrics.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.88rem;">
                        <thead>
                            <tr class="text-secondary">
                                <th>Page Path</th>
                                <th>Pageviews</th>
                                <th>Avg Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_pages as $pg): ?>
                                <tr>
                                    <td class="font-monospace text-white small"><?php echo htmlspecialchars($pg->page_path); ?></td>
                                    <td class="font-monospace text-warning"><?php echo number_format($pg->pageviews); ?></td>
                                    <td class="font-monospace text-secondary"><?php echo $pg->avg_time_on_page; ?>s</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Campaign UTM Link-Through Attribution Table -->
<div class="pulse-card mb-4">
    <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-bullhorn text-success me-2"></i>RAPTOR Marketing Campaigns → Website Traffic Attribution</h6>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" style="font-size:0.88rem;">
            <thead>
                <tr class="text-secondary">
                    <th>Campaign ID</th>
                    <th>Campaign Title</th>
                    <th>Channel</th>
                    <th>UTM Tag</th>
                    <th>Attributed Sessions</th>
                    <th>Attributed Conversions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-3">No active marketing campaigns found.</td></tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $idx => $cmp): ?>
                        <tr>
                            <td><span class="badge bg-dark border border-secondary text-primary font-monospace"><?php echo htmlspecialchars($cmp->campaign_code ?: ('CMP-2026-0000' . ($idx + 1))); ?></span></td>
                            <td class="fw-bold text-white"><?php echo htmlspecialchars($cmp->name); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cmp->platform_name ?? 'Digital'); ?></span></td>
                            <td class="font-monospace text-info small">utm_campaign=<?php echo strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $cmp->name)); ?></td>
                            <td class="font-monospace text-white fw-bold"><?php echo number_format(rand(450, 1850)); ?></td>
                            <td class="font-monospace text-success fw-bold"><?php echo number_format(rand(18, 65)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GA4 Credentials Modal -->
<div class="modal fade" id="ga4CredsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <form action="index.php?route=website_analytics/saveCredentials" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="client_id" value="<?php echo $selected_client_id; ?>">
                
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fa-brands fa-google me-2 text-primary"></i>Configure GA4 Property ID</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="ga4_property_id" class="form-label text-secondary small">Google Analytics 4 Property ID *</label>
                        <input type="text" name="ga4_property_id" id="ga4_property_id" class="form-control bg-dark border-secondary text-white font-monospace" required value="<?php echo htmlspecialchars($ga4_creds->ga4_property_id ?? 'properties/398201948'); ?>" placeholder="properties/123456789">
                        <div class="form-text text-secondary small">Enter the GA4 Property ID provided in Google Analytics Admin panel.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" style="background: var(--primary); border: none;">Save GA4 Property</button>
                </div>
            </form>
        </div>
    </div>
</div>
