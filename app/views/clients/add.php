<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="pulse-card">
            <h4 class="text-white mb-4">Add Client Company</h4>
            
            <form action="index.php?route=clients/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label text-secondary">Company Name *</label>
                        <input type="text" name="company_name" id="company_name" 
                               class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($company_name); ?>" required>
                        <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="email" class="form-label text-secondary">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label text-secondary">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary">Status</label>
                        <select name="status" id="status" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="contract_start" class="form-label text-secondary">Contract Start Date</label>
                        <input type="date" name="contract_start" id="contract_start" class="form-control" value="<?php echo htmlspecialchars($contract_start); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="contract_end" class="form-label text-secondary">Contract End Date</label>
                        <input type="date" name="contract_end" id="contract_end" class="form-control" value="<?php echo htmlspecialchars($contract_end); ?>">
                    </div>

                    <div class="col-12">
                        <label for="package_details" class="form-label text-secondary">Package & Retainer Details</label>
                        <textarea name="package_details" id="package_details" class="form-control" rows="3" placeholder="e.g. SEO Campaign, $2500 monthly retainer..."><?php echo htmlspecialchars($package_details); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="billing_address" class="form-label text-secondary">Billing Address</label>
                        <textarea name="billing_address" id="billing_address" class="form-control" rows="2"><?php echo htmlspecialchars($billing_address); ?></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=clients/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Save Client</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
