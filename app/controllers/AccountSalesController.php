<?php
// AccountSalesController - Manages Post-Conversion Inside Sales & Account Growth

class AccountSalesController extends Controller {

    private $activityModel;
    private $opportunityModel;
    private $customerModel;

    public function __construct() {
        $this->requireAuth();
        $this->activityModel = $this->model('AccountSalesActivity');
        $this->opportunityModel = $this->model('AccountOpportunity');
        $this->customerModel = $this->model('Customer');
    }

    public function index() {
        $activities = $this->activityModel->getRecentActivities();
        $churnRisks = $this->opportunityModel->getChurnRiskAccounts();
        $customers = $this->customerModel->getCustomers();
        $employees = $this->getEmployees();

        $data = [
            'title' => 'Account Sales Dashboard | Raptor CRM',
            'active_tab' => 'sales',
            'activities' => $activities,
            'churn_risks' => $churnRisks,
            'customers' => $customers,
            'employees' => $employees
        ];

        $this->viewWithLayout('account_sales/index', 'main', $data);
    }

    public function opportunities() {
        $repId = !empty($_GET['rep_id']) ? (int)$_GET['rep_id'] : null;
        $oppType = !empty($_GET['opp_type']) ? $_GET['opp_type'] : null;

        $filters = [];
        if ($repId) $filters['assigned_rep_employee_id'] = $repId;
        if ($oppType) $filters['opportunity_type'] = $oppType;

        $pipeline = $this->opportunityModel->getOpportunitiesByStage($filters);
        $customers = $this->customerModel->getCustomers();
        $employees = $this->getEmployees();

        $data = [
            'title' => 'Account Growth Pipeline | Raptor CRM',
            'active_tab' => 'sales',
            'pipeline' => $pipeline,
            'customers' => $customers,
            'employees' => $employees,
            'rep_id' => $repId,
            'opp_type' => $oppType
        ];

        $this->viewWithLayout('account_sales/opportunities', 'main', $data);
    }

    public function addOpportunity() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_DEFAULT);

            $data = [
                'customer_id' => (int)($_POST['customer_id'] ?? 0),
                'assigned_rep_employee_id' => !empty($_POST['assigned_rep_employee_id']) ? (int)$_POST['assigned_rep_employee_id'] : null,
                'title' => trim($_POST['title'] ?? ''),
                'opportunity_type' => trim($_POST['opportunity_type'] ?? 'Upsell'),
                'stage' => trim($_POST['stage'] ?? 'Identified'),
                'expected_value' => (float)($_POST['expected_value'] ?? 0),
                'probability' => (int)($_POST['probability'] ?? 50),
                'target_close_date' => trim($_POST['target_close_date'] ?? ''),
                'notes' => trim($_POST['notes'] ?? '')
            ];

            if ($data['customer_id'] > 0 && !empty($data['title'])) {
                $id = $this->opportunityModel->createOpportunity($data);
                if ($id) {
                    $_SESSION['flash_success'] = "Account growth opportunity created successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to create opportunity.";
                }
            } else {
                $_SESSION['flash_error'] = "Customer and Opportunity Title are required.";
            }
        }

        $this->redirect('index.php?route=account_sales/opportunities');
    }

    public function moveStage() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $oppId = (int)($_POST['id'] ?? 0);
            $stage = trim($_POST['stage'] ?? '');

            if ($oppId > 0 && !empty($stage)) {
                $success = $this->opportunityModel->updateStage($oppId, $stage);

                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => $success,
                        'message' => $success ? "Opportunity stage updated to {$stage}." : "Failed to update stage."
                    ]);
                    exit();
                }

                if ($success) {
                    $_SESSION['flash_success'] = "Opportunity stage updated to {$stage}.";
                }
            }
        }

        $this->redirect('index.php?route=account_sales/opportunities');
    }

    public function logActivity() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_DEFAULT);

            $data = [
                'customer_id' => (int)($_POST['customer_id'] ?? 0),
                'assigned_rep_employee_id' => !empty($_POST['assigned_rep_employee_id']) ? (int)$_POST['assigned_rep_employee_id'] : null,
                'activity_type' => trim($_POST['activity_type'] ?? 'Call'),
                'outcome' => trim($_POST['outcome'] ?? 'Successful'),
                'next_follow_up_date' => trim($_POST['next_follow_up_date'] ?? ''),
                'notes' => trim($_POST['notes'] ?? '')
            ];

            if ($data['customer_id'] > 0) {
                $id = $this->activityModel->logActivity($data);
                if ($id) {
                    $_SESSION['flash_success'] = "Sales activity logged successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to log activity.";
                }
            } else {
                $_SESSION['flash_error'] = "Customer selection is required.";
            }
        }

        $this->redirect('index.php?route=account_sales/index');
    }

    public function assignAccount() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $customerId = (int)($_POST['customer_id'] ?? 0);
            $ownerEmployeeId = !empty($_POST['owner_employee_id']) ? (int)$_POST['owner_employee_id'] : null;

            if ($customerId > 0) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE customers SET owner_employee_id = :owner_id WHERE customer_id = :id");
                $success = $stmt->execute([':owner_id' => $ownerEmployeeId, ':id' => $customerId]);

                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => $success, 'message' => $success ? 'Account Manager assigned successfully.' : 'Failed to update Account Manager.']);
                    exit();
                }

                if ($success) {
                    $_SESSION['flash_success'] = "Account Manager assigned successfully.";
                } else {
                    $_SESSION['flash_error'] = "Failed to assign Account Manager.";
                }
            }
        }
        $this->redirect('index.php?route=account_sales/index');
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
