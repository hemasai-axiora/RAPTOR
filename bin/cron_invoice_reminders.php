<?php
/**
 * Raptor CRM — Invoice Due Date Reminders Cron Job
 * Checks for unpaid invoices due today, tomorrow, or 1 day overdue, and sends email reminders.
 */

require_once dirname(__DIR__) . '/app/config/config.php';
require_once APPROOT . '/core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch all unpaid invoices
    $sql = "SELECT i.*, c.company_name, c.email as client_email 
            FROM invoices i 
            JOIN clients c ON i.client_id = c.client_id 
            WHERE i.status = 'unpaid'";
    
    $stmt = $db->query($sql);
    $invoices = $stmt->fetchAll(PDO::FETCH_OBJ);

    $today = strtotime(date('Y-m-d'));
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting invoice reminders cron check...\n";
    $sentCount = 0;

    foreach ($invoices as $invoice) {
        $dueDate = strtotime($invoice->due_date);
        $diffDays = round(($dueDate - $today) / 86400);

        // Send reminder if: tomorrow (1), today (0), or 1 day overdue (-1)
        if ($diffDays === 1.0 || $diffDays === 0.0 || $diffDays === -1.0) {
            // Fetch contacts for this client
            $cStmt = $db->prepare("SELECT email FROM client_contacts WHERE client_id = :cid");
            $cStmt->execute([':cid' => $invoice->client_id]);
            $contacts = $cStmt->fetchAll(PDO::FETCH_COLUMN);

            $recipients = [];
            if (!empty($invoice->client_email)) {
                $recipients[] = $invoice->client_email;
            }
            foreach ($contacts as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
            $recipients = array_unique($recipients);

            if ($recipients) {
                $invoiceUrl = URLROOT . '/index.php?route=invoices/show/' . $invoice->invoice_id;
                $currencySymbol = ($invoice->currency === 'INR') ? 'Rs. ' : '$';
                
                if ($diffDays === 1.0) {
                    $timeContext = "is due tomorrow";
                    $subject = "Reminder: Invoice " . $invoice->invoice_number . " is due tomorrow";
                } elseif ($diffDays === 0.0) {
                    $timeContext = "is due today";
                    $subject = "Reminder: Invoice " . $invoice->invoice_number . " is due today";
                } else {
                    $timeContext = "is 1 day OVERDUE";
                    $subject = "URGENT: Invoice " . $invoice->invoice_number . " is 1 day overdue";
                }

                $body = "Hello,\n\n" .
                        "This is an automated reminder that invoice " . $invoice->invoice_number . " for " . $invoice->company_name . " " . $timeContext . ".\n\n" .
                        "Details:\n" .
                        "- Invoice Number: " . $invoice->invoice_number . "\n" .
                        "- Amount: " . $currencySymbol . number_format((float)$invoice->amount, 2) . "\n" .
                        "- Due Date: " . $invoice->due_date . "\n\n" .
                        "Please click the link below to view or pay your invoice:\n" .
                        $invoiceUrl . "\n\n" .
                        "Thank you for your business!\n\n" .
                        "Best regards,\nRaptor Billing Team";

                foreach ($recipients as $email) {
                    @mail($email, $subject, $body);
                }
                echo "  -> Sent reminder for {$invoice->invoice_number} to " . implode(', ', $recipients) . " (diff: {$diffDays} days)\n";
                $sentCount++;
            }
        }
    }
    echo "[" . date('Y-m-d H:i:s') . "] Completed. Sent {$sentCount} reminder email batch(es).\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
}
