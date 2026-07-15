<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="pulse-card">
            <h4 class="text-white mb-4"><i class="fa-solid fa-file-invoice-dollar me-2" style="color: var(--primary);"></i>Generate Invoice</h4>
            
            <form action="index.php?route=invoices/add" method="POST" id="generate-invoice-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_id" class="form-label text-secondary font-weight-bold">Client Company *</label>
                        <select name="client_id" id="client_id" class="form-select <?php echo (!empty($client_err)) ? 'is-invalid' : ''; ?>" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client->client_id; ?>" 
                                        data-address="<?php echo htmlspecialchars($client->billing_address ?? ''); ?>"
                                        data-name="<?php echo htmlspecialchars($client->company_name); ?>"
                                        data-email="<?php echo htmlspecialchars($client->email ?? ''); ?>"
                                        <?php echo $client_id == $client->client_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client->company_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $client_err; ?></div>
                    </div>

                    <div class="col-md-6">
                        <label for="invoice_number" class="form-label text-secondary font-weight-bold">Invoice Number *</label>
                        <input type="text" name="invoice_number" id="invoice_number" class="form-control" value="<?php echo htmlspecialchars($invoice_number); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="currency" class="form-label text-secondary font-weight-bold">Billing Currency *</label>
                        <select name="currency" id="currency" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;">
                            <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="INR" <?php echo $currency === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="amount" class="form-label text-secondary font-weight-bold">Amount *</label>
                        <input type="number" step="0.01" name="amount" id="amount" 
                               class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($amount); ?>" placeholder="0.00" required>
                        <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                    </div>

                    <div class="col-md-4">
                        <label for="due_date" class="form-label text-secondary font-weight-bold">Due Date *</label>
                        <input type="date" name="due_date" id="due_date" class="form-control <?php echo (!empty($due_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($due_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        <div class="invalid-feedback"><?php echo $due_date_err ?? ''; ?></div>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label text-secondary font-weight-bold">Initial Status</label>
                        <select name="status" id="status" class="form-select" style="background-color: rgba(0,0,0,0.2); border-color: var(--border-color); color: white;">
                            <option value="unpaid" <?php echo $status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>

                    <!-- Conversion rate info -->
                    <div class="col-md-6 d-flex align-items-end">
                        <div id="conversion-info" class="w-100 p-2 rounded bg-opacity-10 bg-info border border-info border-opacity-25 text-info small d-none">
                            <i class="fa-solid fa-calculator me-1"></i> Conversion rate: <strong>1 USD = <?php echo number_format($conversion_rate, 2); ?> INR</strong>.<br>
                            Equivalent: <span id="converted-amt-txt">0.00</span>
                        </div>
                    </div>

                    <!-- Editable Billing Address -->
                    <div class="col-md-12">
                        <label for="billing_address" class="form-label text-secondary font-weight-bold d-flex align-items-center gap-2">
                            Billing Address
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="btn-edit-billing-address" title="Override billing address" style="text-decoration: none;">
                                <i class="fa-solid fa-pencil" style="font-size: 0.85rem;"></i>
                            </button>
                            <button type="button" class="btn btn-link btn-sm p-0 text-danger d-none" id="btn-cancel-billing-address" title="Revert to default address" style="text-decoration: none;">
                                <i class="fa-solid fa-xmark" style="font-size: 0.85rem;"></i>
                            </button>
                        </label>
                        <textarea name="billing_address" id="billing_address" class="form-control" rows="3" placeholder="Will auto-populate from client record but can be customized here..." readonly><?php echo htmlspecialchars($billing_address); ?></textarea>
                    </div>

                    <!-- Hidden Custom Email Inputs -->
                    <input type="hidden" name="custom_email_subject" id="hidden_email_subject" value="">
                    <input type="hidden" name="custom_email_body" id="hidden_email_body" value="">
                    <input type="hidden" name="custom_email_participants" id="hidden_email_participants" value="">

                    <!-- Editable Sender Details -->
                    <div class="col-md-12">
                        <label for="sender_details" class="form-label text-secondary font-weight-bold d-flex align-items-center gap-2">
                            Sender Details
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="btn-edit-sender-details" title="Override sender details" style="text-decoration: none;">
                                <i class="fa-solid fa-pencil" style="font-size: 0.85rem;"></i>
                            </button>
                            <button type="button" class="btn btn-link btn-sm p-0 text-danger d-none" id="btn-cancel-sender-details" title="Revert to default sender details" style="text-decoration: none;">
                                <i class="fa-solid fa-xmark" style="font-size: 0.85rem;"></i>
                            </button>
                        </label>
                        <textarea name="sender_details" id="sender_details" class="form-control" rows="3" placeholder="Will populate with default company details but can be customized here..." readonly><?php echo htmlspecialchars($sender_details); ?></textarea>
                    </div>

                    <!-- Selected Stakeholders Checklist -->
                    <div class="col-md-12">
                        <label class="form-label text-secondary font-weight-bold mb-1 d-flex align-items-center gap-2">
                            Send Invoice Copy to Stakeholders (Optional)
                            <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="btn-edit-email-template" title="Customize email template & CC participants" data-bs-toggle="modal" data-bs-target="#editEmailTemplateModal" style="text-decoration: none;">
                                <i class="fa-solid fa-pencil" style="font-size: 0.85rem;"></i>
                            </button>
                        </label>
                        <div id="client-contacts-container" class="p-3 rounded border border-secondary bg-dark bg-opacity-50" style="max-height: 150px; overflow-y: auto;">
                            <p class="text-secondary small mb-0 select-prompt-txt">Select a Client Company above to view stakeholders.</p>
                        </div>
                    </div>

                    <!-- Send Email Toggle Option -->
                    <div class="col-md-12 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="send_email_on_generate" id="send_email_on_generate" value="1" checked>
                            <label class="form-check-label text-white font-weight-bold" for="send_email_on_generate">
                                <i class="fa-solid fa-paper-plane me-1 text-primary"></i>Send Invoice PDF copy via email to stakeholders upon generation
                            </label>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                        <a href="index.php?route=invoices/index" class="btn btn-outline-light px-4">Cancel</a>
                        <button type="button" class="btn btn-outline-info px-4" id="btn-preview-invoice"><i class="fa-solid fa-eye me-2"></i>Preview Invoice</button>
                        <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none;">Generate</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Preview Modal -->
