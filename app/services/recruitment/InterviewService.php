<?php
/**
 * Raptor CRM — Interview Scheduling & Conflict Detection Service (Sprint 5)
 * -------------------------------------------------------------------------
 * Manages interview coordination, multi-round technical/HR scheduling, panel
 * assignments, and calendar conflict detection engines.
 */

declare(strict_types=1);

class InterviewService {

    public const TYPES = [
        'technical'  => 'Technical Interview',
        'functional' => 'Functional / Domain Round',
        'hr'         => 'HR & Culture Fit',
        'leadership' => 'Executive / Leadership'
    ];

    public const MODES = [
        'video'      => 'Video Conference',
        'in_person'  => 'In-Person (On-Site)',
        'telephone'  => 'Telephone Interview'
    ];

    /**
     * Check if a candidate has an overlapping interview booking.
     */
    public function hasCandidateConflict(
        PDO $db,
        int $candidateId,
        string $scheduledStart,
        string $scheduledEnd,
        ?int $excludeInterviewId = null
    ): bool {
        $excludeSql = $excludeInterviewId ? " AND i.interview_id != " . (int)$excludeInterviewId : "";

        $stmt = $db->prepare("
            SELECT i.interview_id, i.round_name, i.scheduled_start, i.scheduled_end
            FROM recruitment_interviews i
            JOIN recruitment_applications a ON i.application_id = a.application_id
            WHERE a.candidate_id = :cid
              AND i.status NOT IN ('cancelled', 'completed')
              AND (i.scheduled_start < :newEnd AND i.scheduled_end > :newStart)
              {$excludeSql}
            LIMIT 1
        ");
        $stmt->execute([
            ':cid'      => $candidateId,
            ':newStart' => $scheduledStart,
            ':newEnd'   => $scheduledEnd
        ]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if an interviewer has an overlapping interview booking.
     */
    public function hasInterviewerConflict(
        PDO $db,
        int $interviewerUserId,
        string $scheduledStart,
        string $scheduledEnd,
        ?int $excludeInterviewId = null
    ): bool {
        $excludeSql = $excludeInterviewId ? " AND i.interview_id != " . (int)$excludeInterviewId : "";

        $stmt = $db->prepare("
            SELECT i.interview_id, i.round_name, i.scheduled_start, i.scheduled_end
            FROM recruitment_interviews i
            JOIN recruitment_interview_panel p ON i.interview_id = p.interview_id
            WHERE p.interviewer_user_id = :uid
              AND i.status NOT IN ('cancelled', 'completed')
              AND (i.scheduled_start < :newEnd AND i.scheduled_end > :newStart)
              {$excludeSql}
            LIMIT 1
        ");
        $stmt->execute([
            ':uid'      => $interviewerUserId,
            ':newStart' => $scheduledStart,
            ':newEnd'   => $scheduledEnd
        ]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate and detect all conflicts across candidate and panel interviewers.
     *
     * @return array List of conflict descriptions, empty if no conflicts
     */
    public function checkConflicts(
        PDO $db,
        int $candidateId,
        array $interviewerUserIds,
        string $scheduledStart,
        string $scheduledEnd,
        ?int $excludeInterviewId = null
    ): array {
        $conflicts = [];

        if (strtotime($scheduledStart) >= strtotime($scheduledEnd)) {
            $conflicts[] = "Interview start time must be earlier than end time.";
            return $conflicts;
        }

        // Candidate check
        if ($this->hasCandidateConflict($db, $candidateId, $scheduledStart, $scheduledEnd, $excludeInterviewId)) {
            $conflicts[] = "Candidate already has another interview booked during this time window.";
        }

        // Interviewer check
        foreach ($interviewerUserIds as $userId) {
            if ($this->hasInterviewerConflict($db, (int)$userId, $scheduledStart, $scheduledEnd, $excludeInterviewId)) {
                $stmtUser = $db->prepare("SELECT name FROM users WHERE user_id = :id");
                $stmtUser->execute([':id' => (int)$userId]);
                $userName = $stmtUser->fetchColumn() ?: "User #{$userId}";
                $conflicts[] = "Interviewer {$userName} has a scheduling collision during this time slot.";
            }
        }

        return $conflicts;
    }

    /**
     * Schedule a new interview round with conflict validation and panel assignments.
     */
    public function scheduleInterview(
        PDO $db,
        array $data,
        array $panelUserIds,
        int $coordinatorUserId
    ): array {
        $applicationId  = (int)($data['application_id'] ?? 0);
        $scheduledStart = trim((string)($data['scheduled_start'] ?? ''));
        $scheduledEnd   = trim((string)($data['scheduled_end'] ?? ''));

        if ($applicationId <= 0 || empty($scheduledStart) || empty($scheduledEnd)) {
            throw new InvalidArgumentException("Application ID, start time, and end time are required.");
        }

        if (empty($panelUserIds)) {
            throw new InvalidArgumentException("At least one interviewer must be assigned to the interview panel.");
        }

        // Fetch application and candidate info
        $stmtApp = $db->prepare("
            SELECT a.*, c.first_name, c.last_name, c.candidate_id, c.email, j.title AS job_title
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE a.application_id = :id
        ");
        $stmtApp->execute([':id' => $applicationId]);
        $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            throw new RuntimeException("Application #{$applicationId} not found.");
        }

        $candidateId = (int)$app['candidate_id'];

        // Enforce zero-collision conflict detection
        $conflicts = $this->checkConflicts($db, $candidateId, $panelUserIds, $scheduledStart, $scheduledEnd);
        if (!empty($conflicts)) {
            throw new InvalidArgumentException("Scheduling Conflict Detected: " . implode('; ', $conflicts));
        }

        $roundNumber = (int)($data['round_number'] ?? 1);
        $roundName   = trim((string)($data['round_name'] ?? "Round {$roundNumber} Interview"));
        $type        = trim((string)($data['interview_type'] ?? 'technical'));
        $mode        = trim((string)($data['interview_mode'] ?? 'video'));
        $meetingLink = trim((string)($data['meeting_link'] ?? ''));
        $location    = trim((string)($data['location_details'] ?? ''));
        $timezone    = trim((string)($data['timezone'] ?? 'Asia/Kolkata'));

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO recruitment_interviews (
                    application_id, round_number, round_name, interview_type, interview_mode,
                    scheduled_start, scheduled_end, timezone, meeting_link, location_details,
                    coordinator_user_id, status, created_at, updated_at
                ) VALUES (
                    :aid, :rnum, :rname, :type, :mode,
                    :sstart, :send, :tz, :link, :loc,
                    :coord, 'scheduled', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute([
                ':aid'    => $applicationId,
                ':rnum'   => $roundNumber,
                ':rname'  => $roundName,
                ':type'   => $type,
                ':mode'   => $mode,
                ':sstart' => $scheduledStart,
                ':send'   => $scheduledEnd,
                ':tz'     => $timezone,
                ':link'   => $meetingLink ?: null,
                ':loc'    => $location ?: null,
                ':coord'  => $coordinatorUserId
            ]);

            $interviewId = (int)$db->lastInsertId();

            // Insert panel members
            $stmtPanel = $db->prepare("
                INSERT INTO recruitment_interview_panel (interview_id, interviewer_user_id, is_lead, created_at)
                VALUES (:iid, :uid, :lead, CURRENT_TIMESTAMP)
            ");

            $isFirst = true;
            foreach ($panelUserIds as $uid) {
                $stmtPanel->execute([
                    ':iid'  => $interviewId,
                    ':uid'  => (int)$uid,
                    ':lead' => $isFirst ? 1 : 0
                ]);
                $isFirst = false;
            }

            // Enqueue invitation email in recruitment_queue
            $payloadJson = json_encode([
                'interview_id'   => $interviewId,
                'application_id' => $applicationId,
                'candidate_name' => "{$app['first_name']} {$app['last_name']}",
                'candidate_email'=> $app['email'],
                'job_title'      => $app['job_title'],
                'round_name'     => $roundName,
                'scheduled_start'=> $scheduledStart,
                'scheduled_end'  => $scheduledEnd,
                'meeting_link'   => $meetingLink
            ]);

            $stmtQ = $db->prepare("
                INSERT INTO recruitment_queue (job_type, payload_json, status, scheduled_at, created_at)
                VALUES ('interview_invitation', :payload, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $stmtQ->execute([':payload' => $payloadJson]);

            $db->commit();

            return [
                'success'         => true,
                'interview_id'    => $interviewId,
                'round_name'      => $roundName,
                'scheduled_start' => $scheduledStart,
                'status'          => 'scheduled'
            ];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Reschedule interview with conflict detection.
     */
    public function rescheduleInterview(
        PDO $db,
        int $interviewId,
        string $newStart,
        string $newEnd,
        string $reason,
        int $actorUserId
    ): bool {
        $interview = $this->getInterviewById($db, $interviewId);
        if (!$interview) {
            throw new RuntimeException("Interview #{$interviewId} not found.");
        }

        $panelIds = array_column($interview['panel'], 'interviewer_user_id');
        $conflicts = $this->checkConflicts($db, (int)$interview['candidate_id'], $panelIds, $newStart, $newEnd, $interviewId);
        if (!empty($conflicts)) {
            throw new InvalidArgumentException("Reschedule Conflict: " . implode('; ', $conflicts));
        }

        $stmt = $db->prepare("
            UPDATE recruitment_interviews 
            SET scheduled_start = :sstart, scheduled_end = :send, 
                reschedule_reason = :reason, status = 'rescheduled', updated_at = CURRENT_TIMESTAMP
            WHERE interview_id = :id
        ");
        return $stmt->execute([
            ':sstart' => $newStart,
            ':send'   => $newEnd,
            ':reason' => $reason,
            ':id'     => $interviewId
        ]);
    }

    /**
     * Cancel an interview.
     */
    public function cancelInterview(PDO $db, int $interviewId, string $reason, int $actorUserId): bool {
        $stmt = $db->prepare("
            UPDATE recruitment_interviews 
            SET status = 'cancelled', reschedule_reason = :reason, updated_at = CURRENT_TIMESTAMP
            WHERE interview_id = :id
        ");
        return $stmt->execute([
            ':reason' => $reason,
            ':id'     => $interviewId
        ]);
    }

    /**
     * Fetch interview record by ID with candidate and panel members.
     */
    public function getInterviewById(PDO $db, int $interviewId): ?array {
        $stmt = $db->prepare("
            SELECT i.*, a.candidate_id, a.job_id, a.application_code,
                   c.first_name, c.last_name, c.email AS candidate_email, c.phone AS candidate_phone,
                   j.title AS job_title, j.department AS job_department,
                   u.name AS coordinator_name
            FROM recruitment_interviews i
            JOIN recruitment_applications a ON i.application_id = a.application_id
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            LEFT JOIN users u ON i.coordinator_user_id = u.user_id
            WHERE i.interview_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $interviewId]);
        $interview = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$interview) {
            return null;
        }

        // Fetch panel
        $stmtPanel = $db->prepare("
            SELECT p.*, u.name AS interviewer_name, u.email AS interviewer_email, r.role_name
            FROM recruitment_interview_panel p
            JOIN users u ON p.interviewer_user_id = u.user_id
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE p.interview_id = :id
            ORDER BY p.is_lead DESC, p.panel_id ASC
        ");
        $stmtPanel->execute([':id' => $interviewId]);
        $interview['panel'] = $stmtPanel->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $interview;
    }

    /**
     * Query calendar events across date range with optional filters.
     */
    public function getCalendar(
        PDO $db,
        string $startDate,
        string $endDate,
        ?int $interviewerId = null,
        ?string $department = null
    ): array {
        $conditions = [
            "i.scheduled_start >= :start",
            "i.scheduled_start <= :end",
            "i.status NOT IN ('cancelled')"
        ];
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end'   => $endDate . ' 23:59:59'
        ];

        if ($interviewerId) {
            $conditions[] = "EXISTS (SELECT 1 FROM recruitment_interview_panel p WHERE p.interview_id = i.interview_id AND p.interviewer_user_id = :uid)";
            $params[':uid'] = $interviewerId;
        }

        if ($department) {
            $conditions[] = "j.department = :dept";
            $params[':dept'] = $department;
        }

        $whereSql = implode(' AND ', $conditions);

        $stmt = $db->prepare("
            SELECT i.*, 
                   c.first_name, c.last_name, c.candidate_code,
                   j.title AS job_title, j.department AS job_department,
                   (SELECT COUNT(*) FROM recruitment_interview_feedback f WHERE f.interview_id = i.interview_id) AS feedback_count
            FROM recruitment_interviews i
            JOIN recruitment_applications a ON i.application_id = a.application_id
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            WHERE {$whereSql}
            ORDER BY i.scheduled_start ASC
        ");
        $stmt->execute($params);
        $interviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Attach panel member names to each event
        foreach ($interviews as &$iv) {
            $stmtP = $db->prepare("
                SELECT u.name, p.is_lead 
                FROM recruitment_interview_panel p
                JOIN users u ON p.interviewer_user_id = u.user_id
                WHERE p.interview_id = :id
            ");
            $stmtP->execute([':id' => $iv['interview_id']]);
            $iv['panel_members'] = $stmtP->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return $interviews;
    }
}
