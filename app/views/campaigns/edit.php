<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="pulse-card">
            <h4 class="text-white mb-4">Edit Campaign & Actual Metrics</h4>
            
            <form action="index.php?route=campaigns/edit/<?php echo $campaign_id; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_id" class="form-label text-secondary">Client Company *</label>
                        <select name="client_id" id="client_id" class="form-select <?php echo (!empty($client_err)) ? 'is-invalid' : ''; ?>" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->client_id; ?>" <?php echo $client_id == $client->client_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client->company_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $client_err; ?></div>
                    </div>

                    <div class="col-md-6">
                        <label for="name" class="form-label text-secondary">Campaign Name *</label>
                        <input type="text" name="name" id="name" 
                               class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($name); ?>" required>
                        <div class="invalid-feedback"><?php echo $name_err; ?></div>
                    </div>

                    <div class="col-md-6">
                        <label for="channel" class="form-label text-secondary">Marketing Channel *</label>
                        <select name="channel" id="channel" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;" required>
                            <option value="LinkedIn" <?php echo $channel === 'LinkedIn' ? 'selected' : ''; ?>>LinkedIn</option>
                            <option value="Instagram" <?php echo $channel === 'Instagram' ? 'selected' : ''; ?>>Instagram</option>
                            <option value="Facebook" <?php echo $channel === 'Facebook' ? 'selected' : ''; ?>>Facebook</option>
                            <option value="YouTube" <?php echo $channel === 'YouTube' ? 'selected' : ''; ?>>YouTube</option>
                            <option value="X" <?php echo $channel === 'X' ? 'selected' : ''; ?>>X (Twitter)</option>
                            <option value="Website" <?php echo $channel === 'Website' ? 'selected' : ''; ?>>Website</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary">Status</label>
                        <select name="status" id="status" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="budget" class="form-label text-secondary">Planned Budget ($) *</label>
                        <input type="number" step="0.01" name="budget" id="budget" 
                               class="form-control <?php echo (!empty($budget_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($budget); ?>" required>
                        <div class="invalid-feedback"><?php echo $budget_err; ?></div>
                    </div>

                    <div class="col-md-4">
                        <label for="spend" class="form-label text-secondary">Actual Spend ($)</label>
                        <input type="number" step="0.01" name="spend" id="spend" class="form-control" value="<?php echo htmlspecialchars($spend); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="revenue_influenced" class="form-label text-secondary">Actual Revenue Influenced ($)</label>
                        <input type="number" step="0.01" name="revenue_influenced" id="revenue_influenced" class="form-control" value="<?php echo htmlspecialchars($revenue_influenced); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="start_date" class="form-label text-secondary">Start Date *</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="end_date" class="form-label text-secondary">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=campaigns/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
