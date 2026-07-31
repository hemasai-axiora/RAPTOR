<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1"><i class="fa-solid fa-address-book text-primary me-2"></i>My Follow-up Leads</h4>
            <div class="text-secondary" style="font-size:0.9rem;">Leads assigned to you requiring active follow-up & engagement.</div>
        </div>
        <a href="index.php?route=followups/index" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to My Follow-ups
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary table-stack">
            <thead>
                <tr class="text-secondary">
                    <th>Lead Code</th>
                    <th>Contact Name</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Quality</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-secondary">No assigned follow-up leads found.</td></tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($lead->lead_code ?: ('LD-' . $lead->lead_id)); ?></td>
                            <td class="text-white fw-semibold"><?php echo htmlspecialchars($lead->contact_name ?: trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($lead->lead_company_name ?: ($lead->client_company_name ?: 'Individual')); ?></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo strtoupper($lead->status); ?></span></td>
                            <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?php echo strtoupper($lead->lead_quality ?: 'WARM'); ?></span></td>
                            <td><?php echo htmlspecialchars($lead->phone ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($lead->email ?: '-'); ?></td>
                            <td class="text-end">
                                <a href="index.php?route=leads/view/<?php echo $lead->lead_id; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
