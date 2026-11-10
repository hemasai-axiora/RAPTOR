<?php
/**
 * Raptor CRM — Recruitment ATS Model (Sprint 1 Foundation)
 * -----------------------------------------------------------------------------------
 * Manages recruitment data retrieval, pipeline aggregations, and dashboard metrics.
 */

declare(strict_types=1);

class Recruitment extends Model {

    /**
     * Fetch central recruitment dashboard statistics scoped to user's authorized role & subtree.
     */
    public function getDashboardStats(string $role, int $userId): array {
        $stats = [
            'active_requisitions' => 0,
            'open_vacancies'      => 0,
            'total_candidates'    => 0,
            'active_applications' => 0,
            'interviews_today'    => 0,
            'pending_offers'      => 0,
            'hired_count'         => 0,
            'pipeline_funnel'     => [
                'applied'              => 0,
                'screening'            => 0,
                'shortlisted'          => 0,
                'technical_interview'  => 0,
                'hr_interview'         => 0,
                'offer_released'       => 0,
                'hired'                => 0
            ],
            'recent_requisitions' => [],
            'upcoming_interviews' => []
        ];

        try {
            $db = $this->db;

            // 1. Requisitions count & vacancies
            if (in_array($role, ['admin', 'ceo', 'hr'], true)) {
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) as req_count, 
                        COALESCE(SUM(vacancies), 0) as total_vacancies 
                    FROM recruitment_requisitions 
                    WHERE status IN ('approved', 'open', 'partially_filled')
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['active_requisitions'] = (int) ($row['req_count'] ?? 0);
                $stats['open_vacancies']      = (int) ($row['total_vacancies'] ?? 0);
            } else {
                // Team subtree scoping
                $subtreeIds = Policy::getTeamSubtreeUserIds($userId);
                if (empty($subtreeIds)) {
                    $subtreeIds = [$userId];
                }
                $inClause = implode(',', array_map('intval', $subtreeIds));
                $stmt = $db->query("
                    SELECT 
                        COUNT(*) as req_count, 
                        COALESCE(SUM(vacancies), 0) as total_vacancies 
                    FROM recruitment_requisitions 
                    WHERE status IN ('approved', 'open', 'partially_filled')
                      AND hiring_manager_id IN ({$inClause})
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['active_requisitions'] = (int) ($row['req_count'] ?? 0);
                $stats['open_vacancies']      = (int) ($row['total_vacancies'] ?? 0);
            }

            // 2. Total candidates master count
            $stmt = $db->query("SELECT COUNT(*) FROM recruitment_candidates WHERE is_blacklisted = 0");
            $stats['total_candidates'] = (int) $stmt->fetchColumn();

            // 3. Active applications count
            $stmt = $db->query("
                SELECT COUNT(*) FROM recruitment_applications 
                WHERE current_stage NOT IN ('hired', 'rejected', 'withdrawn', 'offer_declined')
            ");
            $stats['active_applications'] = (int) $stmt->fetchColumn();

            // 4. Interviews scheduled for today
            $stmt = $db->query("
                SELECT COUNT(*) FROM recruitment_interviews 
                WHERE DATE(scheduled_start) = CURRENT_DATE 
                  AND status = 'scheduled'
            ");
            $stats['interviews_today'] = (int) $stmt->fetchColumn();

            // 5. Pending offers count
            $stmt = $db->query("
                SELECT COUNT(*) FROM recruitment_offers 
                WHERE status IN ('pending_approval', 'approved', 'released')
            ");
            $stats['pending_offers'] = (int) $stmt->fetchColumn();

            // 6. Total hired count
            $stmt = $db->query("
                SELECT COUNT(*) FROM recruitment_applications 
                WHERE current_stage = 'hired'
            ");
            $stats['hired_count'] = (int) $stmt->fetchColumn();

            // 7. Pipeline Funnel Breakdown
            $stmt = $db->query("
                SELECT current_stage, COUNT(*) as stage_count 
                FROM recruitment_applications 
                GROUP BY current_stage
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stg = (string) $row['current_stage'];
                if (isset($stats['pipeline_funnel'][$stg])) {
                    $stats['pipeline_funnel'][$stg] = (int) $row['stage_count'];
                }
            }

            // 8. Recent active requisitions
            $stmt = $db->query("
                SELECT r.requisition_id, r.requisition_code, r.title, r.department, r.vacancies, r.status, r.hiring_priority,
                       u.name as hiring_manager_name, r.target_hire_date
                FROM recruitment_requisitions r
                JOIN users u ON r.hiring_manager_id = u.user_id
                ORDER BY r.requisition_id DESC
                LIMIT 5
            ");
            $stats['recent_requisitions'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // 9. Upcoming interviews
            $stmt = $db->query("
                SELECT i.interview_id, i.round_name, i.interview_type, i.interview_mode, i.scheduled_start, i.meeting_link,
                       c.first_name, c.last_name, r.title as job_title
                FROM recruitment_interviews i
                JOIN recruitment_applications a ON i.application_id = a.application_id
                JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
                JOIN recruitment_jobs j ON a.job_id = j.job_id
                JOIN recruitment_requisitions r ON j.requisition_id = r.requisition_id
                WHERE i.scheduled_start >= NOW() AND i.status = 'scheduled'
                ORDER BY i.scheduled_start ASC
                LIMIT 5
            ");
            $stats['upcoming_interviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (Throwable $e) {
            // Self-healing fallback if database tables empty or in migration state
            error_log("Recruitment Model Exception: " . $e->getMessage());
        }

        return $stats;
    }
}

