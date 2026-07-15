<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($invoice->invoice_number); ?></title>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #fff;
            padding: 2rem;
        }

        .invoice-card {
            max-width: 800px;
            margin: auto;
            border: 1px solid #eaeaea;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .invoice-title {
            font-weight: 700;
            color: #1a1c29;
            font-size: 2.5rem;
        }

        .company-details {
            text-align: right;
            font-size: 0.875rem;
            color: #666;
        }

        .billing-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2.5rem;
        }

        .billing-col {
            width: 48%;
        }

        .billing-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #999;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .billing-value {
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .invoice-table {
            width: 100%;
            margin-bottom: 2rem;
        }

        .invoice-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #1a1c29;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #eaeaea;
        }

        .invoice-table td {
            padding: 1rem;
            border-bottom: 1px solid #eaeaea;
            font-size: 0.95rem;
        }

        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .totals-table {
            width: 300px;
        }

        .totals-table td {
            padding: 0.5rem 0;
            font-size: 1rem;
        }

        .grand-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: #6366f1;
            border-top: 2px solid #eaeaea;
            padding-top: 0.5rem;
        }

        .invoice-footer {
            margin-top: 4rem;
            border-top: 1px solid #eaeaea;
            padding-top: 1.5rem;
            text-align: center;
            color: #999;
            font-size: 0.8rem;
        }

        /* Print Button Floating Control */
        .floating-print-bar {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 100;
        }

        @media print {
            .floating-print-bar {
                display: none;
            }
            body {
                padding: 0;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Action Button -->
    <div class="floating-print-bar">
        <button onclick="window.print()" class="btn btn-primary btn-lg shadow px-4 py-2" style="background: #6366f1; border:none; border-radius:30px;">
            <i class="fa-solid fa-print me-2"></i>Print / Save PDF
        </button>
    </div>

    <div class="invoice-card">
        <div class="invoice-header">
            <div>
                <h1 class="invoice-title">INVOICE</h1>
                <div style="font-size: 1.1rem; color: #666; font-weight:500;">
                    <?php echo htmlspecialchars($invoice->invoice_number); ?>
                </div>
            </div>
            <div class="company-details">
                <?php if (!empty($invoice->sender_details)): ?>
                    <?php echo nl2br(htmlspecialchars($invoice->sender_details)); ?>
                <?php else: ?>
                    <h5 style="color:#6366f1; font-weight:700; margin-bottom: 0.25rem;">Raptor Marketing Agency</h5>
                    <div>100 Creator Square, Business Bay</div>
                    <div>billing@raptor-agency.com</div>
                    <div>+1 (555) 0100</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="billing-row">
            <div class="billing-col">
                <div class="billing-title">Billed To</div>
                <div class="billing-value">
                    <strong><?php echo htmlspecialchars($invoice->company_name); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice->billing_address ?? 'N/A')); ?><br>
                    Email: <?php echo htmlspecialchars($invoice->client_email ?? 'N/A'); ?>
                </div>
            </div>

            <div class="billing-col" style="text-align: right;">
                <div class="billing-title">Invoice Details</div>
                <div class="billing-value">
                    Date Issued: <strong><?php echo date('Y-m-d', strtotime($invoice->created_at)); ?></strong><br>
                    Payment Due: <strong><?php echo htmlspecialchars($invoice->due_date); ?></strong><br>
                    Status: <strong style="color: <?php echo $invoice->status === 'paid' ? '#10b981' : '#ef4444'; ?>; text-transform: uppercase;">
                        <?php echo htmlspecialchars($invoice->status); ?>
                    </strong>
                </div>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end" style="width: 150px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $symbol = ($invoice->currency === 'INR') ? '₹' : '$'; ?>
                <tr>
                    <td>
                        <strong>Monthly Digital Marketing Retainer</strong><br>
                        <span class="text-secondary" style="font-size: 0.85rem;">Includes social media posting, search ad optimization, and analytics reporting.</span>
                    </td>
                    <td class="text-end font-weight-bold"><?php echo $symbol; ?><?php echo number_format($invoice->amount, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-end font-weight-bold"><?php echo $symbol; ?><?php echo number_format($invoice->amount, 2); ?></td>
                </tr>
                <tr>
                    <td>Tax (0%):</td>
                    <td class="text-end font-weight-bold"><?php echo $symbol; ?>0.00</td>
                </tr>
                <tr class="grand-total">
                    <td>Amount Due:</td>
                    <td class="text-end"><?php echo $symbol; ?><?php echo number_format($invoice->amount, 2); ?></td>
                </tr>
            </tbody>
        </div>

        <div class="invoice-footer">
            <p>Thank you for your business! Please send payments via Bank Transfer or Stripe invoice link.</p>
            <p style="font-size:0.7rem;">RAPTOR - Generated on <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <!-- Font Awesome CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
