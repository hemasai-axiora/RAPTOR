<?php
// Raptor CRM Leads Controller

class LeadsController extends Controller {
    private $leadModel;
    private $clientModel;
    private $followUpModel;
    private $communicationModel;
    private $meetingModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('crm_leads', 'view');

        $this->leadModel = $this->model('Lead');
        $this->clientModel = $this->model('Client');
        $this->followUpModel = $this->model('FollowUp');
        $this->communicationModel = $this->model('Communication');
        $this->meetingModel = $this->model('Meeting');
    }

    public function index() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'lead_quality' => $_GET['lead_quality'] ?? '',
            'lead_source' => $_GET['lead_source'] ?? '',
            'assigned_to_user_id' => $_GET['assigned_to_user_id'] ?? '',
            'ageing' => $_GET['ageing'] ?? '',
        ];

        $data = [
            'title' => 'Leads Manager | Raptor CRM',
            'active_tab' => 'operations',
            'leads' => $this->leadModel->getLeads($filters, $this->visibleUserIds()),
            'filters' => $filters,
            'statuses' => Lead::STATUSES,
            'qualities' => Lead::QUALITIES,
            'sources' => $this->leadModel->getSources(),
            'assignees' => $this->getAssignees(),
        ];

        $this->viewWithLayout('leads/index', 'main', $data);
    }

    public function pipeline() {
        $data = [
            'title' => 'Lead Pipeline | Raptor CRM',
            'active_tab' => 'operations',
            'pipeline' => $this->leadModel->getPipeline($this->visibleUserIds()),
            'statuses' => Lead::STATUSES,
        ];

        $this->viewWithLayout('leads/pipeline', 'main', $data);
    }

    public function view($id = null, $data = []) {
        $lead = $this->leadModel->getLeadById((int) $id, $this->visibleUserIds());
        if (!$lead) {
            $this->redirect('index.php?route=leads/index');
        }

        $data = [
            'title' => 'Lead Detail | Raptor CRM',
            'active_tab' => 'operations',
            'lead' => $lead,
            'statuses' => Lead::STATUSES,
            'followup_channels' => FollowUp::CHANNELS,
            'communication_channels' => Communication::CHANNELS,
            'communication_directions' => Communication::DIRECTIONS,
            'meeting_types' => Meeting::TYPES,
            'assignees' => $this->getAssignees(),
            'status_history' => $this->leadModel->getStatusHistory((int) $id),
            'assignment_history' => $this->leadModel->getAssignmentHistory((int) $id),
            'communications' => $this->communicationModel->getForLead((int) $id),
            'meetings' => $this->meetingModel->getForLead((int) $id),
            'duplicates' => $this->leadModel->findDuplicates($lead->phone, $lead->email, (int) $id),
            'can_assign' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true),
        ];

        $this->viewWithLayout('leads/detail', 'main', $data);
    }

    public function add() {
        $this->requirePermission('crm_leads', 'create');
        $data = $this->formData([
            'title' => 'Capture Lead | Raptor CRM',
            'active_tab' => 'operations',
            'lead_id' => null,
            'lead_code' => 'Auto-generated (e.g. LD-2026-00001)',
            'client_id' => '',
            'owner_employee_id' => '',
            'assigned_to_user_id' => Policy::isEmployee() ? $_SESSION['user_id'] : '',
            'team_id' => '',
            'first_name' => '',
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'status' => 'new',
            'lead_quality' => 'warm',
            'probability' => '0.00',
            'lead_value' => '0.00',
            'lead_source' => '',
            'campaign_source' => '',
            'product_id' => '',
            'location' => '',
            'priority' => 'medium',
            'next_follow_up_at' => '',
            'lost_reason' => '',
            'converted_at' => '',
            'first_name_err' => '',
            'source_err' => '',
            'phone_err' => '',
            'email_err' => '',
            'duplicates' => [],
        ]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($data, $this->leadInput());
            $data['changed_by_user_id'] = $_SESSION['user_id'];
            $this->validateLeadData($data);
            $data['duplicates'] = $this->leadModel->findDuplicates($data['phone'], $data['email']);

            if (empty($data['first_name_err']) && empty($data['source_err']) && empty($data['phone_err']) && empty($data['email_err'])) {
                $leadId = $this->leadModel->addLead($data);
                if ($leadId) {
                    $this->audit('Created lead: ' . $data['first_name'], 'lead', (int) $leadId, null, $data);
                    $this->redirect('index.php?route=leads/view/' . $leadId);
                }
                die('Something went wrong.');
            }
        }

        $this->viewWithLayout('leads/add', 'main', $data);
    }

    public function edit($id) {
        $lead = $this->leadModel->getLeadById((int) $id, $this->visibleUserIds());
        if (!$lead) {
            $this->redirect('index.php?route=leads/index');
        }
        $this->requirePermission('crm_leads', 'edit', $lead);

        $data = $this->formData([
            'title' => 'Edit Lead | Raptor CRM',
            'active_tab' => 'operations',
            'lead_id' => $lead->lead_id,
            'lead_code' => $lead->lead_code ?: ('LD-' . date('Y') . '-' . sprintf('%05d', $lead->lead_id)),
            'client_id' => $lead->client_id,
            'owner_employee_id' => $lead->owner_employee_id,
            'assigned_to_user_id' => $lead->assigned_to_user_id,
            'team_id' => $lead->team_id,
            'first_name' => $lead->first_name,
            'company_name' => $lead->lead_company_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'status' => $lead->status,
            'lead_quality' => $lead->lead_quality,
            'probability' => $lead->probability ?? $lead->conversion_probability,
            'lead_value' => $lead->lead_value,
            'lead_source' => $lead->lead_source,
            'campaign_source' => $lead->campaign_source,
            'product_id' => $lead->product_id,
            'location' => $lead->location,
            'priority' => $lead->priority,
            'next_follow_up_at' => $lead->next_follow_up_at ? str_replace(' ', 'T', substr($lead->next_follow_up_at, 0, 16)) : '',
            'lost_reason' => $lead->lost_reason,
            'converted_at' => $lead->converted_at,
            'first_name_err' => '',
            'source_err' => '',
            'phone_err' => '',
            'email_err' => '',
            'duplicates' => $this->leadModel->findDuplicates($lead->phone, $lead->email, (int) $id),
        ]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $before = (array) $lead;
            $data = array_merge($data, $this->leadInput());
            $data['lead_id'] = (int) $id;
            $data['changed_by_user_id'] = $_SESSION['user_id'];
            $this->validateLeadData($data);
            $data['duplicates'] = $this->leadModel->findDuplicates($data['phone'], $data['email'], (int) $id);

            if (empty($data['first_name_err']) && empty($data['last_name_err']) && empty($data['source_err']) && empty($data['phone_err']) && empty($data['email_err'])) {
                if ($this->leadModel->updateLead($data)) {
                    if ($lead->status !== $data['status']) {
                        $this->autoScheduleAfterStageChange((int) $id, $data['status'], $data['assigned_to_user_id']);
                    }
                    $this->audit('Updated lead #' . (int) $id, 'lead', (int) $id, $before, $data);
                    $this->redirect('index.php?route=leads/view/' . (int) $id);
                }
                die('Something went wrong.');
            }
        }

        $this->viewWithLayout('leads/edit', 'main', $data);
    }

    public function moveStage($id = null) {
        $id = (int) ($id ?: ($_POST['id'] ?? $_POST['lead_id'] ?? 0));

        $isJsonRequest = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || isset($_POST['ajax']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid HTTP method. POST required.']);
                exit;
            }
            $this->redirect('index.php?route=leads/pipeline');
        }

        $lead = $this->leadModel->getLeadById($id, $this->visibleUserIds());
        if (!$lead) {
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Lead not found or permission denied.']);
                exit;
            }
            $this->redirect('index.php?route=leads/pipeline');
        }

        $this->requirePermission('crm_leads', 'edit', $lead);

        $targetStatus = trim($_POST['status'] ?? $_POST['target_status'] ?? '');
        $remarks = trim($_POST['remarks'] ?? $_POST['note'] ?? '');
        $customDate = !empty($_POST['changed_at']) ? trim($_POST['changed_at']) : null;

        if ($remarks === '') {
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Remarks/Notes are required for stage transition.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Remarks/Notes are required for stage transition.';
            $this->redirect('index.php?route=leads/pipeline');
        }

        $result = $this->leadModel->moveStatusWithRemarks($id, $targetStatus, $remarks, $_SESSION['user_id'], $this->visibleUserIds(), $customDate);

        if ($result['success']) {
            $this->autoScheduleAfterStageChange($id, $targetStatus, $lead->assigned_to_user_id);
            $this->audit('Moved lead #' . $id . ' from ' . $lead->status . ' to ' . $targetStatus . ' with remarks: ' . $remarks, 'lead', $id);

            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'lead_id' => $id,
                    'from_status' => $lead->status,
                    'to_status' => $targetStatus,
                    'pipeline' => $this->leadModel->getPipeline($this->visibleUserIds())
                ]);
                exit;
            }
            $_SESSION['flash_success'] = $result['message'];
        } else {
            if ($isJsonRequest) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $result['message']]);
                exit;
            }
            $_SESSION['flash_error'] = $result['message'];
        }

        $allowedReturns = ['leads/pipeline', 'leads/index', 'leads/view/' . $id];
        $return = $_POST['return'] ?? 'leads/pipeline';
        if (!in_array($return, $allowedReturns, true)) {
            $return = 'leads/pipeline';
        }
        $this->redirect('index.php?route=' . $return);
    }

    public function move($id = null) {
        return $this->moveStage($id);
    }

    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $lead = $this->leadModel->getLeadById((int) $id, $this->visibleUserIds());
            if ($lead) {
                $this->requirePermission('crm_leads', 'delete', $lead);
                if ($this->leadModel->deleteLead((int) $id, $this->visibleUserIds())) {
                    $this->audit('Deleted lead #' . (int) $id, 'lead', (int) $id);
                }
            }
        }
        $this->redirect('index.php?route=leads/index');
    }

    private function formData(array $data): array {
        return array_merge($data, [
            'clients' => $this->clientModel->getClients(),
            'assignees' => $this->getAssignees(),
            'employees' => $this->getEmployees(),
            'teams' => $this->getTeams(),
            'products' => $this->leadModel->getProducts(),
            'sources' => $this->leadModel->getSources(),
            'statuses' => Lead::STATUSES,
            'qualities' => Lead::QUALITIES,
            'priorities' => Lead::PRIORITIES,
        ]);
    }

    private function leadInput(): array {
        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $ownerEmpId = $_POST['owner_employee_id'] ?? '';
        $assigned = Policy::isEmployee()
            ? $_SESSION['user_id']
            : ($_POST['assigned_to_user_id'] ?? '');

        return [
            'lead_code' => strip_tags(trim($_POST['lead_code'] ?? '')),
            'client_id' => $_POST['client_id'] ?? '',
            'owner_employee_id' => $ownerEmpId,
            'assigned_to_user_id' => $assigned,
            'team_id' => $_POST['team_id'] ?? '',
            'first_name' => strip_tags(trim($_POST['first_name'] ?? '')),
            'last_name' => null,
            'company_name' => strip_tags(trim($_POST['company_name'] ?? '')),
            'email' => strip_tags(trim($_POST['email'] ?? '')),
            'phone' => strip_tags(trim($_POST['phone'] ?? '')),
            'status' => $_POST['status'] ?? 'new',
            'lead_quality' => $_POST['lead_quality'] ?? 'warm',
            'probability' => $_POST['probability'] ?? '0',
            'lead_value' => $_POST['lead_value'] ?? '0',
            'lead_source' => strip_tags(trim($_POST['lead_source'] ?? '')),
            'campaign_source' => strip_tags(trim($_POST['campaign_source'] ?? '')),
            'product_id' => $_POST['product_id'] ?? '',
            'location' => strip_tags(trim($_POST['location'] ?? '')),
            'priority' => $_POST['priority'] ?? 'medium',
            'next_follow_up_at' => $this->normalizeDatetime($_POST['next_follow_up_at'] ?? ''),
            'lost_reason' => strip_tags(trim($_POST['lost_reason'] ?? '')),
            'converted_at' => $this->normalizeDatetime($_POST['converted_at'] ?? ''),
        ];
    }

    private function validateLeadData(array &$data): void {
        if (empty($data['first_name'])) {
            $data['first_name_err'] = 'First name is required';
        } elseif (!preg_match("/^[A-Za-z\s'\-]{2,50}$/", $data['first_name'])) {
            $data['first_name_err'] = 'First name must be between 2 and 50 characters, containing only letters, spaces, hyphens, or apostrophes.';
        }
        
        if (empty($data['lead_source'])) {
            $data['source_err'] = 'Lead source is required';
        }
        
        if (isset($data['lead_value']) && (float)$data['lead_value'] <= 0) {
            $data['source_err'] = 'Expected Value must be greater than zero.';
        }

        if (!empty($data['email'])) {
            require_once APPROOT . '/core/Validation.php';
            if (!Validation::validateEmail($data['email'])) {
                $data['email_err'] = 'Please enter a valid email address with a domain extension (e.g. .com).';
            }
        }

        if (!empty($data['phone'])) {
            if (!preg_match("/^[0-9\s\+\-\(\)\.]{7,20}$/", $data['phone'])) {
                $data['phone_err'] = 'Phone number must contain only numbers and standard formatting symbols (+, -, parentheses, spaces, dots) and be between 7 and 20 characters.';
            }
        }

        if (!empty($data['company_name']) && !preg_match('/[a-zA-Z0-9]/', $data['company_name'])) {
            $data['first_name_err'] = 'Company name must contain alphanumeric characters.';
        }

        if (!empty($data['location']) && !preg_match('/[a-zA-Z0-9]/', $data['location'])) {
            $data['first_name_err'] = 'Location must contain alphanumeric characters.';
        }

        if (!empty($data['lost_reason']) && !preg_match('/[a-zA-Z0-9]/', $data['lost_reason'])) {
            $data['first_name_err'] = 'Lost reason must contain alphanumeric characters.';
        }

        if (!empty($data['campaign_source']) && !preg_match('/[a-zA-Z0-9]/', $data['campaign_source'])) {
            $data['first_name_err'] = 'Campaign source must contain alphanumeric characters.';
        }
        
        if (!in_array($data['status'], Lead::STATUSES, true)) {
            $data['status'] = 'new';
        }
        if (!in_array($data['lead_quality'], Lead::QUALITIES, true)) {
            $data['lead_quality'] = 'warm';
        }
        if (!in_array($data['priority'], Lead::PRIORITIES, true)) {
            $data['priority'] = 'medium';
        }
    }

    private function normalizeDatetime(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '');
    }

    private function getAssignees() {
        try {
            $db = Database::getInstance()->getConnection();
            $visible = $this->visibleUserIds();
            $params = [];
            $where = 'WHERE u.status = "active" AND r.role_name IN ("admin","manager","team_leader","employee","sales_person")';
            if ($visible !== null) {
                if (!$visible) {
                    return [];
                }
                $keys = [];
                foreach ($visible as $i => $id) {
                    $key = ':uid' . $i;
                    $keys[] = $key;
                    $params[$key] = (int) $id;
                }
                $where .= ' AND u.user_id IN (' . implode(',', $keys) . ')';
            }
            $stmt = $db->prepare('SELECT u.user_id, u.name, r.role_name
                                  FROM users u
                                  JOIN roles r ON u.role_id = r.role_id
                                  ' . $where . '
                                  ORDER BY u.name ASC');
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
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

    private function getTeams() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query('SELECT team_id, name FROM teams WHERE status = "active" ORDER BY name ASC');
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            return [];
        }
    }

    private function autoScheduleAfterStageChange(int $leadId, string $status, $assignedToUserId): void {
        if (empty($assignedToUserId) || !in_array($status, ['contacted', 'qualified', 'proposal'], true)) {
            return;
        }

        $labels = [
            'contacted' => 'Next touch after first contact',
            'qualified' => 'Qualification follow-up',
            'proposal' => 'Proposal follow-up',
        ];

        $dueAt = date('Y-m-d 10:00:00', strtotime('+1 day'));
        $followUpId = $this->followUpModel->createAutoForLead(
            $leadId,
            (int) $assignedToUserId,
            (int) $_SESSION['user_id'],
            $labels[$status],
            $dueAt
        );

        if ($followUpId) {
            $this->audit('Auto-created follow-up #' . (int) $followUpId . ' after lead stage change', 'follow_up', (int) $followUpId);
        }
    }

    public function downloadSampleCsv() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_bulk_import_sample.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['First Name', 'Company Name', 'Email Address', 'Phone Number', 'Lead Source', 'Estimated Value ($)', 'Notes']);
        fputcsv($output, ['John Doe', 'Acme Corp', 'john.doe@acme.local', '+1 555-019900', 'LinkedIn', '15000.00', 'Inquired about enterprise CRM features.']);
        fputcsv($output, ['Jane Smith', 'Apex Logistics', 'jane.smith@apex.local', '+1 555-019901', 'Website', '25000.00', 'Downloaded product catalog.']);
        fclose($output);
        exit();
    }

    public function uploadCsv() {
        $this->requirePermission('crm_leads', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                if ($handle !== false) {
                    $header = fgetcsv($handle, 1000, ',');
                    $importedCount = 0;

                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if (empty($row) || count($row) < 1 || empty(trim($row[0]))) {
                            continue;
                        }

                        $data = [
                            'first_name' => trim($row[0] ?? ''),
                            'company_name' => trim($row[1] ?? ''),
                            'email' => trim($row[2] ?? ''),
                            'phone' => trim($row[3] ?? ''),
                            'lead_source' => !empty(trim($row[4] ?? '')) ? trim($row[4]) : 'Website',
                            'lead_value' => is_numeric(trim($row[5] ?? '')) ? trim($row[5]) : '0.00',
                            'lead_notes' => trim($row[6] ?? ''),
                            'status' => 'new',
                            'lead_quality' => 'warm',
                            'assigned_to_user_id' => $_SESSION['user_id'] ?? null
                        ];

                        if (!empty($data['first_name'])) {
                            if ($this->leadModel->addLead($data)) {
                                $importedCount++;
                            }
                        }
                    }

                    fclose($handle);
                    $_SESSION['flash_success'] = "Successfully imported {$importedCount} leads via CSV.";
                } else {
                    $_SESSION['flash_error'] = "Failed to open uploaded CSV file.";
                }
            } else {
                $_SESSION['flash_error'] = "Please select a valid CSV file to upload.";
            }
        }

        $this->redirect('index.php?route=leads/index');
    }
}