<div class="modal fade" id="invoicePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white text-dark">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold text-black"><i class="fa-solid fa-magnifying-glass me-2"></i>Invoice Draft Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 font-monospace" style="font-family: 'Courier New', Courier, monospace;">
                <div class="d-flex justify-content-between border-bottom pb-3 mb-4">
                    <div>
                        <h2 class="font-weight-bold mb-1" style="color: #6366f1;">INVOICE</h2>
                        <div class="text-secondary" id="preview-invoice-num">INV-XXXXXXXXXX</div>
                    </div>
                    <div class="text-end text-secondary small" style="width: 280px;">
                        <div class="mb-1"><span class="text-secondary text-uppercase small font-weight-bold">Sender Details (Edit to Customize):</span></div>
                        <textarea id="preview-sender-details-field" class="form-control form-control-sm text-secondary small bg-light text-end" rows="4" style="font-family: inherit; font-size: 0.85rem; border: 1px dashed #ccc; text-align: right; width: 100%; box-sizing: border-box;"></textarea>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <span class="text-secondary text-uppercase small font-weight-bold d-block mb-1">Billed To:</span>
                        <strong id="preview-client-name" class="text-black">Client Company Name</strong>
                        <div class="mt-2 mb-1"><span class="text-secondary text-uppercase small font-weight-bold">Billing Address Details (Edit to Customize):</span></div>
                        <textarea id="preview-billing-address-field" class="form-control form-control-sm text-secondary small bg-light" rows="3" style="font-family: inherit; font-size: 0.85rem; border: 1px dashed #ccc;"></textarea>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-secondary text-uppercase small font-weight-bold d-block mb-1">Details:</span>
                        <div>Date Issued: <strong><?php echo date('Y-m-d'); ?></strong></div>
                        <div>Payment Due: <strong id="preview-due-date">YYYY-MM-DD</strong></div>
                        <div>Status: <strong class="text-danger">UNPAID</strong></div>
                    </div>
                </div>

                <table class="table table-bordered small">
                    <thead>
                        <tr class="table-light">
                            <th>Description</th>
                            <th class="text-end" style="width: 150px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Monthly Digital Marketing Retainer</strong><br>
                                <span class="text-secondary small">Includes social media posting, search ad optimization, and analytics reporting.</span>
                            </td>
                            <td class="text-end font-weight-bold text-black" id="preview-item-amt">$0.00</td>
                        </tr>
                        <tr class="table-light">
                            <td class="text-end font-weight-bold">Grand Total Due:</td>
                            <td class="text-end font-weight-bold text-black" id="preview-grand-total">$0.00</td>
                        </tr>
                    </tbody>
                </table>
                <div class="text-center text-secondary small mt-4 pt-3 border-top">
                    Thank you for your business! This is a draft copy generated for previewing.
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Preview</button>
                <button type="button" class="btn btn-primary" id="btn-submit-from-preview" style="background: #6366f1; border: none;">Generate Invoice</button>
            </div>
        </div>
    </div>
