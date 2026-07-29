<?php
// Raptor CRM Customers Controller

class CustomersController extends Controller {
    private $customerModel;
    private $leadModel;
    private $clientModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('crm_leads', 'view');

        $this->customerModel = $this->model('Customer');
        $this->leadModel = $this->model('Lead');
        $this->clientModel = $this->model('Client');
    }

    public function index() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'customer_type' => $_GET['customer_type'] ?? '',
            'owner_employee_id' => $_GET['owner_employee_id'] ?? '',
            'tag' => $_GET['tag'] ?? '',
            'search' => $_GET['search'] ?? '',
        ];

        $customers = $this->customerModel->getCustomers($filters);
        $employees = $this->getEmployees();

        $data = [
            'title' => 'Customer Registry | Raptor CRM',
            'active_tab' => 'operations',
            'customers' => $customers,
            'employees' => $employees,
            'filters' => $filters,
            'statuses' => Customer::STATUSES,
            'types' => Customer::TYPES,
        ];

        $this->viewWithLayout('customers/index', 'main', $data);
    }

    public function add() {
        $employees = $this->getEmployees();
        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Capture Customer | Raptor CRM',
            'active_tab' => 'operations',
            'customer_code' => 'Auto-generated (e.g. CUST-2026-00001)',
            'converted_from_lead_id' => '',
            'first_name' => '',
            'company_name' => '',
            'customer_type' => 'Business',
            'email' => '',
            'phone' => '',
            'billing_address' => '',
            'shipping_address' => '',
            'owner_employee_id' => '',
            'associated_client_id' => '',
            'status' => 'Active',
            'onboarding_date' => date('Y-m-d'),
            'contract_value' => '0.00',
            'payment_terms' => 'Net 30',
            'products_subscribed' => '',
            'renewal_date' => '',
            'tags' => '',
            'notes' => '',
            'employees' => $employees,
            'clients' => $clients,
            'email_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_DEFAULT);

            $data['first_name'] = trim($_POST['first_name'] ?? '');
            $data['company_name'] = trim($_POST['company_name'] ?? '');
            $data['customer_type'] = trim($_POST['customer_type'] ?? 'Business');
            $data['email'] = trim($_POST['email'] ?? '');
            $data['phone'] = trim($_POST['phone'] ?? '');
            $data['billing_address'] = trim($_POST['billing_address'] ?? '');
            $data['shipping_address'] = trim($_POST['shipping_address'] ?? '');
            $data['owner_employee_id'] = trim($_POST['owner_employee_id'] ?? '');
            $data['associated_client_id'] = trim($_POST['associated_client_id'] ?? '');
            $data['status'] = trim($_POST['status'] ?? 'Active');
            $data['onboarding_date'] = trim($_POST['onboarding_date'] ?? date('Y-m-d'));
            $data['contract_value'] = trim($_POST['contract_value'] ?? '0.00');
            $data['payment_terms'] = trim($_POST['payment_terms'] ?? 'Net 30');
            $data['products_subscribed'] = trim($_POST['products_subscribed'] ?? '');
            $data['renewal_date'] = trim($_POST['renewal_date'] ?? '');
            $data['tags'] = trim($_POST['tags'] ?? '');
            $data['notes'] = trim($_POST['notes'] ?? '');
            $data['converted_from_lead_id'] = trim($_POST['converted_from_lead_id'] ?? '');

            if (empty($data['email'])) {
                $data['email_err'] = 'Please enter customer email address';
            }

            if (empty($data['email_err'])) {
                $id = $this->customerModel->addCustomer($data);
                if ($id) {
                    $_SESSION['flash_success'] = "Customer created successfully.";
                    $this->redirect('index.php?route=customers/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('customers/add', 'main', $data);
    }

    public function addFromLead($leadId = null) {
        $lead = null;
        if ($leadId) {
            $lead = $this->leadModel->getLeadById((int)$leadId);
        }

        $employees = $this->getEmployees();
        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Convert Lead to Customer | Raptor CRM',
            'active_tab' => 'operations',
            'customer_code' => 'Auto-generated (e.g. CUST-2026-00001)',
            'converted_from_lead_id' => $lead ? $lead->lead_id : '',
            'originating_lead_code' => $lead ? $lead->lead_code : '',
            'first_name' => $lead ? $lead->first_name : '',
            'company_name' => $lead ? $lead->company_name : '',
            'customer_type' => !empty($lead->company_name) ? 'Business' : 'Individual',
            'email' => $lead ? $lead->email : '',
            'phone' => $lead ? $lead->phone : '',
            'billing_address' => '',
            'shipping_address' => '',
            'owner_employee_id' => $lead ? $lead->owner_employee_id : '',
            'associated_client_id' => '',
            'status' => 'Active',
            'onboarding_date' => date('Y-m-d'),
            'contract_value' => $lead ? $lead->lead_value : '0.00',
            'payment_terms' => 'Net 30',
            'products_subscribed' => $lead ? $lead->lead_notes : '',
            'renewal_date' => date('Y-m-d', strtotime('+1 year')),
            'tags' => 'Converted Lead',
            'notes' => $lead ? ("Converted from Lead " . ($lead->lead_code ?: ('#' . $lead->lead_id))) : '',
            'employees' => $employees,
            'clients' => $clients,
            'email_err' => ''
        ];

        $this->viewWithLayout('customers/add', 'main', $data);
    }

    public function edit($id = null) {
        $customer = $this->customerModel->getCustomerById((int)$id);
        if (!$customer) {
            $this->redirect('index.php?route=customers/index');
        }

        $employees = $this->getEmployees();
        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Edit Customer Profile | Raptor CRM',
            'active_tab' => 'operations',
            'customer' => $customer,
            'employees' => $employees,
            'clients' => $clients,
            'email_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_DEFAULT);

            $updateData = [
                'customer_id' => $customer->customer_id,
                'first_name' => trim($_POST['first_name'] ?? ''),
                'company_name' => trim($_POST['company_name'] ?? ''),
                'customer_type' => trim($_POST['customer_type'] ?? 'Business'),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'billing_address' => trim($_POST['billing_address'] ?? ''),
                'shipping_address' => trim($_POST['shipping_address'] ?? ''),
                'owner_employee_id' => trim($_POST['owner_employee_id'] ?? ''),
                'associated_client_id' => trim($_POST['associated_client_id'] ?? ''),
                'status' => trim($_POST['status'] ?? 'Active'),
                'onboarding_date' => trim($_POST['onboarding_date'] ?? ''),
                'contract_value' => trim($_POST['contract_value'] ?? '0.00'),
                'payment_terms' => trim($_POST['payment_terms'] ?? 'Net 30'),
                'products_subscribed' => trim($_POST['products_subscribed'] ?? ''),
                'renewal_date' => trim($_POST['renewal_date'] ?? ''),
                'tags' => trim($_POST['tags'] ?? ''),
                'notes' => trim($_POST['notes'] ?? '')
            ];

            if (empty($updateData['email'])) {
                $data['email_err'] = 'Please enter customer email address';
            }

            if (empty($data['email_err'])) {
                if ($this->customerModel->updateCustomer($updateData)) {
                    $_SESSION['flash_success'] = "Customer updated successfully.";
                    $this->redirect('index.php?route=customers/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('customers/edit', 'main', $data);
    }

    public function detail($id = null) {
        $customer = $this->customerModel->getCustomerById((int)$id);
        if (!$customer) {
            $this->redirect('index.php?route=customers/index');
        }

        $data = [
            'title' => 'Customer Profile | Raptor CRM',
            'active_tab' => 'operations',
            'customer' => $customer
        ];

        $this->viewWithLayout('customers/detail', 'main', $data);
    }

    public function downloadSampleCsv() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_bulk_import_sample.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Company Name', 'First Name', 'Customer Type', 'Email Address', 'Phone Number', 'Contract Value ($)', 'Billing Address', 'Payment Terms', 'Tags/Segment']);
        fputcsv($output, ['Nexus Holdings', 'Alex Mercer', 'Business', 'alex.mercer@nexus.local', '+1 555-019800', '45000.00', '100 Corporate Blvd, Suite 400', 'Net 30', 'Enterprise']);
        fputcsv($output, ['Solaria Corp', 'Elena Rostova', 'Business', 'elena@solaria.local', '+1 555-019801', '28000.00', '450 Tech Way, Austin TX', 'Prepaid', 'VIP']);
        fclose($output);
        exit();
    }

    public function uploadCsv() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                if ($handle !== false) {
                    $header = fgetcsv($handle, 1000, ',');
                    $importedCount = 0;

                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if (empty($row) || count($row) < 1 || (empty(trim($row[0])) && empty(trim($row[1])))) {
                            continue;
                        }

                        $data = [
                            'company_name' => trim($row[0] ?? ''),
                            'first_name' => trim($row[1] ?? ''),
                            'customer_type' => !empty(trim($row[2] ?? '')) ? trim($row[2]) : 'Business',
                            'email' => trim($row[3] ?? ''),
                            'phone' => trim($row[4] ?? ''),
                            'contract_value' => is_numeric(trim($row[5] ?? '')) ? trim($row[5]) : '0.00',
                            'billing_address' => trim($row[6] ?? ''),
                            'payment_terms' => !empty(trim($row[7] ?? '')) ? trim($row[7]) : 'Net 30',
                            'tags' => trim($row[8] ?? ''),
                            'status' => 'Active',
                            'onboarding_date' => date('Y-m-d')
                        ];

                        if (!empty($data['email'])) {
                            if ($this->customerModel->addCustomer($data)) {
                                $importedCount++;
                            }
                        }
                    }

                    fclose($handle);
                    $_SESSION['flash_success'] = "Successfully imported {$importedCount} customers via CSV.";
                } else {
                    $_SESSION['flash_error'] = "Failed to open uploaded CSV file.";
                }
            } else {
                $_SESSION['flash_error'] = "Please select a valid CSV file to upload.";
            }
        }

        $this->redirect('index.php?route=customers/index');
    }

    private function getEmployees() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT e.employee_id, u.name, e.department, e.job_title FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.status = "active" ORDER BY u.name ASC');
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }
}
