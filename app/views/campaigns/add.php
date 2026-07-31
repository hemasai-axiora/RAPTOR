<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="pulse-card">
            <h4 class="text-white mb-4"><i class="fa-solid fa-rectangle-ad me-2 text-primary"></i>Create Marketing Campaign</h4>
            
            <form action="index.php?route=campaigns/add" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="row g-3">
                    <!-- Campaign Category Toggle -->
                    <div class="col-12 mb-2">
                        <label class="form-label text-secondary d-block fw-semibold">Campaign Category *</label>
                        <div class="btn-group w-100" role="group" aria-label="Campaign Type Toggle">
                            <input type="radio" class="btn-check" name="campaign_type" id="type_online" value="online" <?php echo ($campaign_type ?? 'online') === 'online' ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-info py-2" for="type_online">
                                <i class="fa-solid fa-globe me-2"></i>Online / Digital Campaign
                            </label>

                            <input type="radio" class="btn-check" name="campaign_type" id="type_offline" value="offline" <?php echo ($campaign_type ?? '') === 'offline' ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-warning py-2" for="type_offline">
                                <i class="fa-solid fa-bullhorn me-2"></i>Offline / Event / Print / OOH Campaign
                            </label>
                        </div>
                    </div>

                    <!-- Campaign ID (Auto-generated) -->
                    <div class="col-md-6">
                        <label for="campaign_code" class="form-label text-secondary">Campaign ID</label>
                        <input type="text" name="campaign_code" id="campaign_code" class="form-control bg-dark border-secondary text-light font-monospace fw-bold" value="<?php echo htmlspecialchars($campaign_code ?? ''); ?>" readonly>
                    </div>

                    <!-- Client Company -->
                    <div class="col-md-6">
                        <label for="client_id" class="form-label text-secondary">Client Company *</label>
                        <select name="client_id" id="client_id" class="form-select bg-dark border-secondary text-white <?php echo (!empty($client_err)) ? 'is-invalid' : ''; ?>" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->client_id; ?>" <?php echo (string)($client_id ?? '') === (string)$client->client_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client->company_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $client_err ?? ''; ?></div>
                    </div>

                    <!-- Campaign Name -->
                    <div class="col-md-6">
                        <label for="name" class="form-label text-secondary">Campaign Name *</label>
                        <input type="text" name="name" id="name" 
                               class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($name ?? ''); ?>" placeholder="e.g. Q3 Brand Awareness" required>
                        <div class="invalid-feedback"><?php echo $name_err ?? ''; ?></div>
                    </div>

                    <!-- Campaign Owner (Employee) -->
                    <div class="col-md-6">
                        <label for="owner_employee_id" class="form-label text-secondary">Campaign Owner (Employee)</label>
                        <select name="owner_employee_id" id="owner_employee_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">Leave unassigned</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>" <?php echo (string)($owner_employee_id ?? '') === (string)$emp->employee_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp->name); ?> (<?php echo htmlspecialchars($emp->job_title ?: $emp->role_name); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Marketing Channel -->
                    <div class="col-md-6">
                        <label for="channel" class="form-label text-secondary">Marketing Channel *</label>
                        <select name="channel" id="channel" class="form-select bg-dark border-secondary text-white" required>
                            <!-- Dynamically populated via JS based on Campaign Type -->
                        </select>
                    </div>

                    <!-- Planned Budget -->
                    <div class="col-md-6">
                        <label for="budget" class="form-label text-secondary">Planned Budget ($) *</label>
                        <input type="number" step="0.01" name="budget" id="budget" 
                               class="form-control <?php echo (!empty($budget_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($budget ?? ''); ?>" placeholder="0.00" required>
                        <div class="invalid-feedback"><?php echo $budget_err ?? ''; ?></div>
                    </div>

                    <!-- Offline Specific Fields Container -->
                    <div id="offline_fields_container" class="col-12" style="display: none;">
                        <div class="p-3 rounded bg-dark border border-warning border-opacity-25 my-2">
                            <div class="fw-semibold text-warning mb-3">
                                <i class="fa-solid fa-bullhorn me-2"></i>Offline Execution Metadata
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="vendor_name" class="form-label text-secondary">Vendor / Agency Name</label>
                                    <input type="text" name="vendor_name" id="vendor_name" class="form-control" value="<?php echo htmlspecialchars($vendor_name ?? ''); ?>" placeholder="e.g. Apex Print Media Ltd">
                                </div>
                                <div class="col-md-6">
                                    <label for="location" class="form-label text-secondary">Location / City</label>
                                    <input type="text" name="location" id="location" class="form-control" value="<?php echo htmlspecialchars($location ?? ''); ?>" placeholder="e.g. Downtown Metro Hub / New York">
                                </div>
                                <div class="col-md-6">
                                    <label for="reach_estimate" class="form-label text-secondary">Reach / Circulation Estimate</label>
                                    <input type="number" name="reach_estimate" id="reach_estimate" class="form-control" value="<?php echo htmlspecialchars($reach_estimate ?? ''); ?>" placeholder="e.g. 50000">
                                </div>
                                <div class="col-md-6">
                                    <label for="proof_of_execution_file" class="form-label text-secondary">Proof of Execution (Photo/PDF Upload)</label>
                                    <input type="file" name="proof_of_execution_file" id="proof_of_execution_file" class="form-control bg-dark border-secondary text-white" accept="image/*,.pdf,.doc,.docx">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="col-md-6">
                        <label for="start_date" class="form-label text-secondary">Start Date *</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="end_date" class="form-label text-secondary">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary">Status</label>
                        <select name="status" id="status" class="form-select bg-dark border-secondary text-white">
                            <option value="active" <?php echo ($status ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo ($status ?? '') === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="completed" <?php echo ($status ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=campaigns/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Create Campaign</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const onlineChannels = ['LinkedIn', 'Instagram', 'Facebook', 'YouTube', 'X (Twitter)', 'Google Ads', 'Email', 'Website', 'Other (Online)'];
    const offlineChannels = ['Print', 'Radio', 'TV', 'Hoarding/OOH', 'Event/Expo', 'Direct Mail', 'Telemarketing', 'Other (Offline)'];
    const selectedChannel = "<?php echo htmlspecialchars($channel ?? ''); ?>";

    function updateFormCategory() {
        const isOffline = $('#type_offline').is(':checked');
        const container = $('#offline_fields_container');
        const channelSelect = $('#channel');
        
        channelSelect.empty();
        const choices = isOffline ? offlineChannels : onlineChannels;

        choices.forEach(function(item) {
            const isSelected = selectedChannel === item || selectedChannel.toLowerCase() === item.toLowerCase();
            channelSelect.append(new Option(item, item, isSelected, isSelected));
        });

        if (isOffline) {
            container.slideDown(200);
        } else {
            container.slideUp(200);
        }
    }

    $('input[name="campaign_type"]').on('change', updateFormCategory);
    updateFormCategory();
});
</script>