</div>

<!-- Customize Email Template Modal -->
<div class="modal fade" id="editEmailTemplateModal" tabindex="-1" aria-labelledby="editEmailTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="editEmailTemplateModalLabel"><i class="fa-solid fa-envelope me-2 text-primary"></i>Customize Email & CC Participants</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="modal_email_subject" class="form-label text-secondary font-weight-bold">Email Subject</label>
                    <input type="text" id="modal_email_subject" class="form-control bg-dark border-secondary text-white" style="border-radius: 8px;">
                </div>
                <div class="mb-3">
                    <label for="modal_email_body" class="form-label text-secondary font-weight-bold">Email Message Body</label>
                    <textarea id="modal_email_body" class="form-control bg-dark border-secondary text-white" rows="6" style="border-radius: 8px;"></textarea>
                </div>
                <div class="mb-3">
                    <label for="modal_email_participants" class="form-label text-secondary font-weight-bold">CC / Additional Stakeholders</label>
                    <input type="text" id="modal_email_participants" class="form-control bg-dark border-secondary text-white" placeholder="stakeholder1@example.com, stakeholder2@example.com" style="border-radius: 8px;">
                    <div class="form-text text-secondary small">Enter comma-separated email addresses of additional recipients.</div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-email-template" data-bs-dismiss="modal" style="background: var(--primary); border: none;">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const conversionRate = <?php echo (float)$conversion_rate; ?>;
    const defaultSenderDetails = "Raptor Marketing Agency\n100 Creator Square, Business Bay\nbilling@raptor-agency.com\n+1 (555) 0100";
    let billingAddressEditing = false;
    let senderDetailsEditing = false;
    let originalClientAddress = '';

    // Initialize Default Sender Details if empty
    if (!$('#sender_details').val()) {
        $('#sender_details').val(defaultSenderDetails);
    }

    // Billing Address override toggle
    $('#btn-edit-billing-address').on('click', function() {
        billingAddressEditing = true;
        $('#billing_address').prop('readonly', false).focus();
        $('#btn-edit-billing-address').addClass('d-none');
        $('#btn-cancel-billing-address').removeClass('d-none');
    });

    // Revert/Cancel Billing Address override
    $('#btn-cancel-billing-address').on('click', function() {
        billingAddressEditing = false;
        $('#billing_address').val(originalClientAddress).prop('readonly', true);
        $('#btn-edit-billing-address').removeClass('d-none');
        $('#btn-cancel-billing-address').addClass('d-none');
    });

    // Sender Details override toggle
    $('#btn-edit-sender-details').on('click', function() {
        senderDetailsEditing = true;
        $('#sender_details').prop('readonly', false).focus();
        $('#btn-edit-sender-details').addClass('d-none');
        $('#btn-cancel-sender-details').removeClass('d-none');
    });

    // Revert/Cancel Sender Details override
    $('#btn-cancel-sender-details').on('click', function() {
        senderDetailsEditing = false;
        $('#sender_details').val(defaultSenderDetails).prop('readonly', true);
        $('#btn-edit-sender-details').removeClass('d-none');
        $('#btn-cancel-sender-details').addClass('d-none');
    });

    // Modal Open: Copy hidden inputs or pre-fill defaults
    $('#editEmailTemplateModal').on('show.bs.modal', function () {
        const clientName = $('#client_id option:selected').data('name') || 'Valued Client';
        const invoiceNum = $('#invoice_number').val() || 'INV-XXXXXXXX';
        const amount = $('#amount').val() || '0.00';
        const currency = $('#currency').val();
        const dueDate = $('#due_date').val() || 'YYYY-MM-DD';
        const currencySymbol = (currency === 'INR') ? 'Rs. ' : '$';

        const defaultSubject = `Invoice ${invoiceNum} from Raptor Marketing Agency`;
        const defaultBody = `Hello,\n\nAn invoice has been generated for your company, ${clientName}.\n\n` +
                            `Details:\n` +
                            `- Invoice Number: ${invoiceNum}\n` +
                            `- Amount: ${currencySymbol}${amount}\n` +
                            `- Due Date: ${dueDate}\n\n` +
                            `Please click the link below to view or print your invoice:\n` +
                            `[Invoice Link will be generated automatically]\n\n` +
                            `Best regards,\nRaptor Billing Team`;

        const currentSubject = $('#hidden_email_subject').val() || defaultSubject;
        const currentBody = $('#hidden_email_body').val() || defaultBody;
        const currentParticipants = $('#hidden_email_participants').val();

        $('#modal_email_subject').val(currentSubject);
        $('#modal_email_body').val(currentBody);
        $('#modal_email_participants').val(currentParticipants);
    });

    // Save Email Template Changes: Copy to hidden fields
    $('#btn-save-email-template').on('click', function() {
        $('#hidden_email_subject').val($('#modal_email_subject').val());
        $('#hidden_email_body').val($('#modal_email_body').val());
        $('#hidden_email_participants').val($('#modal_email_participants').val());
    });

    // Initialize Select2 search option inside the select element
    $('#client_id').select2({
        placeholder: "-- Select Client --",
        allowClear: true
    });

    // Trigger address populate and contacts load on client change
    $('#client_id').on('change', function() {
        const option = $(this).find('option:selected');
        const address = option.data('address') || '';
        const name = option.data('name');
        
        originalClientAddress = address;
        if (!billingAddressEditing) {
            $('#billing_address').val(address);
        }

        const clientId = $(this).val();
        if (!clientId) {
            $('#client-contacts-container').html('<p class="text-secondary small mb-0 select-prompt-txt">Select a Client Company above to view stakeholders.</p>');
            return;
        }

        // Fetch client contacts via API
        $('#client-contacts-container').html('<div class="text-secondary small"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading client stakeholders...</div>');
        $.getJSON('index.php?route=clients/contacts_api', { client_id: clientId }, function(res) {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(function(c, i) {
                    html += `
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="selected_contacts[]" value="${c.email}" id="contact_${i}" checked>
                            <label class="form-check-label text-white small" for="contact_${i}">
                                <strong>${c.name}</strong> (${c.role_or_title || 'Stakeholder'}) - <span class="text-secondary font-monospace">${c.email}</span>
                            </label>
                        </div>
                    `;
                });
                $('#client-contacts-container').html(html);
            } else {
                $('#client-contacts-container').html('<p class="text-secondary small mb-0 select-prompt-txt">No contact persons found for this client. Invoice will be sent only to company primary email.</p>');
            }
        }).fail(function() {
            $('#client-contacts-container').html('<p class="text-danger small mb-0 select-prompt-txt">Failed to fetch contacts. Primary email will be used.</p>');
        });
    });

    // Handle currency calculations live
    function updateCurrencyCalcs() {
        const currency = $('#currency').val();
        const amount = parseFloat($('#amount').val()) || 0;

        if (currency === 'INR') {
            const converted = amount / conversionRate;
            $('#converted-amt-txt').text('$' + converted.toFixed(2) + ' USD equivalent');
            $('#conversion-info').removeClass('d-none');
        } else {
            const converted = amount * conversionRate;
            $('#converted-amt-txt').text('₹' + converted.toFixed(2) + ' INR equivalent');
            $('#conversion-info').removeClass('d-none');
        }
    }

    $('#currency, #amount').on('input change', updateCurrencyCalcs);
    if ($('#amount').val()) {
        updateCurrencyCalcs();
    }

    // Modal Preview rendering
    $('#btn-preview-invoice').on('click', function() {
        const clientId = $('#client_id').val();
        if (!clientId) {
            alert('Please select a Client Company first.');
            return;
        }

        const clientName = $('#client_id option:selected').data('name') || 'Client Name';
        const invoiceNum = $('#invoice_number').val() || 'INV-XXXXXXXX';
        const amount = parseFloat($('#amount').val()) || 0;
        const currency = $('#currency').val();
        const dueDate = $('#due_date').val() || 'YYYY-MM-DD';
        const billingAddress = $('#billing_address').val() || '';

        const currencySymbol = (currency === 'INR') ? '₹' : '$';
        const formattedAmt = currencySymbol + amount.toFixed(2);

        // Populate Preview Modal
        $('#preview-invoice-num').text(invoiceNum);
        $('#preview-client-name').text(clientName);
        $('#preview-billing-address-field').val(billingAddress);
        $('#preview-due-date').text(dueDate);
        $('#preview-item-amt').text(formattedAmt);
        $('#preview-grand-total').text(formattedAmt);

        // Populate Sender Details in Preview Modal from main form textarea
        const senderDetails = $('#sender_details').val() || defaultSenderDetails;
        $('#preview-sender-details-field').val(senderDetails);

        // Open modal
        $('#invoicePreviewModal').modal('show');
    });

    // Synchronize modifications inside the preview modal back to the main form billing address field
    $('#preview-billing-address-field').on('input change', function() {
        $('#billing_address').val($(this).val());
    });

    // Synchronize modifications inside the preview modal back to the main form sender details textarea
    $('#preview-sender-details-field').on('input change', function() {
        $('#sender_details').val($(this).val());
    });

    // Form submission from preview modal
    $('#btn-submit-from-preview').on('click', function() {
        $('#generate-invoice-form').submit();
    });
});
</script>
