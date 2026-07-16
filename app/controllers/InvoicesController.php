<?php
// Raptor CRM Invoices Controller

class InvoicesController extends Controller {
    private $invoiceModel;
    private $clientModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('invoices', 'view');

        $this->invoiceModel = $this->model('Invoice');
        $this->clientModel = $this->model('Client');
    }

    // List all invoices
    public function index() {
        $invoices = $this->invoiceModel->getInvoices();

        $data = [
            'title' => 'Billing Ledger | Raptor CRM',
            'active_tab' => 'finance',
            'invoices' => $invoices
        ];

        $this->viewWithLayout('invoices/index', 'main', $data);
    }

    // Create invoice
    public function add() {
        $this->requirePermission('invoices', 'create');
        $clients = $this->clientModel->getClients();

        // Get single source of truth conversion rate from settings table
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'billing.conversion_rate_usd_to_inr'");
        $conversionRate = (float)($stmt->fetchColumn() ?: 83.50);

        $data = [
            'title' => 'Generate Invoice | Raptor CRM',
            'active_tab' => 'finance',
            'clients' => $clients,
            'client_id' => '',
            'invoice_number' => 'INV-' . time(),
            'amount' => '',
            'status' => 'unpaid',
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'billing_address' => '',
            'sender_details' => '',
            'currency' => 'USD',
            'conversion_rate' => $conversionRate,
            'amount_err' => '',
            'client_err' => '',
            'due_date_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];

            $data['client_id'] = trim($_POST['client_id'] ?? '');
            $data['invoice_number'] = trim($_POST['invoice_number'] ?? '');
            $data['amount'] = trim($_POST['amount'] ?? '');
            $data['status'] = trim($_POST['status'] ?? 'unpaid');
            $data['due_date'] = trim($_POST['due_date'] ?? '');
            $data['billing_address'] = trim($_POST['billing_address'] ?? '');
            $data['sender_details'] = trim($_POST['sender_details'] ?? '');
            $data['currency'] = trim($_POST['currency'] ?? 'USD');
            $data['conversion_rate'] = $conversionRate;
            $data['due_date_err'] = '';

            // Validate
            if (empty($data['client_id'])) {
                $data['client_err'] = 'Please select a client';
            }
            if (empty($data['amount']) || !is_numeric($data['amount'])) {
                $data['amount_err'] = 'Please enter a valid amount';
            }
            if (empty($data['due_date'])) {
                $data['due_date_err'] = 'Please enter a due date';
            } elseif (strtotime($data['due_date']) < strtotime(date('Y-m-d'))) {
                $data['due_date_err'] = 'Due date cannot be earlier than invoice date';
            }

            if (empty($data['amount_err']) && empty($data['client_err']) && empty($data['due_date_err'])) {
                if ($this->invoiceModel->addInvoice($data)) {
                    $invoiceId = $this->invoiceModel->lastInsertId();
                    
                    // Fetch client to retrieve email and name
                    $client = $this->clientModel->getClientById($data['client_id']);

                    // Send email notifications to client and selected contact persons
                    $recipients = [];
                    if ($client && !empty($client->email)) {
                        $recipients[] = $client->email;
                    }

                    if (!empty($_POST['selected_contacts']) && is_array($_POST['selected_contacts'])) {
                        foreach ($_POST['selected_contacts'] as $contactEmail) {
                            if (filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                                $recipients[] = $contactEmail;
                            }
                        }
                    }

                    // Add custom email participants / CCs
                    if (!empty($_POST['custom_email_participants'])) {
                        $rawParticipants = str_replace(';', ',', $_POST['custom_email_participants']);
                        $customRecipients = explode(',', $rawParticipants);
                        foreach ($customRecipients as $email) {
                            $email = trim($email);
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $recipients[] = $email;
                            }
                        }
                    }

                    $recipients = array_unique($recipients);
                    $sendEmail = isset($_POST['send_email_on_generate']) && $_POST['send_email_on_generate'] === '1';

                    if ($sendEmail && $recipients) {
                        $invoiceUrl = URLROOT . '/index.php?route=invoices/show/' . $invoiceId;
                        
                        // Use custom subject if provided
                        $subject = !empty($_POST['custom_email_subject']) ? trim($_POST['custom_email_subject']) : ("Invoice Generated: " . $data['invoice_number']);
                        
                        // Use custom body if provided, and swap dynamic link placeholder
                        if (!empty($_POST['custom_email_body'])) {
                            $body = trim($_POST['custom_email_body']);
                            $body = str_replace('[Invoice Link will be generated automatically]', $invoiceUrl, $body);
                        } else {
                            $currencySymbol = ($data['currency'] === 'INR') ? 'Rs. ' : '$';
                            $body = "Hello,\n\nAn invoice has been generated for your company, " . ($client ? $client->company_name : 'Valued Client') . ".\n\n" .
                                    "Details:\n" .
                                    "- Invoice Number: " . $data['invoice_number'] . "\n" .
                                    "- Amount: " . $currencySymbol . number_format((float)$data['amount'], 2) . "\n" .
                                    "- Due Date: " . $data['due_date'] . "\n\n" .
                                    "Please click the link below to view or print your invoice:\n" .
                                    $invoiceUrl . "\n\n" .
                                    "Best regards,\nRaptor Billing Team";
                        }
                        
                        foreach ($recipients as $email) {
                            @mail($email, $subject, $body);
                        }
                    }

                    $this->redirect('index.php?route=invoices/index');
                    return;
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('invoices/add', 'main', $data);
    }

    // View Invoice (Ready to Print)
    public function show($id) {
        $invoice = $this->invoiceModel->getInvoiceById($id);
        if (!$invoice) {
            $this->redirect('index.php?route=invoices/index');
        }

        $data = [
            'title' => 'Invoice ' . $invoice->invoice_number,
            'active_tab' => 'finance',
            'invoice' => $invoice
        ];

        // Render plain view (not inside layout) so it is clean for printing or downloading
        parent::view('invoices/view', $data);
    }

    // Toggle Payment status via AJAX/Post
    public function toggleStatus($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $invoice = $this->invoiceModel->getInvoiceById($id);
            if ($invoice) {
                $newStatus = $invoice->status === 'paid' ? 'unpaid' : 'paid';
                $this->invoiceModel->updateStatus($id, $newStatus);
            }
        }
        $this->redirect('index.php?route=invoices/index');
    }

    // Delete invoice - disabled by governance policy
    public function delete($id) {
        $this->redirect('index.php?route=invoices/index');
    }

    // Mark invoice as Paid (admin/finance only) - records UTR number
    public function markPaid($id) {
        $this->requirePermission('invoices', 'edit');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $utr = trim($_POST['utr_number'] ?? '');
            if (empty($utr)) {
                $_SESSION['invoice_error'] = 'UTR number is required to mark an invoice as Paid.';
            } else {
                $invoice = $this->invoiceModel->getInvoiceById($id);
                if ($invoice && $invoice->status !== 'paid' && $invoice->status !== 'cancelled') {
                    $this->invoiceModel->updatePayment($id, $utr);
                    $_SESSION['invoice_success'] = 'Invoice marked as Paid. UTR: ' . htmlspecialchars($utr);
                } else {
                    $_SESSION['invoice_error'] = 'Invoice is already finalised or not found.';
                }
            }
        }
        $this->redirect('index.php?route=invoices/index');
    }

    // Mark invoice as Cancelled (admin/finance only) - records cancellation reason
    public function markCancelled($id) {
        $this->requirePermission('invoices', 'edit');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reason = trim($_POST['cancel_reason'] ?? '');
            if (empty($reason)) {
                $_SESSION['invoice_error'] = 'Cancellation reason is required.';
            } else {
                $invoice = $this->invoiceModel->getInvoiceById($id);
                if ($invoice && $invoice->status !== 'paid' && $invoice->status !== 'cancelled') {
                    $this->invoiceModel->updateCancellation($id, $reason);
                    $_SESSION['invoice_success'] = 'Invoice has been cancelled.';
                } else {
                    $_SESSION['invoice_error'] = 'Invoice is already finalised or not found.';
                }
            }
        }
        $this->redirect('index.php?route=invoices/index');
    }

    // Email Invoice to Client Stakeholders manually
    public function mail($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $invoice = $this->invoiceModel->getInvoiceById($id);
            if ($invoice) {
                // Fetch client details
                $client = $this->clientModel->getClientById($invoice->client_id);
                
                // Fetch stakeholders
                $contacts = $this->clientModel->getContactsByClientId($invoice->client_id);
                
                $recipients = [];
                if ($client && !empty($client->email)) {
                    $recipients[] = $client->email;
                }
                foreach ($contacts as $contact) {
                    if (filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = $contact->email;
                    }
                }
                
                $recipients = array_unique($recipients);
                
                if ($recipients) {
                    $invoiceUrl = URLROOT . '/index.php?route=invoices/show/' . $invoice->invoice_id;
                    $subject = "Invoice Details: " . $invoice->invoice_number;
                    $currencySymbol = ($invoice->currency === 'INR') ? 'Rs. ' : '$';
                    $body = "Hello,\n\nPlease find the details of invoice " . $invoice->invoice_number . " generated for your company, " . ($client ? $client->company_name : 'Valued Client') . ".\n\n" .
                            "Details:\n" .
                            "- Invoice Number: " . $invoice->invoice_number . "\n" .
                            "- Amount: " . $currencySymbol . number_format((float)$invoice->amount, 2) . "\n" .
                            "- Due Date: " . $invoice->due_date . "\n\n" .
                            "Please click the link below to view or print your invoice:\n" .
                            $invoiceUrl . "\n\n" .
                            "Best regards,\nRaptor Billing Team";
                    
                    $success = true;
                    foreach ($recipients as $email) {
                        if (!@mail($email, $subject, $body)) {
                            $success = false;
                        }
                    }
                    
                    if ($success) {
                        $_SESSION['invoice_success'] = "Invoice emailed successfully to client stakeholders.";
                    } else {
                        $_SESSION['invoice_error'] = "Emailed dispatch succeeded partially/fully (local sandbox simulation).";
                    }
                } else {
                    $_SESSION['invoice_error'] = "Client company has no registered email addresses.";
                }
            } else {
                $_SESSION['invoice_error'] = "Invoice record not found.";
            }
        }
        $this->redirect('index.php?route=invoices/index');
    }
}
