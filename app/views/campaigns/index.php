<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0">Campaign Registry (Planned vs Actual)</h4>
        <?php if ($can_edit): ?>
            <a href="index.php?route=campaigns/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-plus me-2"></i>Create Campaign
            </a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="campaigns-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Campaign Name</th>
                    <th>Client</th>
                    <th>Channel</th>
                    <th class="text-end">Planned Budget</th>
                    <th class="text-end">Actual Spend</th>
                    <th class="text-end">Actual Revenue</th>
                    <th class="text-center">ROI</th>
                    <th>Status</th>
                    <?php if ($can_edit): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="<?php echo $can_edit ? 9 : 8; ?>" class="text-center py-4 text-secondary">No campaigns registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($campaigns as $campaign): ?>
                        <?php 
                            // Determine ROI class for color coding
                            $roiVal = (float)$campaign->roi;
                            $roiClass = 'text-white';
                            if ($roiVal >= 3.0) {
                                $roiClass = 'text-success font-weight-bold';
                            } elseif ($roiVal > 0 && $roiVal < 1.5) {
                                $roiClass = 'text-warning';
                            } elseif ($roiVal == 0 && (float)$campaign->spend > 0) {
                                $roiClass = 'text-danger';
                            }
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="font-weight-bold text-white"><?php echo htmlspecialchars($campaign->name); ?></td>
                            <td><?php echo htmlspecialchars($campaign->company_name); ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <i class="fa-brands fa-<?php echo strtolower($campaign->channel); ?> me-1"></i><?php echo htmlspecialchars($campaign->channel); ?>
                                </span>
                            </td>
                            <td class="text-end font-weight-bold text-info">$<?php echo number_format($campaign->budget, 2); ?></td>
                            <td class="text-end font-weight-bold text-white">$<?php echo number_format($campaign->spend, 2); ?></td>
                            <td class="text-end font-weight-bold text-success">$<?php echo number_format($campaign->revenue_influenced, 2); ?></td>
                            <td class="text-center <?php echo $roiClass; ?>"><?php echo number_format($roiVal, 2); ?>x</td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $campaign->status === 'active' ? 'success' : ($campaign->status === 'paused' ? 'warning' : 'info'); 
                                ?>-subtle text-<?php 
                                    echo $campaign->status === 'active' ? 'success' : ($campaign->status === 'paused' ? 'warning' : 'info'); 
                                ?> border border-<?php 
                                    echo $campaign->status === 'active' ? 'success' : ($campaign->status === 'paused' ? 'warning' : 'info'); 
                                ?>-subtle">
                                    <?php echo ucfirst($campaign->status); ?>
                                </span>
                            </td>
                            <?php if ($can_edit): ?>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="index.php?route=campaigns/edit/<?php echo $campaign->campaign_id; ?>" class="btn btn-outline-light btn-sm" title="Edit/Adjust">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <span class="badge bg-secondary-subtle text-secondary" title="Deletion is disabled by governance policy">No delete</span>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        if ($('#campaigns-table tbody tr').length > 1 || !$('#campaigns-table tbody tr td').hasClass('text-center')) {
            $('#campaigns-table').DataTable({
                "pageLength": 10,
                "lengthChange": false,
                "info": false,
                "searching": true,
                "language": {
                    "search": "Filter Campaigns:"
                }
            });
        }
    });
</script>
