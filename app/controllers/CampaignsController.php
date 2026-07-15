<?php
// Raptor CRM Campaigns Controller

class CampaignsController extends Controller {
    private $campaignModel;
    private $clientModel;

    public function __construct() {
        $this->requireAuth();
        
        // Enforce RBAC: Employer has no access to Campaigns list
        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }
        
        $this->campaignModel = $this->model('Campaign');
        $this->clientModel = $this->model('Client');
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

    // Add new campaign (Planned setup)
    public function add() {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            $this->redirect('index.php?route=campaigns/index');
        }

        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Create Campaign | Raptor CRM',
            'active_tab' => 'operations',
            'clients' => $clients,
            'client_id' => '',
            'name' => '',
            'channel' => '',
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

            $data['client_id'] = trim($_POST['client_id']);
            $data['name'] = trim($_POST['name']);
            $data['channel'] = trim($_POST['channel']);
            $data['budget'] = trim($_POST['budget']);
            $data['start_date'] = trim($_POST['start_date']);
            $data['end_date'] = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
            $data['status'] = trim($_POST['status']);

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

    // Edit campaign (Adjusting budget or entering actual metrics)
    public function edit($id) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            $this->redirect('index.php?route=campaigns/index');
        }

        $campaign = $this->campaignModel->getCampaignById($id);
        if (!$campaign) {
            $this->redirect('index.php?route=campaigns/index');
        }

        $clients = $this->clientModel->getClients();

        $data = [
            'title' => 'Edit Campaign | Raptor CRM',
            'active_tab' => 'operations',
            'clients' => $clients,
            'campaign_id' => $campaign->campaign_id,
            'client_id' => $campaign->client_id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
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

            $data['client_id'] = trim($_POST['client_id']);
            $data['name'] = trim($_POST['name']);
            $data['channel'] = trim($_POST['channel']);
            $data['budget'] = trim($_POST['budget']);
            $data['spend'] = trim($_POST['spend']);
            $data['revenue_influenced'] = trim($_POST['revenue_influenced']);
            $data['start_date'] = trim($_POST['start_date']);
            $data['end_date'] = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
            $data['status'] = trim($_POST['status']);

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
        if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            $this->redirect('index.php?route=campaigns/index');
        }

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
        if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }

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
