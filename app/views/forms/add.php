<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?route=forms/index" class="text-secondary text-decoration-none small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Forms List
        </a>
    </div>

    <div class="pulse-card p-4">
        <h4 class="text-white mb-1"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Create Lead Capture Form</h4>
        <div class="text-secondary small mb-4">Design a responsive form ready to embed on WordPress, Webflow, Shopify, or landing pages.</div>

        <form method="POST" action="index.php?route=forms/add">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label text-secondary small">Form Name *</label>
                    <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Website Contact Inquiry Form" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-secondary small">Form Style / Type</label>
                    <select name="form_type" class="form-select bg-dark text-white border-secondary">
                        <option value="embedded">Standard Inline Form</option>
                        <option value="standalone">Full Standalone Page</option>
                        <option value="popup">Modal / Popup Widget</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label text-secondary small">Description (Internal)</label>
                    <input type="text" name="description" class="form-control bg-dark text-white border-secondary" placeholder="Brief note explaining where this form will be deployed...">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small">Submit Button Text</label>
                    <input type="text" name="submit_button_text" class="form-control bg-dark text-white border-secondary" value="Submit Inquiry">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-secondary small">Success Message</label>
                    <input type="text" name="success_message" class="form-control bg-dark text-white border-secondary" value="Thank you! We have received your inquiry and will reach out shortly.">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small">Redirect URL (Optional)</label>
                    <input type="url" name="redirect_url" class="form-control bg-dark text-white border-secondary" placeholder="https://yourwebsite.com/thank-you">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-secondary small">Assign Inbound Leads To</label>
                    <select name="assigned_user_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">Unassigned / Round Robin</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u->user_id; ?>"><?= htmlspecialchars($u->name); ?> (<?= ucfirst($u->role); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small">Default Lead Source</label>
                    <input type="text" name="default_lead_source" class="form-control bg-dark text-white border-secondary" value="website_form">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-secondary small">Default Lead Quality</label>
                    <select name="default_lead_quality" class="form-select bg-dark text-white border-secondary">
                        <option value="warm" selected>Warm (Default)</option>
                        <option value="hot">Hot (High Intent)</option>
                        <option value="cold">Cold</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label text-secondary small">Email Alerts (Comma-separated addresses)</label>
                    <input type="text" name="notification_emails" class="form-control bg-dark text-white border-secondary" placeholder="sales@yourcompany.com, ceo@yourcompany.com">
                </div>

                <div class="col-md-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="require_phone" id="require_phone">
                        <label class="form-check-label text-white small" for="require_phone">Require Phone Number for WhatsApp / SMS outreach</label>
                    </div>
                </div>

                <div class="col-md-12 mt-4 text-end">
                    <a href="index.php?route=forms/index" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Create Form & Get Embed Code
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

