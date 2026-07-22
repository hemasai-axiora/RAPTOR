<?php
// Helpers
$canFinance = PermissionService::can('invoices', 'edit');
?>
<div class="pulse-card">
    <?php if (!empty($_SESSION['invoice_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?php echo htmlspecialchars($_SESSION['invoice_success']); unset($_SESSION['invoice_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['invoice_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="fa-solid fa-circle-xmark me-2"></i><?php echo htmlspecialchars($_SESSION['invoice_error']); unset($_SESSION['invoice_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-white mb-0">Billing &amp; Invoices Ledger</h4>
        <a href="index.php?route=invoices/add" class="btn btn-primary btn-sm px-3 py-2" style="background: var(--primary); border: none; border-radius: 8px;">
            <i class="fa-solid fa-file-invoice me-2"></i>Generate Invoice
        </a>
    </div>

    <!-- Complete Invoice Status Filter -->
    <div class="row g-2 mb-4">
        <div class="col-md-4 col-lg-3">
            <select id="invoice-status-filter" class="form-select bg-dark border-secondary text-white" style="border-radius: 8px;">
                <option value="">All Invoice Statuses</option>
                <option value="unpaid">Unpaid</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
                <option value="cancelled">Cancelled</option>
                <option value="draft">Draft</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle border-secondary" id="invoices-table">
            <thead>
                <tr class="text-secondary" style="border-bottom: 1px solid var(--border-color);">
                    <th>Invoice Number</th>
                    <th>Client Company</th>
                    <th class="text-end">Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <?php if ($canFinance): ?><th>UTR / Remarks</th><?php endif; ?>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="<?php echo $canFinance ? 7 : 6; ?>" class="text-center py-4 text-secondary">No invoices generated.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                        $isFinalised = in_array($invoice->status, ['paid', 'cancelled']);
                        $statusColor = match($invoice->status) {
                            'paid'      => 'success',
                            'cancelled' => 'warning',
                            default     => 'danger',
                        };
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td class="font-weight-bold text-white"><?php echo htmlspecialchars($invoice->invoice_number); ?></td>
                            <td><?php echo htmlspecialchars($invoice->company_name); ?></td>
                            <td class="text-end font-weight-bold text-white"><?php echo ($invoice->currency === 'INR') ? '₹' : '$'; ?><?php echo number_format($invoice->amount, 2); ?></td>
                            <td><?php echo htmlspecialchars($invoice->due_date); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $statusColor; ?>-subtle text-<?php echo $statusColor; ?> border border-<?php echo $statusColor; ?>-subtle">
                                    <?php echo strtoupper($invoice->status); ?>
                                </span>
                            </td>
                            <?php if ($canFinance): ?>
                            <td class="text-secondary small">
                                <?php if ($invoice->status === 'paid' && !empty($invoice->utr_number)): ?>
                                    <span class="text-success"><i class="fa-solid fa-landmark me-1"></i>UTR: <?php echo htmlspecialchars($invoice->utr_number); ?></span>
                                <?php elseif ($invoice->status === 'cancelled' && !empty($invoice->cancel_reason)): ?>
                                    <span class="text-warning"><i class="fa-solid fa-comment-slash me-1"></i><?php echo htmlspecialchars($invoice->cancel_reason); ?></span>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <!-- Email button (manager, admin, finance) -->
                                    <form action="index.php?route=invoices/mail/<?php echo $invoice->invoice_id; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Email this invoice to client stakeholders?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm" title="Email Invoice to Client">
                                            <i class="fa-solid fa-envelope"></i>
                                        </button>
                                    </form>
                                    <!-- Print button -->
                                    <a href="index.php?route=invoices/show/<?php echo $invoice->invoice_id; ?>" target="_blank" class="btn btn-outline-light btn-sm" title="View / Print PDF">
                                        <i class="fa-solid fa-print"></i>
                                    </a>

                                    <?php if ($canFinance && !$isFinalised): ?>
                                        <!-- Mark as Paid -->
                                        <button type="button"
                                            class="btn btn-outline-success btn-sm"
                                            title="Mark as Paid"
                                            data-bs-toggle="modal"
                                            data-bs-target="#paidModal"
                                            data-invoice-id="<?php echo $invoice->invoice_id; ?>"
                                            data-invoice-num="<?php echo htmlspecialchars($invoice->invoice_number); ?>">
                                            <i class="fa-solid fa-circle-check me-1"></i>Paid
                                        </button>
                                        <!-- Mark as Cancelled -->
                                        <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            title="Cancel Invoice"
                                            data-bs-toggle="modal"
                                            data-bs-target="#cancelModal"
                                            data-invoice-id="<?php echo $invoice->invoice_id; ?>"
                                            data-invoice-num="<?php echo htmlspecialchars($invoice->invoice_number); ?>">
                                            <i class="fa-solid fa-ban me-1"></i>Cancel
                                        </button>
                                    <?php elseif ($isFinalised): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2">Finalised</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canFinance): ?>
<!-- ═══════════════════════════════════════════════════════════
     MARK AS PAID MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="paidModal" tabindex="-1" aria-labelledby="paidModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(25,135,84,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-circle-check text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="paidModalLabel">Mark Invoice as Paid</h5>
                        <small class="text-secondary" id="paid-invoice-subtitle"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paidForm" method="POST" action="">
                <div class="modal-body pt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <label for="utr_number" class="form-label text-secondary small fw-semibold">UTR / Reference Number <span class="text-danger">*</span></label>
                    <input type="text"
                           id="utr_number"
                           name="utr_number"
                           class="form-control"
                           style="background:#0d0f1a;border-color:rgba(255,255,255,0.1);color:#fff;border-radius:10px;"
                           placeholder="e.g. UTR123456789012"
                           required
                           maxlength="100">
                    <div class="form-text text-secondary mt-1">Enter the bank UTR or payment reference number for this transaction.</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4">
                        <i class="fa-solid fa-circle-check me-1"></i>Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     CANCEL INVOICE MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(220,53,69,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-ban text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0" id="cancelModalLabel">Cancel Invoice</h5>
                        <small class="text-secondary" id="cancel-invoice-subtitle"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cancelForm" method="POST" action="">
                <div class="modal-body pt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <label for="cancel_reason" class="form-label text-secondary small fw-semibold">Cancellation Reason <span class="text-danger">*</span></label>
                    <textarea id="cancel_reason"
                              name="cancel_reason"
                              class="form-control"
                              style="background:#0d0f1a;border-color:rgba(255,255,255,0.1);color:#fff;border-radius:10px;resize:vertical;"
                              rows="3"
                              placeholder="Describe why this invoice is being cancelled…"
                              required
                              maxlength="500"></textarea>
                    <div class="form-text text-secondary mt-1">This reason will be stored against the invoice record.</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Go Back</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4">
                        <i class="fa-solid fa-ban me-1"></i>Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Wire modal data when triggered
document.addEventListener('DOMContentLoaded', function () {
    var paidModal = document.getElementById('paidModal');
    if (paidModal) {
        paidModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            var id  = btn.getAttribute('data-invoice-id');
            var num = btn.getAttribute('data-invoice-num');
            document.getElementById('paid-invoice-subtitle').textContent = 'Invoice: ' + num;
            document.getElementById('paidForm').action = 'index.php?route=invoices/markPaid/' + id;
            document.getElementById('utr_number').value = '';
        });
    }

    var cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            var id  = btn.getAttribute('data-invoice-id');
            var num = btn.getAttribute('data-invoice-num');
            document.getElementById('cancel-invoice-subtitle').textContent = 'Invoice: ' + num;
            document.getElementById('cancelForm').action = 'index.php?route=invoices/markCancelled/' + id;
            document.getElementById('cancel_reason').value = '';
        });
    }
});
</script>
<?php endif; ?>

<script>
    $(document).ready(function() {
        if ($('#invoices-table tbody tr').length > 1 || !$('#invoices-table tbody tr td').hasClass('text-center')) {
            var table = $('#invoices-table').DataTable({
                "pageLength": 10,
                "lengthChange": false,
                "info": false,
                "searching": true,
                "language": {
                    "search": "Filter Invoices:"
                }
            });
            $('#invoice-status-filter').on('change', function() {
                var val = $(this).val();
                table.column(4).search(val ? val : '', false, true).draw();
            });
        }
    });
</script>
