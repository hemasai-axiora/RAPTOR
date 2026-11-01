<?php
// Raptor CRM - Add / Edit Contact Form with Consent Metadata
?>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="index.php?route=email_campaigns/contacts" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h3 class="fw-bold mb-1">Add Contact</h3>
            <p class="text-secondary small mb-0">Record recipient with explicit consent proof to protect sender reputation.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?route=email_campaigns/contactSave">
        <div class="row g-4">
            <!-- Left Column: Core Contact Info -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-id-card me-2"></i>Contact Identity</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="john.doe@example.com" required>
                                <div class="form-text">Will be checked against global suppression and role/disposable domain filters.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">First Name</label>
                                <input type="text" name="first_name" class="form-control" placeholder="John">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" placeholder="Doe">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Company / Organization</label>
                                <input type="text" name="company_name" class="form-control" placeholder="Acme Corporation">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Fields (JSON) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-tags me-2"></i>Custom Merge Attributes</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-cf"><i class="fa-solid fa-plus me-1"></i>Add Field</button>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">Custom fields are available in campaign templates as merge tags (e.g. <code>{{city}}</code>, <code>{{job_title}}</code>).</p>
                        <div id="cf-container">
                            <div class="row g-2 mb-2 cf-row">
                                <div class="col-5">
                                    <input type="text" name="cf_key[]" class="form-control form-control-sm" placeholder="Field Key (e.g. city)">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="cf_val[]" class="form-control form-control-sm" placeholder="Value (e.g. London)">
                                </div>
                                <div class="col-1 text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-cf"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Consent & Compliance Proof -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-success"><i class="fa-solid fa-shield-halved me-2"></i>Deliverability & Consent Governance (CAN-SPAM / GDPR / DPDP)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Legal Consent Basis <span class="text-danger">*</span></label>
                                <select name="consent_type" class="form-select form-select-sm" required>
                                    <option value="explicit_optin">Explicit Double/Single Opt-in</option>
                                    <option value="customer_relationship">Existing Customer / Contract Basis</option>
                                    <option value="legitimate_interest">Legitimate Interest (B2B)</option>
                                    <option value="public_inquiry">Public Webform Inquiry</option>
                                    <option value="internal_employee">Internal Corporate Employee</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Source Channel</label>
                                <select name="source" class="form-select form-select-sm">
                                    <option value="manual_entry">Manual Administrator Entry</option>
                                    <option value="webform_optin">Webform Single/Double Opt-in</option>
                                    <option value="conference_lead">Conference / Offline Lead</option>
                                    <option value="internal_payroll">Internal Corporate Directory</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Consent Notes & Audit Trail</label>
                                <textarea name="consent_notes" rows="2" class="form-control form-control-sm" placeholder="Document origin of consent (e.g., 'Subscribed via website form on /newsletter with checkbox verified')."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Lists & Tags -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-list-check me-2"></i>Assign to Lists</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lists)): ?>
                            <p class="small text-muted">No lists created yet. <a href="index.php?route=email_campaigns/lists">Create a list</a></p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($lists as $l): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="list_ids[]" value="<?php echo $l->list_id; ?>" id="list_<?php echo $l->list_id; ?>">
                                        <label class="form-check-label small" for="list_<?php echo $l->list_id; ?>">
                                            <?php echo htmlspecialchars($l->name); ?>
                                            <?php if ($l->is_internal): ?>
                                                <span class="badge bg-secondary-subtle text-secondary small">Internal</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-tags me-2"></i>Assign Tags</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tags)): ?>
                            <p class="small text-muted">No tags defined.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($tags as $t): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tag_ids[]" value="<?php echo $t->tag_id; ?>" id="tag_<?php echo $t->tag_id; ?>">
                                        <label class="form-check-label small" for="tag_<?php echo $t->tag_id; ?>">
                                            <span class="badge bg-primary-subtle text-primary border"><?php echo htmlspecialchars($t->name); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subscription Status -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-toggle-on me-2"></i>Status</h6>
                    </div>
                    <div class="card-body">
                        <select name="status" class="form-select form-select-sm">
                            <option value="subscribed" selected>Subscribed (Active)</option>
                            <option value="unsubscribed">Unsubscribed</option>
                        </select>
                        <div class="form-text small mt-2 text-muted">If an email is globally suppressed or bounced, it will automatically override to Suppressed.</div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm"><i class="fa-solid fa-check me-2"></i>Save Contact</button>
                    <a href="index.php?route=email_campaigns/contacts" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cfContainer = document.getElementById('cf-container');
    const btnAddCf = document.getElementById('btn-add-cf');

    btnAddCf.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 cf-row';
        row.innerHTML = `
            <div class="col-5">
                <input type="text" name="cf_key[]" class="form-control form-control-sm" placeholder="Field Key">
            </div>
            <div class="col-6">
                <input type="text" name="cf_val[]" class="form-control form-control-sm" placeholder="Value">
            </div>
            <div class="col-1 text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-cf"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
        cfContainer.appendChild(row);
    });

    cfContainer.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-cf')) {
            const row = e.target.closest('.cf-row');
            if (document.querySelectorAll('.cf-row').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(i => i.value = '');
            }
        }
    });
});
</script>
