<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-rectangle-ad me-2 text-primary"></i>Campaign Registry</h4>
            <div class="text-secondary small">Comprehensive management of online & offline marketing campaigns</div>
        </div>
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
                    <th>Campaign ID</th>
                    <th>Campaign Name</th>
                    <th>Client</th>
                    <th class="text-center">Type</th>
                    <th>Channel</th>
                    <th>Owner</th>
                    <th class="text-end">Planned Budget</th>
                    <th class="text-end">Actual Spend</th>
                    <th class="text-end">Revenue</th>
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
                        <td colspan="<?php echo $can_edit ? 12 : 11; ?>" class="text-center py-4 text-secondary">No campaigns registered.</td>
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

                            $isOffline = ($campaign->campaign_type ?? 'online') === 'offline';
                            $typeBadgeClass = $isOffline ? 'bg-warning-subtle text-warning border-warning-subtle' : 'bg-info-subtle text-info border-info-subtle';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                <span class="badge bg-dark border border-secondary text-primary font-monospace" style="font-size:0.8rem;">
                                    <?php echo htmlspecialchars($campaign->campaign_code ?: ('CMP-' . date('Y') . '-' . sprintf('%05d', $campaign->campaign_id))); ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-weight-bold text-white"><?php echo htmlspecialchars($campaign->name); ?></div>
                                <?php if ($isOffline && !empty($campaign->vendor_name)): ?>
                                    <div class="text-secondary" style="font-size:0.75rem;"><i class="fa-solid fa-store me-1"></i><?php echo htmlspecialchars($campaign->vendor_name); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($campaign->company_name); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $typeBadgeClass; ?> border" style="font-size: 0.72rem;">
                                    <i class="fa-solid <?php echo $isOffline ? 'fa-bullhorn' : 'fa-globe'; ?> me-1"></i><?php echo strtoupper($campaign->campaign_type ?? 'online'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                    <?php echo htmlspecialchars($campaign->channel); ?>
                                </span>
                                <?php if ($isOffline && !empty($campaign->reach_estimate)): ?>
                                    <div class="text-secondary mt-1" style="font-size:0.75rem;" title="Reach estimate">
                                        <i class="fa-solid fa-users me-1"></i><?php echo number_format((int)$campaign->reach_estimate); ?> reach
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-secondary" style="font-size: 0.85rem;"><?php echo htmlspecialchars($campaign->owner_name ?? 'Unassigned'); ?></td>
                            <td class="text-end font-weight-bold text-info">$<?php echo number_format((float)$campaign->budget, 2); ?></td>
                            <td class="text-end font-weight-bold text-white">$<?php echo number_format((float)$campaign->spend, 2); ?></td>
                            <td class="text-end font-weight-bold text-success">$<?php echo number_format((float)$campaign->revenue_influenced, 2); ?></td>
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
                                        <?php if (!empty($campaign->proof_of_execution)): ?>
                                            <a href="<?php echo htmlspecialchars($campaign->proof_of_execution); ?>" target="_blank" class="btn btn-outline-warning btn-sm" title="View Proof of Execution">
                                                <i class="fa-solid fa-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?route=campaigns/edit/<?php echo $campaign->campaign_id; ?>" class="btn btn-outline-light btn-sm" title="Edit/Adjust">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
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
