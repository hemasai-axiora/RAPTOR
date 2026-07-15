<?php
// Manager edit-request workflow and admin approval queue.

class EditrequestsController extends Controller {
    private $requestModel;

    public function __construct() {
        $this->requireAuth();
        $this->requestModel = $this->model('DataEditRequest');
    }

    public function index() {
        if (!Policy::canApproveDataEdit() && !Policy::canRequestDataEdit()) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'You do not have access to edit requests.'
            ]);
            return;
        }

        $status = $_GET['status'] ?? 'pending';
        $requests = Policy::canApproveDataEdit()
            ? $this->requestModel->all(in_array($status, ['pending', 'approved', 'rejected', 'all'], true) ? $status : 'pending')
            : $this->requestModel->pendingForManager((int) $_SESSION['user_id']);

        $this->viewWithLayout('editrequests/index', 'main', [
            'title' => 'Data Edit Requests | Raptor CRM',
            'active_tab' => 'edit_requests',
            'requests' => $requests,
            'status' => $status,
            'entity_types' => $this->requestModel->entityTypes(),
        ]);
    }

    public function create() {
        if (!Policy::canRequestDataEdit()) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'Only managers can submit governed data edit requests.'
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $comment = trim($_POST['manager_comment'] ?? '');
            $entityId = (int) ($_POST['entity_id'] ?? 0);
            $rawChanges = htmlspecialchars_decode($_POST['proposed_changes'] ?? '', ENT_QUOTES);

            // Validate JSON
            if (!Validation::validateJson($rawChanges)) {
                $_SESSION['edit_request_error'] = 'Please enter valid JSON data.';
                $this->redirect('index.php?route=editrequests/index');
                return;
            }

            // Validate Comment
            if (!Validation::validateManagerComment($comment)) {
                $_SESSION['edit_request_error'] = 'Please enter a meaningful comment using at least 3 characters.';
                $this->redirect('index.php?route=editrequests/index');
                return;
            }

            $changes = $this->requestModel->parseChangesText($rawChanges);

            if ($entityId > 0 && $comment !== '') {
                $this->requestModel->create([
                    'entity_type' => $_POST['entity_type'] ?? '',
                    'entity_id' => $entityId,
                    'requested_action' => $_POST['requested_action'] ?? 'update',
                    'proposed_changes' => $changes,
                    'manager_comment' => $comment,
                    'requested_by_user_id' => (int) $_SESSION['user_id'],
                ]);
                $this->audit('Created data edit request', 'data_edit_requests');
                $_SESSION['edit_request_success'] = 'Data edit request submitted successfully.';
            } else {
                $_SESSION['edit_request_error'] = 'All fields are required.';
            }
        }

        $this->redirect('index.php?route=editrequests/index');
    }

    public function approve($id = 0) {
        $this->review((int) $id, true);
    }

    public function reject($id = 0) {
        $this->review((int) $id, false);
    }

    private function review(int $id, bool $approve): void {
        if (!Policy::canApproveDataEdit()) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'Only admins can approve or reject data edit requests.'
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
            $comment = trim($_POST['reviewed_comment'] ?? '');
            $ok = $approve
                ? $this->requestModel->approve($id, (int) $_SESSION['user_id'], $comment)
                : $this->requestModel->reject($id, (int) $_SESSION['user_id'], $comment);

            if ($ok) {
                $this->audit(($approve ? 'Approved' : 'Rejected') . ' data edit request', 'data_edit_requests', $id);
            }
        }

        $this->redirect('index.php?route=editrequests/index');
    }
}
