<?php
// Raptor CRM Targets Controller

class TargetsController extends Controller {
    private $targetModel;

    public function __construct() {
        $this->requireAuth();

        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->targetModel = $this->model('Target');
    }

    public function index() {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'period' => $_GET['period'] ?? '',
            'owner_type' => $_GET['owner_type'] ?? '',
        ];

        $data = [
            'title' => 'Targets | Raptor CRM',
            'active_tab' => 'targets',
            'targets' => $this->targetModel->getTargets($this->visibleUserIds(), $filters),
            'filters' => $filters,
            'periods' => Target::PERIODS,
            'statuses' => Target::STATUSES,
            'categories' => $this->targetModel->getCategories(),
            'users' => $this->targetModel->getUsers($this->visibleUserIds()),
            'teams' => $this->targetModel->getTeams($this->visibleUserIds()),
            'products' => $this->targetModel->getProducts(),
            'territories' => $this->targetModel->getTerritories(),
            'can_approve' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true),
        ];

        $this->viewWithLayout('targets/index', 'main', $data);
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=targets/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $ownerType = ($_POST['owner_type'] ?? 'employee') === 'team' ? 'team' : 'employee';
        $data = [
            'owner_type' => $ownerType,
            'owner_user_id' => $_POST['owner_user_id'] ?? $_SESSION['user_id'],
            'team_id' => $_POST['team_id'] ?? null,
            'period' => $_POST['period'] ?? 'monthly',
            'start_date' => $this->safeDate($_POST['start_date'] ?? ''),
            'end_date' => $this->safeDate($_POST['end_date'] ?? ''),
            'status' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true) ? 'approved' : 'pending_approval',
            'created_by_user_id' => $_SESSION['user_id'],
        ];

        if (!$data['start_date'] || !$data['end_date']) {
            $this->redirect('index.php?route=targets/index');
        }
        if (!$this->ownerIsVisible($ownerType, (int) $data['owner_user_id'], (int) $data['team_id'])) {
            $this->redirect('index.php?route=targets/index');
        }

        $items = [];
        $categoryIds = $_POST['category_id'] ?? [];
        $plannedValues = $_POST['planned_value'] ?? [];
        $productIds = $_POST['product_id'] ?? [];
        $territoryIds = $_POST['territory_id'] ?? [];
        foreach ($categoryIds as $i => $categoryId) {
            $items[] = [
                'category_id' => $categoryId,
                'planned_value' => $plannedValues[$i] ?? 0,
                'product_id' => $productIds[$i] ?? null,
                'territory_id' => $territoryIds[$i] ?? null,
            ];
        }

        $targetId = $this->targetModel->createTarget($data, $items);
        if ($targetId) {
            $this->audit('Created target plan #' . (int) $targetId, 'target', (int) $targetId, null, $data);
        }

        $this->redirect('index.php?route=targets/index');
    }

    public function review($id) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true)) {
            $this->redirect('index.php?route=targets/index');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $decision = $_POST['decision'] ?? 'rejected';
            $remark = trim($_POST['approval_remark'] ?? '');
            if ($this->targetModel->review((int) $id, $decision, (int) $_SESSION['user_id'], $remark, $this->visibleUserIds())) {
                $this->audit('Reviewed target #' . (int) $id . ' as ' . $decision, 'target', (int) $id);
            }
        }
        $this->redirect('index.php?route=targets/index');
    }

    public function recompute($id = 0) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true)) {
            $this->redirect('index.php?route=targets/index');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=targets/index');
        }
        if ((int) $id > 0) {
            $target = $this->targetModel->getTargetById((int) $id, $this->visibleUserIds());
            if ($target) {
                $this->targetModel->recomputeTarget((int) $id);
                $this->audit('Recomputed target #' . (int) $id, 'target', (int) $id);
            }
        } else {
            $this->targetModel->recomputeAll();
            $this->audit('Recomputed all approved targets', 'target');
        }
        $this->redirect('index.php?route=targets/index');
    }

    public function items($id) {
        $target = $this->targetModel->getTargetById((int) $id, $this->visibleUserIds());
        if (!$target) {
            $this->redirect('index.php?route=targets/index');
        }
        $data = [
            'title' => 'Target Detail | Raptor CRM',
            'active_tab' => 'targets',
            'target' => $target,
            'items' => $this->targetModel->getItems((int) $id),
        ];
        $this->viewWithLayout('targets/items', 'main', $data);
    }

    private function safeDate(string $value): ?string {
        $value = substr(trim($value), 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function ownerIsVisible(string $ownerType, int $userId, int $teamId): bool {
        $visible = $this->visibleUserIds();
        if ($visible === null) {
            return true;
        }
        if ($ownerType === 'employee') {
            return in_array($userId, $visible, true);
        }
        foreach ($this->targetModel->getTeams($visible) as $team) {
            if ((int) $team->team_id === $teamId) {
                return true;
            }
        }
        return false;
    }
}
