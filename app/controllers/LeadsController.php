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
            'client_id' => '',
            'assigned_to_user_id' => Policy::isEmployee() ? $_SESSION['user_id'] : '',
            'team_id' => '',
            'first_name' => '',
            'last_name' => '',
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
            'last_name_err' => '',
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

            if (empty($data['first_name_err']) && empty($data['last_name_err']) && empty($data['source_err']) && empty($data['phone_err']) && empty($data['email_err'])) {
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
            'client_id' => $lead->client_id,
            'assigned_to_user_id' => $lead->assigned_to_user_id,
            'team_id' => $lead->team_id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
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
            'last_name_err' => '',
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

    public function move($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $status = $_POST['status'] ?? '';
            $lead = $this->leadModel->getLeadById((int) $id, $this->visibleUserIds());
            if ($lead) {
                $this->requirePermission('crm_leads', 'edit', $lead);
                if ($this->leadModel->moveStatus((int) $id, $status, $_SESSION['user_id'], $this->visibleUserIds())) {
                    if ($lead->status !== $status) {
                        $this->autoScheduleAfterStageChange((int) $id, $status, $lead->assigned_to_user_id);
                    }
                    $this->audit('Moved lead #' . (int) $id . ' to ' . $status, 'lead', (int) $id);
                }
            }
        }
        $allowedReturns = ['leads/pipeline', 'leads/index', 'leads/view/' . (int) $id];
        $return = $_POST['return'] ?? ('leads/view/' . (int) $id);
        if (!in_array($return, $allowedReturns, true)) {
            $return = 'leads/view/' . (int) $id;
        }
        $this->redirect('index.php?route=' . $return);
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
        $assigned = Policy::isEmployee()
            ? $_SESSION['user_id']
            : ($_POST['assigned_to_user_id'] ?? '');

        return [
            'client_id' => $_POST['client_id'] ?? '',
            'assigned_to_user_id' => $assigned,
            'team_id' => $_POST['team_id'] ?? '',
            'first_name' => strip_tags(trim($_POST['first_name'] ?? '')),
            'last_name' => strip_tags(trim($_POST['last_name'] ?? '')),
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
        
        if (!empty($data['last_name']) && !preg_match("/^[A-Za-z\s'\-]{2,50}$/", $data['last_name'])) {
            $data['last_name_err'] = 'Last name must contain only letters, spaces, hyphens, or apostrophes, and be between 2 and 50 characters.';
        }
        
        if (empty($data['lead_source'])) {
            $data['source_err'] = 'Lead source is required';
        }
        
        if (isset($data['lead_value']) && (float)$data['lead_value'] <= 0) {
            $data['source_err'] = 'Expected Value must be greater than zero.';
        }

        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $data['email_err'] = 'Please enter a valid email address.';
            }
        }

        if (!empty($data['phone'])) {
            if (!preg_match("/^[0-9\s\+\-\(\)\.]{7,20}$/", $data['phone'])) {
                $data['phone_err'] = 'Phone number must contain only numbers and standard formatting symbols (+, -, parentheses, spaces, dots) and be between 7 and 20 characters.';
            }
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
}
