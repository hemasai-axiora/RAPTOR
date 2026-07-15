<?php
// Raptor CRM Performance Controller

class PerformanceController extends Controller {
    private $performanceModel;

    public function __construct() {
        $this->requireAuth();

        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }

        $this->performanceModel = $this->model('Performance');
    }
    public function index() {
        $period = $_GET['period'] ?? 'weekly';
        if (!in_array($period, ['weekly', 'monthly'], true)) {
            $period = 'weekly';
        }
        [$start, $end] = $this->performanceModel->periodRange($period, $_GET['start'] ?? null, $_GET['end'] ?? null);

        $data = [
            'title' => 'Performance | Raptor CRM',
            'active_tab' => 'performance',
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'scores' => $this->performanceModel->getScores($period, $start, $end, $this->visibleUserIds()),
            'weights' => $this->performanceModel->getWeights(),
            'can_manage' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true),
            'is_admin' => $_SESSION['user_role'] === 'admin',
        ];

        $this->viewWithLayout('performance/index', 'main', $data);
    }

    public function profile($userId = 0) {
        $userId = (int) ($userId ?: $_SESSION['user_id']);
        $scope = $this->visibleUserIds();
        if ($scope !== null && !in_array($userId, $scope, true)) {
            $this->viewWithLayout('errors/403', 'main', [
                'title' => 'Access Denied',
                'message' => 'This performance profile is outside your team scope.'
            ]);
            return;
        }

        $period = $_GET['period'] ?? 'weekly';
        if (!in_array($period, ['weekly', 'monthly'], true)) {
            $period = 'weekly';
        }
        $score = $this->performanceModel->getLatestForUser($userId, $period);
        $reviews = $score
            ? $this->performanceModel->getReviews($userId, $score->period, $score->start_date, $score->end_date)
            : [];

        $data = [
            'title' => 'Performance Profile | Raptor CRM',
            'active_tab' => 'performance',
            'score' => $score,
            'reviews' => $reviews,
            'user_id' => $userId,
            'period' => $period,
            'can_review' => in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true) && $userId !== (int) $_SESSION['user_id'],
        ];

        $this->viewWithLayout('performance/profile', 'main', $data);
    }

    public function recompute() {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=performance/index');
        }
        $period = in_array($_POST['period'] ?? 'weekly', ['weekly', 'monthly'], true) ? $_POST['period'] : 'weekly';
        $count = $this->performanceModel->recompute($period, $_POST['start'] ?? null, $_POST['end'] ?? null);
        $this->audit('Recomputed ' . $count . ' performance scores', 'performance');
        $this->redirect('index.php?route=performance/index&period=' . $period);
    }

    public function weights() {
        if ($_SESSION['user_role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=performance/index');
        }
        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $this->performanceModel->saveWeights($_POST['weights'] ?? []);
        $this->audit('Updated scoring weights', 'scoring_weights');
        $this->redirect('index.php?route=performance/index');
    }

    public function review($userId) {
        if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'team_leader'], true) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=performance/index');
        }
        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $period = $_POST['period'] ?? 'weekly';
        $start = $_POST['start_date'] ?? date('Y-m-d');
        $end = $_POST['end_date'] ?? date('Y-m-d');
        $this->performanceModel->addReview([
            'user_id' => (int) $userId,
            'reviewer_user_id' => (int) $_SESSION['user_id'],
            'period' => $period,
            'start_date' => $start,
            'end_date' => $end,
            'rating' => $_POST['rating'] ?? '',
            'remarks' => trim($_POST['remarks'] ?? ''),
        ]);
        $this->audit('Added manager review for user #' . (int) $userId, 'manager_review', (int) $userId);
        $this->redirect('index.php?route=performance/profile/' . (int) $userId . '&period=' . urlencode($period));
    }
}
