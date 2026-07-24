<?php
/**
 * Raptor CRM — Organization Management (Sprint 1)
 * Teams, branches, territories, and member assignment. Admin + Manager only.
 */

class TeamsController extends Controller {
    private $teamModel;

    public function __construct() {
        $this->requireAuth();
        $role = $_SESSION['user_role'] ?? '';
        if ($role !== 'admin' && $role !== 'manager') {
            $this->redirect('index.php?route=dashboard/index');
            return;
        }
        $this->teamModel = $this->model('Team');
    }

    public function index() {
        $mgrId = $_SESSION['user_role'] === 'manager' ? (int) $_SESSION['user_id'] : null;
        
        // Assemble parent-child hierarchy tree
        $users = $this->teamModel->getOrgHierarchy();
        $tree = [];
        $nodes = [];
        foreach ($users as $u) {
            $u->children = [];
            $nodes[$u->user_id] = $u;
        }
        foreach ($users as $u) {
            $parentId = $u->reporting_manager_id;
            if ($parentId && isset($nodes[$parentId])) {
                $nodes[$parentId]->children[] = $u;
            } else {
                $tree[] = $u;
            }
        }

        $data = [
            'title'       => 'Organization | Raptor CRM',
            'active_tab'  => 'system',
            'teams'       => $this->teamModel->getTeams(),
            'branches'    => $this->teamModel->getBranches(),
            'territories' => $this->teamModel->getTerritories(),
            'leaders'     => $this->teamModel->getLeadershipUsers(),
            'salespersons'=> $this->teamModel->getSalesPersons(),
            'geofences'   => $this->teamModel->getGeofences(),
            'geofence_enabled' => $this->teamModel->isGeofenceEnabled(),
            'team_members' => $this->teamModel->getTeamMembers($mgrId),
            'hierarchy_tree' => $tree,
        ];
        $this->viewWithLayout('teams/index', 'main', $data);
    }

