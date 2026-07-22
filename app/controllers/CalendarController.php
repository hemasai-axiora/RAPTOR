<?php
// Raptor CRM Calendar Controller

class CalendarController extends Controller {
    public function __construct() {
        $this->requireAuth();
        if ($_SESSION['user_role'] === 'employer') {
            $this->redirect('index.php?route=dashboard/executive');
        }
    }

    public function index() {
        $db = Database::getInstance()->getConnection();

        // 1. Fetch Calendar Events
        $visible = $this->visibleUserIds();
        $params = [];
        $where = '1=1';
        if ($visible !== null) {
            if (empty($visible)) {
                $where .= ' AND 1=0';
            } else {
                $keys = [];
                foreach ($visible as $i => $id) {
                    $key = ':uid' . $i;
                    $keys[] = $key;
                    $params[$key] = (int) $id;
                }
                $where .= ' AND user_id IN (' . implode(',', $keys) . ')';
            }
        }
        $stmt = $db->prepare("SELECT * FROM calendar_events WHERE $where ORDER BY start_date ASC");
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_OBJ);

        // 2. Fetch Meetings
        $mWhere = '1=1';
        if ($visible !== null) {
            if (empty($visible)) {
                $mWhere .= ' AND 1=0';
            } else {
                $mWhere .= ' AND assigned_to_user_id IN (' . implode(',', array_map('intval', $visible)) . ')';
            }
        }
        $mStmt = $db->prepare("SELECT meeting_id, title, scheduled_start, scheduled_end, location, type, status FROM meetings WHERE $mWhere");
        $mStmt->execute();
        $meetings = $mStmt->fetchAll(PDO::FETCH_OBJ);

        // 3. Fetch Tasks
        $tWhere = '1=1';
        if ($visible !== null) {
            if (empty($visible)) {
                $tWhere .= ' AND 1=0';
            } else {
                $tWhere .= ' AND assigned_to_user_id IN (' . implode(',', array_map('intval', $visible)) . ')';
            }
        }
        $tStmt = $db->prepare("SELECT task_id, title, start_date, deadline, priority, status FROM tasks WHERE $tWhere");
        $tStmt->execute();
        $tasks = $tStmt->fetchAll(PDO::FETCH_OBJ);

        // Format events into FullCalendar payload
        $calendarEvents = [];
        foreach ($events as $e) {
            $calendarEvents[] = [
                'id' => 'evt_' . $e->event_id,
                'title' => '[EVENT] ' . $e->title,
                'start' => $e->start_date,
                'end' => $e->end_date,
                'color' => '#8a2be2', // Purple
                'extendedProps' => [
                    'type' => 'event',
                    'details' => $e->description,
                    'db_id' => $e->event_id,
                    'can_delete' => ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'manager' || (int)$e->user_id === (int)$_SESSION['user_id'])
                ]
            ];
        }

        foreach ($meetings as $m) {
            $calendarEvents[] = [
                'id' => 'mtg_' . $m->meeting_id,
                'title' => '[' . strtoupper($m->type) . '] ' . $m->title,
                'start' => $m->scheduled_start,
                'end' => $m->scheduled_end ?: $m->scheduled_start,
                'color' => $m->status === 'completed' ? '#2ec4b6' : '#ff9f1c', // Teal or Orange
                'extendedProps' => [
                    'type' => 'meeting',
                    'details' => 'Location: ' . ($m->location ?: 'N/A') . ' | Status: ' . str_replace('_', ' ', $m->status),
                    'can_delete' => false
                ]
            ];
        }

        foreach ($tasks as $t) {
            $calendarEvents[] = [
                'id' => 'tsk_' . $t->task_id,
                'title' => '[TASK] ' . $t->title,
                'start' => $t->start_date ?: date('Y-m-d H:i:s'),
                'end' => $t->deadline,
                'color' => $t->status === 'completed' ? '#2ec4b6' : '#e63946', // Teal or Red
                'extendedProps' => [
                    'type' => 'task',
                    'details' => 'Priority: ' . strtoupper($t->priority) . ' | Status: ' . strtoupper($t->status),
                    'can_delete' => false
                ]
            ];
        }

        // Get list of clients for scheduling modal
        $cStmt = $db->query("SELECT client_id, company_name FROM clients ORDER BY company_name ASC");
        $clients = $cStmt->fetchAll(PDO::FETCH_OBJ);

        $data = [
            'title' => 'Events Calendar | Raptor CRM',
            'active_tab' => 'calendar',
            'events_json' => json_encode($calendarEvents),
            'clients' => $clients,
            'is_admin_or_manager' => in_array($_SESSION['user_role'], ['admin', 'manager'], true)
        ];

        $this->viewWithLayout('calendar/index', 'main', $data);
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php?route=calendar/index');
        }

        $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $clientId = $_POST['client_id'] ?? null;
        if ($clientId === '') {
            $clientId = null;
        }
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';

        if ($title !== '' && $startDate !== '' && $endDate !== '') {
            if (!preg_match('/[a-zA-Z0-9]/', $title)) {
                $_SESSION['calendar_error'] = 'Event Title must contain alphanumeric characters.';
                $this->redirect('index.php?route=calendar/index');
                return;
            }
            if ($description !== '' && !preg_match('/[a-zA-Z0-9]/', $description)) {
                $_SESSION['calendar_error'] = 'Event Description must contain alphanumeric characters if provided.';
                $this->redirect('index.php?route=calendar/index');
                return;
            }

            $startTs = strtotime($startDate);
            $endTs = strtotime($endDate);

            if (!$startTs || !$endTs) {
                $_SESSION['calendar_error'] = 'Invalid start or end date/time format.';
                $this->redirect('index.php?route=calendar/index');
                return;
            }

            if ($startTs < strtotime('today')) {
                $_SESSION['calendar_error'] = 'Start date/time cannot be in the past.';
                $this->redirect('index.php?route=calendar/index');
                return;
            }

            if ($endTs < $startTs) {
                $_SESSION['calendar_error'] = 'End date/time cannot be earlier than start date/time.';
                $this->redirect('index.php?route=calendar/index');
                return;
            }

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO calendar_events (client_id, user_id, title, description, start_date, end_date) VALUES (:client_id, :user_id, :title, :description, :start_date, :end_date)");
            $stmt->execute([
                ':client_id' => $clientId,
                ':user_id' => (int) $_SESSION['user_id'],
                ':title' => $title,
                ':description' => $description,
                ':start_date' => str_replace('T', ' ', $startDate),
                ':end_date' => str_replace('T', ' ', $endDate),
            ]);
            $this->audit('Created calendar event: ' . $title, 'calendar_event');
            $_SESSION['calendar_success'] = 'Event scheduled successfully.';
        } else {
            $_SESSION['calendar_error'] = 'All fields marked with * are required.';
        }

        $this->redirect('index.php?route=calendar/index');
    }

    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("SELECT user_id, title FROM calendar_events WHERE event_id = :id");
            $stmt->execute([':id' => (int) $id]);
            $event = $stmt->fetch(PDO::FETCH_OBJ);
            
            if ($event) {
                $canDelete = ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'manager' || (int)$event->user_id === (int)$_SESSION['user_id']);
                if ($canDelete) {
                    $delStmt = $db->prepare("DELETE FROM calendar_events WHERE event_id = :id");
                    $delStmt->execute([':id' => (int) $id]);
                    $this->audit('Deleted calendar event: ' . $event->title, 'calendar_event');
                }
            }
        }
        $this->redirect('index.php?route=calendar/index');
    }
}
