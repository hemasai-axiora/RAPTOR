<?php
// Raptor CRM Campaigns Controller

class CampaignsController extends Controller {
    private $campaignModel;
    private $clientModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('social_media', 'view');
        
        $this->campaignModel = $this->model('Campaign');
        $this->clientModel = $this->model('Client');
    }

    private function getEmployees() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT e.employee_id, e.user_id, u.name, e.job_title, e.department, r.role_name
                                  FROM employees e
                                  JOIN users u ON e.user_id = u.user_id
                                  JOIN roles r ON u.role_id = r.role_id
                                  WHERE u.status = "active" AND e.status = "active"
                                  ORDER BY u.name ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }

    private function handleFileUpload(?array $file): ?string {
        if (!$file || empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            return null;
        }

        $uploadDir = APPROOT . '/../public/uploads/campaigns/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'uploads/campaigns/' . $filename;
        }

        return null;
    }

    // List all campaigns
    public function index() {
        $campaigns = $this->campaignModel->getCampaigns();

        $data = [
            'title' => 'Campaign Registry | Raptor CRM',
            'active_tab' => 'operations',
            'campaigns' => $campaigns,
            'can_edit' => in_array($_SESSION['user_role'], ['admin', 'manager'])
        ];

        $this->viewWithLayout('campaigns/index', 'main', $data);
    }

    // Add new campaign
    public function add() {
        $this->requirePermission('social_media', 'create');

        $clients = $this->clientModel->getClients();
        $employees = $this->getEmployees();

        $data = [
            'title' => 'Create Campaign | Raptor CRM',
            'active_tab' => 'operations',
            'clients' => $clients,
            'employees' => $employees,
            'campaign_code' => $this->campaignModel->generateCampaignCode(),
            'client_id' => '',
            'owner_employee_id' => '',
            'name' => '',
            'channel' => 'LinkedIn',
            'campaign_type' => 'online',
            'vendor_name' => '',
            'location' => '',
            'reach_estimate' => '',
            'proof_of_execution' => '',
            'budget' => '',
            'start_date' => '',
            'end_date' => '',
            'status' => 'active',
            'name_err' => '',
            'client_err' => '',
            'budget_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['campaign_code'] = null;
            $data['client_id'] = trim($_POST['client_id'] ?? '');
            $data['owner_employee_id'] = trim($_POST['owner_employee_id'] ?? '');
            $data['name'] = trim($_POST['name'] ?? '');
            $data['channel'] = trim($_POST['channel'] ?? 'LinkedIn');
            $data['campaign_type'] = in_array($_POST['campaign_type'] ?? '', ['online', 'offline'], true) ? $_POST['campaign_type'] : 'online';
            $data['vendor_name'] = trim($_POST['vendor_name'] ?? '');
            $data['location'] = trim($_POST['location'] ?? '');
            $data['reach_estimate'] = trim($_POST['reach_estimate'] ?? '');
            $data['budget'] = trim($_POST['budget'] ?? '');
            $data['start_date'] = trim($_POST['start_date'] ?? '');
            $data['end_date'] = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
            $data['status'] = trim($_POST['status'] ?? 'active');

            // Handle Proof of Execution File Upload
            $uploadedPath = $this->handleFileUpload($_FILES['proof_of_execution_file'] ?? null);
            if ($uploadedPath) {
                $data['proof_of_execution'] = $uploadedPath;
            }

            // Validate
            if (empty($data['client_id'])) {
                $data['client_err'] = 'Please select a client';
            }
            if (empty($data['name'])) {
                $data['name_err'] = 'Please enter campaign name';
            }
            if (empty($data['budget']) || !is_numeric($data['budget'])) {
                $data['budget_err'] = 'Please enter a valid budget';
            }

            if (empty($data['name_err']) && empty($data['client_err']) && empty($data['budget_err'])) {
                if ($this->campaignModel->addCampaign($data)) {
                    $this->redirect('index.php?route=campaigns/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('campaigns/add', 'main', $data);
    }

    // Edit campaign
    public function edit($id) {
        $this->requirePermission('social_media', 'edit');

        $campaign = $this->campaignModel->getCampaignById($id);
        if (!$campaign) {
            $this->redirect('index.php?route=campaigns/index');
        }

        $clients = $this->clientModel->getClients();
        $employees = $this->getEmployees();

        $data = [
            'title' => 'Edit Campaign | Raptor CRM',
            'active_tab' => 'operations',
            'clients' => $clients,
            'employees' => $employees,
            'campaign_id' => $campaign->campaign_id,
            'campaign_code' => $campaign->campaign_code ?: ('CMP-' . date('Y') . '-' . sprintf('%05d', $campaign->campaign_id)),
            'client_id' => $campaign->client_id,
            'owner_employee_id' => $campaign->owner_employee_id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
            'campaign_type' => $campaign->campaign_type ?? 'online',
            'vendor_name' => $campaign->vendor_name,
            'location' => $campaign->location,
            'reach_estimate' => $campaign->reach_estimate,
            'proof_of_execution' => $campaign->proof_of_execution,
            'budget' => $campaign->budget,
            'spend' => $campaign->spend,
            'revenue_influenced' => $campaign->revenue_influenced,
            'start_date' => $campaign->start_date,
            'end_date' => $campaign->end_date,
            'status' => $campaign->status,
            'name_err' => '',
            'client_err' => '',
            'budget_err' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);

            $data['client_id'] = trim($_POST['client_id'] ?? '');
            $data['owner_employee_id'] = trim($_POST['owner_employee_id'] ?? '');
            $data['name'] = trim($_POST['name'] ?? '');
            $data['channel'] = trim($_POST['channel'] ?? 'LinkedIn');
            $data['campaign_type'] = in_array($_POST['campaign_type'] ?? '', ['online', 'offline'], true) ? $_POST['campaign_type'] : 'online';
            $data['vendor_name'] = trim($_POST['vendor_name'] ?? '');
            $data['location'] = trim($_POST['location'] ?? '');
            $data['reach_estimate'] = trim($_POST['reach_estimate'] ?? '');
            $data['budget'] = trim($_POST['budget'] ?? '');
            $data['spend'] = trim($_POST['spend'] ?? '0');
            $data['revenue_influenced'] = trim($_POST['revenue_influenced'] ?? '0');
            $data['start_date'] = trim($_POST['start_date'] ?? '');
            $data['end_date'] = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
            $data['status'] = trim($_POST['status'] ?? 'active');

            // Handle Proof of Execution File Upload
            $uploadedPath = $this->handleFileUpload($_FILES['proof_of_execution_file'] ?? null);
            if ($uploadedPath) {
                $data['proof_of_execution'] = $uploadedPath;
            }

            // Validate
            if (empty($data['client_id'])) {
                $data['client_err'] = 'Please select a client';
            }
            if (empty($data['name'])) {
                $data['name_err'] = 'Please enter campaign name';
            }
            if (empty($data['budget']) || !is_numeric($data['budget'])) {
                $data['budget_err'] = 'Please enter a valid budget';
            }

            if (empty($data['name_err']) && empty($data['client_err']) && empty($data['budget_err'])) {
                if ($this->campaignModel->updateCampaign($data)) {
                    $this->redirect('index.php?route=campaigns/index');
                } else {
                    die('Something went wrong.');
                }
            }
        }

        $this->viewWithLayout('campaigns/edit', 'main', $data);
    }

    // Delete campaign
    public function delete($id) {
        $this->requirePermission('social_media', 'delete');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->campaignModel->deleteCampaign($id)) {
                $this->redirect('index.php?route=campaigns/index');
            } else {
                die('Something went wrong.');
            }
        } else {
            $this->redirect('index.php?route=campaigns/index');
        }
    }

    // Apply AI Budget Recommendation
    public function applyRecommendation() {
        $this->requirePermission('social_media', 'manage');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fromId = (int)$_POST['from_campaign_id'];
            $toId = (int)$_POST['to_campaign_id'];
            $amount = (float)$_POST['amount'];

            try {
                $db = Database::getInstance()->getConnection();
                $db->beginTransaction();

                // 1. Deduct budget from source campaign
                $stmt1 = $db->prepare('UPDATE campaigns SET budget = budget - :amount WHERE campaign_id = :id AND budget >= :amount');
                $stmt1->execute([':amount' => $amount, ':id' => $fromId]);

                if ($stmt1->rowCount() === 0) {
                    throw new Exception('Insufficient budget in source campaign.');
                }

                // 2. Add budget to target campaign
                $stmt2 = $db->prepare('UPDATE campaigns SET budget = budget + :amount WHERE campaign_id = :id');
                $stmt2->execute([':amount' => $amount, ':id' => $toId]);

                // 3. Log this action in audit trail
                $stmt3 = $db->prepare('INSERT INTO activity_logs (user_id, action) VALUES (:uid, :act)');
                $stmt3->execute([
                    ':uid' => $_SESSION['user_id'],
                    ':act' => 'Applied budget reallocation of $' . number_format($amount) . ' from campaign ID ' . $fromId . ' to campaign ID ' . $toId
                ]);

                $db->commit();
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Recommendation applied successfully!']);
                exit();

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
        }
    }
}
