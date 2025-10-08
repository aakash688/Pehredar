<?php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/json_helper.php';

$action = $_GET['action'] ?? '';
$team_actions = ['create_team', 'update_team', 'delete_team', 'get_team_stats', 'getAllTeams', 'getAllTeamMembers'];

// Only handle and exit for GET-only helper endpoints here.
// For POST actions (create/update/delete), the routing is handled in team_actions.php
if (in_array($action, $team_actions) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getAllTeams':
            getAllTeams();
            break;
        case 'getAllTeamMembers':
            getAllTeamMembers();
            break;
    }
    exit;
}

class TeamController {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // Get all details for a specific team
    public function getTeamDetails($teamId) {
        // Main team info - optimize by using an indexed lookup
        $team = $this->db->query("
            SELECT 
                t.id, 
                t.team_name, 
                t.description, 
                t.created_at, 
                u.id as supervisor_id,
                CONCAT(u.first_name, ' ', u.surname) as supervisor_name
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.role = 'Supervisor'
            LEFT JOIN users u ON tm.user_id = u.id
            WHERE t.id = ?
            LIMIT 1
        ", [$teamId])->fetch();

        if (!$team) {
            return null;
        }

        // Team members - optimize by selecting only needed fields and using ORDER BY on indexed columns
        $team['members'] = $this->db->query("
            SELECT 
                u.id, 
                u.first_name, 
                u.surname, 
                u.user_type
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.role != 'Supervisor'
            ORDER BY u.first_name, u.surname
        ", [$teamId])->fetchAll();
        
        return $team;
    }

    // Create a new team
    public function createTeam($data) {
        // Validate input
        $errors = [];
        $fieldErrors = [];

        if (empty($data['team_name'])) {
            $fieldErrors['team-name'] = 'Team name is required.';
        }

        if (empty($data['supervisor_id'])) {
            $fieldErrors['supervisor-id'] = 'Supervisor is required.';
        }

        if (!empty($fieldErrors)) {
            return [
                'success' => false, 
                'error' => 'Please correct the validation errors.',
                'field_errors' => $fieldErrors
            ];
        }

        $this->db->getPdo()->beginTransaction();
        try {
            // 1. Create the team
            $this->db->query("INSERT INTO teams (team_name, description, created_by) VALUES (?, ?, ?)", [
                trim($data['team_name']),
                !empty($data['description']) ? trim($data['description']) : null,
                $_SESSION['user_id'] ?? 1 // Default to 1 if session not set
            ]);
            $teamId = $this->db->lastInsertId();

            // 2. Assign supervisor
            $this->db->query("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'Supervisor')", [
                $teamId,
                $data['supervisor_id']
            ]);

            // 3. Assign members
            if (!empty($data['team_members'])) {
                foreach ($data['team_members'] as $memberId) {
                    $this->db->query("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'Guard')", [
                        $teamId,
                        $memberId
                    ]);
                }
            }

            $this->db->getPdo()->commit();
            return [
                'success' => true, 
                'team_id' => $teamId,
                'message' => 'Team created successfully!'
            ];
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            return [
                'success' => false, 
                'error' => $e->getMessage()
            ];
        }
    }

    // Update an existing team
    public function updateTeam($data) {
        // Validate input
        $errors = [];
        $fieldErrors = [];

        if (empty($data['team_name'])) {
            $fieldErrors['team-name'] = 'Team name is required.';
        }

        if (empty($data['supervisor_id'])) {
            $fieldErrors['supervisor-id'] = 'Supervisor is required.';
        }

        if (empty($data['team_id'])) {
            $fieldErrors['team_id'] = 'Team ID is required for update.';
        }

        if (!empty($fieldErrors)) {
            return [
                'success' => false, 
                'error' => 'Please correct the validation errors.',
                'field_errors' => $fieldErrors
            ];
        }

        $this->db->getPdo()->beginTransaction();
        try {
            // 1. Update team details
            $this->db->query("UPDATE teams SET team_name = ?, description = ? WHERE id = ?", [
                trim($data['team_name']),
                !empty($data['description']) ? trim($data['description']) : null,
                $data['team_id']
            ]);

            // 2. Update supervisor (remove old, add new)
            $this->db->query("DELETE FROM team_members WHERE team_id = ? AND role = 'Supervisor'", [$data['team_id']]);
            $this->db->query("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'Supervisor')", [
                $data['team_id'],
                $data['supervisor_id']
            ]);

            // 3. Sync team members
            $this->db->query("DELETE FROM team_members WHERE team_id = ? AND role != 'Supervisor'", [$data['team_id']]);
            if (!empty($data['team_members'])) {
                foreach ($data['team_members'] as $memberId) {
                    $this->db->query("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'Guard')", [
                        $data['team_id'],
                        $memberId
                    ]);
                }
            }

            $this->db->getPdo()->commit();
            return [
                'success' => true,
                'message' => 'Team updated successfully!'
            ];
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            return [
                'success' => false, 
                'error' => $e->getMessage()
            ];
        }
    }

    // Delete a team
    public function deleteTeam($teamId) {
        // Validate input
        if (!$teamId || !is_numeric($teamId)) {
            return ['success' => false, 'error' => 'Invalid Team ID provided.'];
        }

        $this->db->getPdo()->beginTransaction();
        try {
            // First, log the team details before deletion
            $teamDetails = $this->getTeamDetails($teamId);
            
            // Remove all team members
            $membersRemoved = $this->db->query("DELETE FROM team_members WHERE team_id = ?", [$teamId]);
            $memberCount = $membersRemoved->rowCount();

            // Then delete the team
            $deleteResult = $this->db->query("DELETE FROM teams WHERE id = ?", [$teamId]);
            
            // Check if any rows were actually deleted
            if ($deleteResult->rowCount() === 0) {
                $this->db->getPdo()->rollBack();
                return [
                    'success' => false, 
                    'error' => 'Team not found or already deleted.',
                    'details' => [
                        'team_id' => $teamId,
                        'existing_team' => $teamDetails
                    ]
                ];
            }

            $this->db->getPdo()->commit();
            return [
                'success' => true, 
                'message' => 'Team deleted successfully.',
                'details' => [
                    'team_id' => $teamId,
                    'team_name' => $teamDetails['team_name'] ?? 'Unknown',
                    'members_removed' => $memberCount
                ]
            ];
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            return [
                'success' => false, 
                'error' => 'Failed to delete team: ' . $e->getMessage(),
                'exception_details' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'team_id' => $teamId
                ]
            ];
        }
    }

    // Remove a member from a team
    public function removeMember($teamId, $userId) {
        try {
            $this->db->query("DELETE FROM team_members WHERE team_id = ? AND user_id = ?", [$teamId, $userId]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Migrate a member to another team
    public function migrateMember($userId, $fromTeamId, $toTeamId) {
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Check if the target team exists
            $targetTeam = $this->db->query("SELECT id FROM teams WHERE id = ?", [$toTeamId])->fetch();
            if (!$targetTeam) {
                return ['success' => false, 'error' => 'Target team does not exist.'];
            }
            
            // Check if the user is already in the target team
            $existingMembership = $this->db->query("SELECT id FROM team_members WHERE team_id = ? AND user_id = ?", [$toTeamId, $userId])->fetch();
            if ($existingMembership) {
                return ['success' => false, 'error' => 'User is already a member of the target team.'];
            }
            
            // Remove from current team
            $this->db->query("DELETE FROM team_members WHERE team_id = ? AND user_id = ?", [$fromTeamId, $userId]);
            
            // Add to new team
            $this->db->query("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'Guard')", [$toTeamId, $userId]);
            
            $this->db->getPdo()->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get all teams (for dropdowns) - optimized to fetch only necessary fields
    public function getAllTeams() {
        return $this->db->query("SELECT id, team_name FROM teams ORDER BY team_name")->fetchAll();
    }
    
    // Get team statistics for AJAX updates
    public function getTeamStats() {
        // Total teams
        $totalTeams = $this->db->query('SELECT COUNT(*) as cnt FROM teams')->fetch()['cnt'];

        // Total employees assigned to teams
        $totalAssignedEmployees = $this->db->query('
            SELECT COUNT(DISTINCT user_id) as cnt 
            FROM team_members
        ')->fetch()['cnt'];

        // Total employees
        $totalEmployees = $this->db->query('
            SELECT COUNT(*) as cnt 
            FROM users 
            WHERE user_type IN ("Guard", "Armed Guard", "Bouncer", "Housekeeping")
        ')->fetch()['cnt'];

        $totalUnassignedEmployees = $totalEmployees - $totalAssignedEmployees;

        // Average team size
        $averageTeamSize = 0;
        if ($totalTeams > 0) {
            $averageTeamSize = $this->db->query('
                SELECT AVG(member_count) as avg_size FROM (
                    SELECT team_id, COUNT(*) as member_count 
                    FROM team_members 
                    GROUP BY team_id
                ) as team_sizes
            ')->fetch()['avg_size'];
            $averageTeamSize = round($averageTeamSize, 1);
        }

        return [
            'total_teams' => $totalTeams,
            'total_assigned' => $totalAssignedEmployees,
            'total_unassigned' => $totalUnassignedEmployees,
            'average_size' => $averageTeamSize
        ];
    }
} 

function getAllTeams() {
    try {
        $db = new Database();
        $stmt = $db->prepare("SELECT id, team_name FROM teams ORDER BY team_name");
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response([
            'success' => true, 
            'teams' => $teams
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'An error occurred while fetching teams: ' . $e->getMessage()
        ], 500);
    }
}

function getAllTeamMembers() {
    try {
        $team_id = $_GET['team_id'] ?? null;

        if (!$team_id) {
            json_response([
                'success' => false, 
                'message' => 'Team ID is required'
            ], 400);
            exit;
        }

        $db = new Database();
        $stmt = $db->prepare("
            SELECT 
                u.id, 
                u.first_name, 
                u.surname, 
                u.user_type,
                tm.role
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.role = 'Guard'
            ORDER BY u.first_name, u.surname
        ");
        $stmt->execute([$team_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response([
            'success' => true, 
            'members' => $members
        ]);
    } catch (Exception $e) {
        json_response([
            'success' => false, 
            'message' => 'An error occurred while fetching team members: ' . $e->getMessage()
        ], 500);
    }
}
?> 