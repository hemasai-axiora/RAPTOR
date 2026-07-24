<?php
// Raptor CRM Clients Controller

class ClientsController extends Controller {
    private $clientModel;

    public function __construct() {
        $this->requireAuth();
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager', 'analyst'], true)) {
            $this->requirePermission('customers', 'view');
        }
        
        $this->clientModel = $this->model('Client');
    }

    // List clients
    public function index() {
        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Client Directory | Raptor CRM',
            'active_tab' => 'operations',
            'clients' => $clients,
            'can_edit' => in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)
        ];

        $this->viewWithLayout('clients/index', 'main', $data);
    }

    // Add Client
    public function add() {
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)) {
            $this->requirePermission('customers', 'create');
        }

        $data = [
            'title' => 'Add Client | Raptor CRM',
            'active_tab' => 'operations',
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'status' => 'active',
            'contract_start' => '',
            'contract_end' => '',
            'package_details' => '',
            'billing_address' => '',
            'company_name_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['company_name'] = trim($_POST['company_name']);
            $data['email'] = trim($_POST['email']);
            $data['phone'] = trim($_POST['phone']);
            $data['status'] = trim($_POST['status']);
            $data['contract_start'] = trim($_POST['contract_start']);
            $data['contract_end'] = trim($_POST['contract_end']);
            $data['package_details'] = trim($_POST['package_details']);
            $data['billing_address'] = trim($_POST['billing_address']);

            // Validate
            if (empty($data['company_name'])) {
                $data['company_name_err'] = 'Company name is required';
            } else {
                $startTs = strtotime($data['contract_start']);
                $endTs = strtotime($data['contract_end']);
                if ($data['contract_start'] !== '' && (!$startTs || date('Y', $startTs) < 2000)) {
                    $data['company_name_err'] = 'Contract start date must be Year 2000 or later.';
                } elseif ($data['contract_end'] !== '' && (!$endTs || date('Y', $endTs) < 2000)) {
                    $data['company_name_err'] = 'Contract end date must be Year 2000 or later.';
                } elseif ($data['contract_start'] !== '' && $data['contract_end'] !== '' && $endTs < $startTs) {
                    $data['company_name_err'] = 'Contract end date cannot be earlier than start date.';
                }
            }

            if (empty($data['company_name_err'])) {
                if ($this->clientModel->addClient($data)) {
                    $this->redirect('index.php?route=clients/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('clients/add', 'main', $data);
    }

    // Edit Client
    public function edit($id) {
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)) {
            $this->requirePermission('customers', 'edit');
        }

        $client = $this->clientModel->getClientById($id);
        if (!$client) {
            $this->redirect('index.php?route=clients/index');
        }

        $data = [
            'title' => 'Edit Client | Raptor CRM',
            'active_tab' => 'operations',
            'client_id' => $client->client_id,
            'company_name' => $client->company_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'status' => $client->status,
            'contract_start' => $client->contract_start,
            'contract_end' => $client->contract_end,
            'package_details' => $client->package_details,
            'billing_address' => $client->billing_address,
            'company_name_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['company_name'] = trim($_POST['company_name']);
            $data['email'] = trim($_POST['email']);
            $data['phone'] = trim($_POST['phone']);
            $data['status'] = trim($_POST['status']);
            $data['contract_start'] = trim($_POST['contract_start']);
            $data['contract_end'] = trim($_POST['contract_end']);
            $data['package_details'] = trim($_POST['package_details']);
            $data['billing_address'] = trim($_POST['billing_address']);

            if (empty($data['company_name'])) {
                $data['company_name_err'] = 'Company name is required';
            } else {
                $startTs = strtotime($data['contract_start']);
                $endTs = strtotime($data['contract_end']);
                if ($data['contract_start'] !== '' && (!$startTs || date('Y', $startTs) < 2000)) {
                    $data['company_name_err'] = 'Contract start date must be Year 2000 or later.';
                } elseif ($data['contract_end'] !== '' && (!$endTs || date('Y', $endTs) < 2000)) {
                    $data['company_name_err'] = 'Contract end date must be Year 2000 or later.';
                } elseif ($data['contract_start'] !== '' && $data['contract_end'] !== '' && $endTs < $startTs) {
                    $data['company_name_err'] = 'Contract end date cannot be earlier than start date.';
                }
            }

            if (empty($data['company_name_err'])) {
                if ($this->clientModel->updateClient($data)) {
                    $this->redirect('index.php?route=clients/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $data['contacts'] = $this->clientModel->getContactsByClientId($id);
        $this->viewWithLayout('clients/edit', 'main', $data);
    }

    // Add Client Contact
    public function addContact() {
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)) {
            $this->requirePermission('customers', 'edit');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $clientId = (int)($_POST['client_id'] ?? 0);
            
            $data = [
                'client_id' => $clientId,
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'role_or_title' => trim($_POST['role_or_title'] ?? '')
            ];

            if ($clientId > 0 && !empty($data['name']) && !empty($data['email'])) {
                $this->clientModel->addContact($data);
                $_SESSION['client_success'] = 'Contact person added successfully.';
                $this->redirect('index.php?route=clients/edit/' . $clientId);
                return;
            }
        }
        $this->redirect('index.php?route=clients/index');
    }

    // Delete Client Contact
    public function deleteContact($contactId) {
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)) {
            $this->requirePermission('customers', 'edit');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $clientId = (int)($_POST['client_id'] ?? 0);

            if ($contactId > 0) {
                $this->clientModel->deleteContact($contactId);
                $_SESSION['client_success'] = 'Contact person deleted successfully.';
                if ($clientId > 0) {
                    $this->redirect('index.php?route=clients/edit/' . $clientId);
                    return;
                }
            }
        }
        $this->redirect('index.php?route=clients/index');
    }

    // Delete Client
    public function delete($id) {
        if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'ceo', 'manager'], true)) {
            $this->requirePermission('customers', 'delete');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->clientModel->deleteClient($id)) {
                $_SESSION['client_success'] = 'Client deleted successfully.';
            } else {
                $_SESSION['client_error'] = 'Failed to delete client.';
            }
        }
        $this->redirect('index.php?route=clients/index');
    }

    // JSON API to fetch client contacts/stakeholders
    public function contacts_api() {
        $clientId = (int)($_GET['client_id'] ?? 0);
        $contacts = $this->clientModel->getContactsByClientId($clientId);
        $this->jsonOk($contacts);
    }
}
