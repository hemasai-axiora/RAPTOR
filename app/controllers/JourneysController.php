<?php
/**
 * JourneysController — Marketing Automation & Visual Workflows
 * -------------------------------------------------------------------------
 * Manages:
 * 1. Event-driven journeys (form fills, email clicks, WhatsApp replies)
 * 2. Multi-step action builder (email, WhatsApp, delay, score adjustment, stage)
 * 3. Behavioral lead scoring rules matrix
 * 4. Enrollment monitoring and real-time step analytics
 */

class JourneysController extends Controller {

    private $journeyModel;
    private $runnerModel;
    private $scoringModel;

    public function __construct() {
        $this->requireAuth();
        $this->journeyModel = $this->model('MarketingJourney');
        $this->runnerModel = $this->model('JourneyRunner');
        $this->scoringModel = $this->model('LeadScoringRule');
    }

    public function index() {
        $journeys = $this->journeyModel->getAll();
        $scoringRules = $this->scoringModel->getAll();

        $data = [
            'title' => 'Automation Journeys | Raptor Marketing',
            'active_tab' => 'marketing',
            'journeys' => $journeys,
            'scoring_rules' => $scoringRules,
            'triggers' => MarketingJourney::TRIGGERS,
            'action_types' => MarketingJourney::ACTION_TYPES
        ];

        $this->viewWithLayout('journeys/index', 'main', $data);
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $triggerType = trim($_POST['trigger_type'] ?? '');
            $desc = trim($_POST['description'] ?? '');

            if (empty($name) || empty($triggerType)) {
                $_SESSION['flash_error'] = "Journey Name and Inbound Trigger are required.";
                $this->redirect('index.php?route=journeys/index');
                return;
            }

            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $journeyId = $this->journeyModel->create([
                'name' => $name,
                'trigger_type' => $triggerType,
                'description' => $desc,
                'status' => 'draft',
                'created_by' => $currentUserId
            ]);

            if ($journeyId > 0) {
                $_SESSION['flash_success'] = "Journey initialized! Add your automation sequence steps.";
                $this->redirect('index.php?route=journeys/builder&id=' . $journeyId);
                return;
            } else {
                $_SESSION['flash_error'] = "Failed to create journey.";
            }
        }
        $this->redirect('index.php?route=journeys/index');
    }

    public function builder() {
        $journeyId = (int)($_GET['id'] ?? 0);
        $journey = $this->journeyModel->getById($journeyId);

        if (!$journey) {
            $_SESSION['flash_error'] = "Journey not found.";
            $this->redirect('index.php?route=journeys/index');
            return;
        }

        $steps = $this->journeyModel->getSteps($journeyId);

        $data = [
            'title' => 'Journey Builder: ' . htmlspecialchars($journey->name) . ' | Raptor Marketing',
            'active_tab' => 'marketing',
            'journey' => $journey,
            'steps' => $steps,
            'triggers' => MarketingJourney::TRIGGERS,
            'action_types' => MarketingJourney::ACTION_TYPES
        ];

        $this->viewWithLayout('journeys/builder', 'main', $data);
    }

    public function saveSteps() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $journeyId = (int)($_POST['journey_id'] ?? 0);
            $journey = $this->journeyModel->getById($journeyId);

            if (!$journey) {
                $_SESSION['flash_error'] = "Journey not found.";
                $this->redirect('index.php?route=journeys/index');
                return;
            }

            $stepsData = $_POST['steps'] ?? [];
            $parsedSteps = [];

            if (is_array($stepsData)) {
                foreach ($stepsData as $s) {
                    if (empty($s['action_type'])) continue;
                    $stepType = ($s['action_type'] === 'wait_delay') ? 'delay' : 'action';
                    $parsedSteps[] = [
                        'step_type' => $stepType,
                        'action_type' => $s['action_type'],
                        'action_config' => !empty($s['config']) ? $s['config'] : []
                    ];
                }
            }

            $this->journeyModel->setSteps($journeyId, $parsedSteps);
            $_SESSION['flash_success'] = "Automation journey steps updated successfully (" . count($parsedSteps) . " steps).";
            $this->redirect('index.php?route=journeys/builder&id=' . $journeyId);
            return;
        }
        $this->redirect('index.php?route=journeys/index');
    }

    public function toggleStatus() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $journeyId = (int)($_POST['journey_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            if ($journeyId > 0 && in_array($status, MarketingJourney::STATUSES, true)) {
                $this->journeyModel->updateStatus($journeyId, $status);
                $_SESSION['flash_success'] = "Journey status updated to [" . strtoupper($status) . "].";
            }
        }
        $this->redirect('index.php?route=journeys/index');
    }

    public function scoring() {
        $rules = $this->scoringModel->getAll();

        $data = [
            'title' => 'Behavioral Lead Scoring Matrix | Raptor Marketing',
            'active_tab' => 'marketing',
            'rules' => $rules,
            'standard_events' => LeadScoringRule::STANDARD_EVENTS
        ];

        $this->viewWithLayout('journeys/scoring', 'main', $data);
    }

    public function saveScoringRule() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['rule_name'] ?? '');
            $eventType = trim($_POST['event_type'] ?? '');
            $delta = (int)($_POST['score_delta'] ?? 5);
            $maxDaily = (int)($_POST['max_daily_occurrences'] ?? 5);

            if (!empty($name) && !empty($eventType)) {
                $this->scoringModel->saveRule($name, $eventType, $delta, $maxDaily);
                $_SESSION['flash_success'] = "Lead scoring rule saved.";
            } else {
                $_SESSION['flash_error'] = "Rule name and event type are required.";
            }
        }
        $this->redirect('index.php?route=journeys/scoring');
    }
}

