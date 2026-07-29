<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="pulse-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-white mb-0">
                    <i class="fa-solid fa-user-plus me-2 text-primary"></i>
                    <?php echo !empty($converted_from_lead_id) ? 'Convert Lead to Customer' : 'Capture Customer'; ?>
                </h4>
                <a href="index.php?route=customers/index" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Customer Registry
                </a>
            </div>

            <?php if (!empty($converted_from_lead_id)): ?>
                <div class="alert alert-info border-0 shadow mb-4" style="background: rgba(13, 202, 240, 0.15); color: #0dcaf0;">
                    <i class="fa-solid fa-link me-2"></i> Converting originating lead <strong><?php echo htmlspecialchars($originating_lead_code ?: ('#' . $converted_from_lead_id)); ?></strong> into a permanent Customer record.
                </div>
            <?php endif; ?>

            <form action="index.php?route=customers/add" method="POST" id="customer-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="converted_from_lead_id" value="<?php echo htmlspecialchars($converted_from_lead_id ?? ''); ?>">

                <!-- 1. Identity & Classification -->
                <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-id-card text-primary me-2"></i>Customer Identity</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="customer_code" class="form-label text-secondary">Customer ID</label>
                        <input type="text" id="customer_code" class="form-control bg-dark border-secondary text-secondary font-monospace" value="<?php echo htmlspecialchars($customer_code ?? 'Auto-generated'); ?>" readonly disabled>
                    </div>
                    <div class="col-md-4">
                        <label for="customer_type" class="form-label text-secondary">Customer Type *</label>
                        <select name="customer_type" id="customer_type" class="form-select bg-dark border-secondary text-white" required>
                            <option value="Business" <?php echo ($customer_type ?? 'Business') === 'Business' ? 'selected' : ''; ?>>Business / Corporate</option>
                            <option value="Individual" <?php echo ($customer_type ?? '') === 'Individual' ? 'selected' : ''; ?>>Individual / B2C</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="owner_employee_id" class="form-label text-secondary">Account Manager (Owner)</label>
                        <select name="owner_employee_id" id="owner_employee_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp->employee_id; ?>" <?php echo ((string)($owner_employee_id ?? '') === (string)$emp->employee_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp->name); ?> (<?php echo htmlspecialchars($emp->department ?: 'Sales'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="company_name" class="form-label text-secondary">Company / Organization Name</label>
                        <input type="text" name="company_name" id="company_name" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($company_name ?? ''); ?>" placeholder="e.g. Acme Enterprises Inc.">
                    </div>
                    <div class="col-md-6">
                        <label for="first_name" class="form-label text-secondary">Contact First Name / Key Contact</label>
                        <input type="text" name="first_name" id="first_name" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" placeholder="e.g. John Doe">
                    </div>
                </div>

                <!-- 2. Contact Information -->
                <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-address-book text-warning me-2"></i>Contact Details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label text-secondary">Email Address *</label>
                        <input type="email" name="email" id="email" class="form-control bg-dark border-secondary text-white <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email ?? ''); ?>" required placeholder="client@company.com">
                        <div class="invalid-feedback"><?php echo $email_err ?? ''; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label text-secondary">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="+1 555-0199">
                    </div>

                    <div class="col-md-6">
                        <label for="billing_address" class="form-label text-secondary">Billing Address</label>
                        <textarea name="billing_address" id="billing_address" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Street, City, State, Pincode, Country"><?php echo htmlspecialchars($billing_address ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="shipping_address" class="form-label text-secondary mb-0">Shipping / Service Address</label>
                            <div class="form-check form-check-inline me-0">
                                <input class="form-check-input" type="checkbox" id="same_address_check">
                                <label class="form-check-label text-secondary small" for="same_address_check">Same as Billing</label>
                            </div>
                        </div>
                        <textarea name="shipping_address" id="shipping_address" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Street, City, State, Pincode, Country"><?php echo htmlspecialchars($shipping_address ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 3. Contract & Financial Relationship -->
                <h6 class="text-white mb-3 border-bottom pb-2 border-secondary"><i class="fa-solid fa-file-contract text-success me-2"></i>Contract &amp; Commercial Terms</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="status" class="form-label text-secondary">Customer Status</label>
                        <select name="status" id="status" class="form-select bg-dark border-secondary text-white">
                            <option value="Active" <?php echo ($status ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="On Hold" <?php echo ($status ?? '') === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Renewal Due" <?php echo ($status ?? '') === 'Renewal Due' ? 'selected' : ''; ?>>Renewal Due</option>
                            <option value="Churned" <?php echo ($status ?? '') === 'Churned' ? 'selected' : ''; ?>>Churned</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="contract_value" class="form-label text-secondary">Contract Value ($)</label>
                        <input type="number" step="0.01" name="contract_value" id="contract_value" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($contract_value ?? '0.00'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="onboarding_date" class="form-label text-secondary">Customer Since / Onboarding</label>
                        <input type="date" name="onboarding_date" id="onboarding_date" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($onboarding_date ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="renewal_date" class="form-label text-secondary">Renewal Date</label>
                        <input type="date" name="renewal_date" id="renewal_date" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($renewal_date ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="payment_terms" class="form-label text-secondary">Payment Terms</label>
                        <select name="payment_terms" id="payment_terms" class="form-select bg-dark border-secondary text-white">
                            <option value="Net 30" <?php echo ($payment_terms ?? 'Net 30') === 'Net 30' ? 'selected' : ''; ?>>Net 30 Days</option>
                            <option value="Net 15" <?php echo ($payment_terms ?? '') === 'Net 15' ? 'selected' : ''; ?>>Net 15 Days</option>
                            <option value="Net 45" <?php echo ($payment_terms ?? '') === 'Net 45' ? 'selected' : ''; ?>>Net 45 Days</option>
                            <option value="Prepaid" <?php echo ($payment_terms ?? '') === 'Prepaid' ? 'selected' : ''; ?>>Prepaid / Upfront</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="tags" class="form-label text-secondary">Tags / Segment</label>
                        <input type="text" name="tags" id="tags" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($tags ?? ''); ?>" placeholder="e.g. Enterprise, VIP, SaaS">
                    </div>
                    <div class="col-md-4">
                        <label for="associated_client_id" class="form-label text-secondary">Associated Client Directory Link</label>
                        <select name="associated_client_id" id="associated_client_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- None (Standalone Customer) --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?php echo $cl->client_id; ?>" <?php echo ((string)($associated_client_id ?? '') === (string)$cl->client_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cl->company_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label for="products_subscribed" class="form-label text-secondary">Products / Services Subscribed</label>
                        <input type="text" name="products_subscribed" id="products_subscribed" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars($products_subscribed ?? ''); ?>" placeholder="e.g. CRM Enterprise Suite, Managed SEO Services">
                    </div>
                    <div class="col-md-12">
                        <label for="notes" class="form-label text-secondary">Notes &amp; Account History</label>
                        <textarea name="notes" id="notes" class="form-control bg-dark border-secondary text-white" rows="3" placeholder="Key account context, contract SLA notes, renewal conditions..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="index.php?route=customers/index" class="btn btn-outline-light px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" style="background: var(--primary); border: none;">
                        <i class="fa-solid fa-save me-2"></i>Save Customer Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#same_address_check').on('change', function() {
        if ($(this).is(':checked')) {
            $('#shipping_address').val($('#billing_address').val());
        }
    });

    $('#billing_address').on('input', function() {
        if ($('#same_address_check').is(':checked')) {
            $('#shipping_address').val($(this).val());
        }
    });
});
</script>
