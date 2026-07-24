<div class="pulse-card">
    <?php if (isset($_SESSION['client_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 mb-3" role="alert" style="background: rgba(25, 135, 84, 0.15); color: #2ec4b6;">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo $_SESSION['client_success']; unset($_SESSION['client_success']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['client_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 mb-3" role="alert" style="background: rgba(220, 53, 69, 0.15); color: #e63946;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['client_error']; unset($_SESSION['client_error']); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0">Client Directory</h4>
        <?php if ($can_edit): ?>
            <a href="index.php?route=clients/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
                <i class="fa-solid fa-plus me-2"></i>Add Client
            </a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="clients-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Company Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Contract Range</th>
                    <th>Package Details</th>
                    <?php if ($can_edit): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="<?php echo $can_edit ? 7 : 6; ?>" class="text-center py-4 text-secondary">No clients registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="font-weight-bold text-white"><?php echo htmlspecialchars($client->company_name); ?></td>
                            <td><?php echo htmlspecialchars($client->email ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($client->phone ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $client->status === 'active' ? 'success' : 'danger'; ?>-subtle text-<?php echo $client->status === 'active' ? 'success' : 'danger'; ?> border border-<?php echo $client->status === 'active' ? 'success' : 'danger'; ?>-subtle">
                                    <?php echo ucfirst($client->status); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem;">
                                <?php if ($client->contract_start): ?>
                                    <?php echo htmlspecialchars($client->contract_start); ?> to <?php echo htmlspecialchars($client->contract_end ?? 'Ongoing'); ?>
                                <?php else: ?>
                                    <span class="text-secondary">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.85rem;" class="text-secondary">
                                <?php echo htmlspecialchars($client->package_details ?? 'N/A'); ?>
                            </td>
                            <?php if ($can_edit): ?>
                                <td class="text-end">
                                    <a href="index.php?route=clients/edit/<?php echo $client->client_id; ?>" class="btn btn-outline-light btn-sm" title="Edit Client">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
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
        if ($('#clients-table tbody tr').length > 1 || !$('#clients-table tbody tr td').hasClass('text-center')) {
            $('#clients-table').DataTable({
                "pageLength": 10,
                "lengthChange": false,
                "info": false,
                "searching": true,
                "language": {
                    "search": "Filter Clients:"
                }
            });
        }
    });
</script>
