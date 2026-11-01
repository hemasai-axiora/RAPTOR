<?php
// Raptor CRM - Contacts CSV Import & Deliverability Validation View
?>
<div class="container-fluid px-0">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="index.php?route=email_campaigns/contacts" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-csv text-primary me-2"></i>Import Contacts via CSV</h3>
            <p class="text-secondary small mb-0">High-deliverability validation pipeline: syntax validation, MX verification, disposable domain blocking, and role account checks.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?route=email_campaigns/importProcess" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Left: File upload & settings -->
            <div class="col-lg-8">
                <!-- Dropzone Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i>1. Select CSV File</h6>
                    </div>
                    <div class="card-body">
                        <div class="p-4 border border-2 border-dashed rounded text-center bg-light">
                            <i class="fa-solid fa-file-csv fs-1 text-primary mb-3"></i>
                            <h6 class="fw-bold">Upload Contacts Spreadsheet (.csv)</h6>
                            <p class="small text-muted mb-3">Ensure your CSV includes an <code>email</code> column. Optional columns: <code>first_name</code>, <code>last_name</code>, <code>company</code>.</p>
                            <input type="file" name="csv_file" class="form-control form-control-sm mx-auto" style="max-width: 380px;" accept=".csv" required>
                        </div>
                    </div>
                </div>

                <!-- Deliverability Safeguards -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-success"><i class="fa-solid fa-shield-virus me-2"></i>2. Deliverability Protection Pipeline</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="check_mx" id="check_mx" value="1" checked>
                            <label class="form-check-label fw-semibold" for="check_mx">
                                Live DNS MX Verification Check
                            </label>
                            <div class="text-muted small">Queries live DNS servers to ensure the recipient domain has active, configured Mail Exchange (MX) records. Rejects non-existent mailboxes upfront before sending.</div>
                        </div>

                        <div class="border-top pt-3">
                            <h6 class="fw-semibold small mb-2 text-dark">Automated Deliverability Rules:</h6>
                            <ul class="small text-muted ps-3 mb-0">
                                <li><strong>RFC 5322 Syntax Check:</strong> Discards malformed or syntactically invalid addresses.</li>
                                <li><strong>Global Suppression Filter:</strong> Cross-references against unsubscribed, hard-bounced, and complained emails to prevent re-sending.</li>
                                <li><strong>Disposable Domain Detector:</strong> Identifies temporary burner inboxes (Mailinator, 10MinuteMail, TempMail).</li>
                                <li><strong>Role Account Flagging:</strong> Flags generic mailboxes (<code>info@</code>, <code>admin@</code>, <code>support@</code>) to prevent high bounce rates.</li>
                                <li><strong>Deduplication:</strong> Automatically deduplicates duplicate emails within the CSV batch and existing lists.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Legal Compliance Declaration -->
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-warning"><i class="fa-solid fa-scale-balanced me-2"></i>3. Consent & Compliance Acknowledgement</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Legal Consent Basis</label>
                            <select name="consent_type" class="form-select form-select-sm">
                                <?php foreach ($consent_types as $val => $label): ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="confirm_consent" id="confirm_consent" value="1" required>
                            <label class="form-check-label small fw-semibold text-danger" for="confirm_consent">
                                I confirm that all contacts in this upload have given explicit opt-in consent or represent lawful business relationships (GDPR / India DPDP / CAN-SPAM compliant). I understand that purchasing third-party scraped lists is strictly prohibited to protect sender reputation.
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: List & Tag Assignment -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-list-check me-2"></i>Add to Lists</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lists)): ?>
                            <p class="small text-muted">No lists available.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($lists as $l): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="list_ids[]" value="<?php echo $l->list_id; ?>" id="import_list_<?php echo $l->list_id; ?>">
                                        <label class="form-check-label small" for="import_list_<?php echo $l->list_id; ?>">
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
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-tags me-2"></i>Tag Imported Contacts</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tags)): ?>
                            <p class="small text-muted">No tags available.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($tags as $t): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tag_ids[]" value="<?php echo $t->tag_id; ?>" id="import_tag_<?php echo $t->tag_id; ?>">
                                        <label class="form-check-label small" for="import_tag_<?php echo $t->tag_id; ?>">
                                            <span class="badge bg-primary-subtle text-primary border"><?php echo htmlspecialchars($t->name); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                        <i class="fa-solid fa-play me-2"></i>Run Import & Verification
                    </button>
                    <a href="index.php?route=email_campaigns/contacts" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