    // ---------------- Teams ----------------

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $name = trim($_POST['name'] ?? '');
            if (!Validation::validateNameWithSpaces($name)) {
                $_SESSION['team_error'] = 'Please enter a valid team name (alphabetic and spaces only, min 3 characters).';
                $this->redirect('index.php?route=teams/index');
                return;
            }
            $this->teamModel->addTeam($_POST);
            $this->audit('Created team: ' . $_POST['name'], 'team');
            $_SESSION['team_success'] = 'Team created successfully.';
        }
        $this->redirect('index.php?route=teams/index');
    }

    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $name = trim($_POST['name'] ?? '');
            if (!Validation::validateNameWithSpaces($name)) {
                $_SESSION['team_error'] = 'Please enter a valid team name (alphabetic and spaces only, min 3 characters).';
                $this->redirect('index.php?route=teams/index');
                return;
            }
            if (!empty($_POST['team_id'])) {
                $this->teamModel->updateTeam($_POST);
                $this->audit('Updated team #' . $_POST['team_id'], 'team', (int) $_POST['team_id']);
                $_SESSION['team_success'] = 'Team updated successfully.';
            }
        }
        $this->redirect('index.php?route=teams/index');
    }

    public function delete($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->teamModel->deleteTeam($id);
            $this->audit('Deleted team #' . $id, 'team', (int) $id);
            $_SESSION['team_success'] = 'Team deleted successfully.';
        }
        $this->redirect('index.php?route=teams/index');
    }

    /** Assign an employee to a team + reporting manager. */
    public function assign() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            if (!empty($_POST['user_id']) && isset($_POST['team_id'])) {
                $teamId = ($_POST['team_id'] === 'remove' || $_POST['team_id'] === '') ? null : (int) $_POST['team_id'];
                $this->teamModel->assignMember(
                    (int) $_POST['user_id'],
                    $teamId,
                    !empty($_POST['reporting_manager_id']) ? (int) $_POST['reporting_manager_id'] : null
                );
                if ($teamId === null) {
                    $this->audit('Removed user #' . $_POST['user_id'] . ' from team', 'team');
                } else {
                    $this->audit('Assigned user #' . $_POST['user_id'] . ' to team #' . $teamId, 'team', $teamId);
                }
                $_SESSION['team_success'] = 'Team member assignment updated successfully.';
            }
        }
        $this->redirect('index.php?route=teams/index');
    }

    // ---------------- Branches ----------------

    public function addBranch() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (!Validation::validateNameWithSpaces($name)) {
                $_SESSION['team_error'] = 'Please enter a valid branch name (alphabetic and spaces only, min 3 characters).';
                $this->redirect('index.php?route=teams/index');
                return;
            }

            if (!empty($address) && !Validation::validateAddress($address)) {
                $_SESSION['team_error'] = 'Please enter a valid branch address.';
                $this->redirect('index.php?route=teams/index');
                return;
            }

            $this->teamModel->addBranch($_POST);
            $this->audit('Created branch: ' . $_POST['name'], 'branch');
            $_SESSION['team_success'] = 'Branch created successfully.';
        }
        $this->redirect('index.php?route=teams/index');
    }

    public function deleteBranch($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->teamModel->deleteBranch($id);
            $this->audit('Deleted branch #' . $id, 'branch', (int) $id);
        }
        $this->redirect('index.php?route=teams/index');
    }

    // ---------------- Territories ----------------

    public function addTerritory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            if (!empty($_POST['name'])) {
                $this->teamModel->addTerritory($_POST);
                $this->audit('Created territory: ' . $_POST['name'], 'territory');
            }
        }
        $this->redirect('index.php?route=teams/index');
    }

    public function deleteTerritory($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->teamModel->deleteTerritory($id);
            $this->audit('Deleted territory #' . $id, 'territory', (int) $id);
        }
        $this->redirect('index.php?route=teams/index');
    }

    // ---------------- Geofences ----------------

    public function addGeofence() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
            if (!empty($_POST['name']) && $_POST['center_lat'] !== '' && $_POST['center_lng'] !== '') {
                $this->teamModel->addGeofence($_POST);
                $this->audit('Created geofence: ' . $_POST['name'], 'geofence');
            }
        }
        $this->redirect('index.php?route=teams/index');
    }

    public function deleteGeofence($id = 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->teamModel->deleteGeofence($id);
            $this->audit('Deleted geofence #' . $id, 'geofence', (int) $id);
        }
        $this->redirect('index.php?route=teams/index');
    }

    /** Toggle geofence enforcement on/off. */
    public function toggleGeofence() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $on = isset($_POST['enabled']) && $_POST['enabled'] === '1';
            $this->teamModel->setGeofenceEnabled($on);
            $this->audit('Geofence enforcement ' . ($on ? 'enabled' : 'disabled'), 'settings');
        }
        $this->redirect('index.php?route=teams/index');
    }

    /** Update reporting manager and team for an employee (Sprint 2) */
    public function updateHierarchy() {
        $role = $_SESSION['user_role'] ?? '';
        if ($role !== 'admin' && $role !== 'ceo') {
            $this->jsonError('Unauthorized. Only Admins and CEOs can edit the hierarchy.', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $managerId = isset($_POST['manager_id']) ? (int) $_POST['manager_id'] : 0;
            $teamId = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;

            if ($userId <= 0) {
                $this->jsonError('Invalid user specified.');
            }

            // A user cannot report to themselves
            if ($userId === $managerId) {
                $this->jsonError('A user cannot report to themselves.');
            }

            // Check for circular dependency
            if ($managerId > 0 && $this->isDescendant($userId, $managerId)) {
                $this->jsonError('Circular dependency detected: a manager cannot report to their own subordinate.');
            }

            $mgrVal = $managerId > 0 ? $managerId : null;
            $teamVal = $teamId > 0 ? $teamId : null;

            try {
                $db = Database::getInstance()->getConnection();
                
                // Update employees table
                $stmt = $db->prepare("UPDATE employees SET reporting_manager_id = :mgr, team_id = :team WHERE user_id = :uid");
                $stmt->execute([
                    ':mgr' => $mgrVal,
                    ':team' => $teamVal,
                    ':uid' => $userId
                ]);

                $this->audit('Updated employee hierarchy/team for user ID: ' . $userId, 'employee', $userId);
                $this->jsonOk(null, 'Hierarchy updated successfully.');
            } catch (Exception $e) {
                $this->jsonError('Database error: ' . $e->getMessage());
            }
        } else {
            $this->jsonError('Invalid request method.');
        }
    }

    /** Helper function to check if target manager is a descendant of the user */
    private function isDescendant($userId, $targetManagerId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT user_id, reporting_manager_id FROM employees WHERE user_id = :uid");
            $stmt->execute([':uid' => $targetManagerId]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                return false;
            }
            $parent = $emp['reporting_manager_id'];
            if ($parent == $userId) {
                return true;
            }
            if ($parent > 0) {
                return $this->isDescendant($userId, $parent);
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
}
