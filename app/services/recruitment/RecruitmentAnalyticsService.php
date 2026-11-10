<?php
/**
 * Raptor CRM — Recruitment Analytics & Intelligence Service (Sprint 10)
 * -------------------------------------------------------------------------
 * Provides executive analytics for hiring velocity, recruitment funnel
 * drop-off analysis, sourcing channel ROI, and offer acceptance ratios.
 */

declare(strict_types=1);

class RecruitmentAnalyticsService {

    /**
     * Compute hiring funnel progression and stage-by-stage drop-off rates.
     */
    public function getFunnelMetrics(PDO $db, array $filters = []): array {
        $whereSql = "WHERE 1=1";
        $params = [];

        if (!empty($filters['department'])) {
            $whereSql .= " AND j.department = :dept";
            $params[':dept'] = $filters['department'];
        }

        if (!empty($filters['job_id'])) {
            $whereSql .= " AND a.job_id = :jid";
            $params[':jid'] = (int)$filters['job_id'];
        }

        // Total applications
        $stmtTotal = $db->prepare("
            SELECT COUNT(DISTINCT a.application_id) 
            FROM recruitment_applications a
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            {$whereSql}
        ");
        $stmtTotal->execute($params);
        $totalApplied = (int)$stmtTotal->fetchColumn();

        // Stage definitions for ATS pipeline
        $stages = [
            'applied'             => 'Applied / Ingested',
            'screened'            => 'Screened & Qualified',
            'assessment'          => 'Technical Assessment',
            'technical_interview' => 'Panel Interview',
            'offered'             => 'Offer Released',
            'hired'               => 'Converted to Employee'
        ];

        // Fetch counts per stage from stage audit history
        $stageCounts = [];
        $stmtStages = $db->prepare("
            SELECT s.to_stage, COUNT(DISTINCT s.application_id) AS count
            FROM recruitment_application_stages s
            JOIN recruitment_applications a ON s.application_id = a.application_id
            JOIN recruitment_jobs j ON a.job_id = j.job_id
            {$whereSql}
            GROUP BY s.to_stage
        ");
        $stmtStages->execute($params);
        $rows = $stmtStages->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $funnel = [];
        $prevCount = $totalApplied;

        foreach ($stages as $key => $label) {
            $count = ($key === 'applied') ? $totalApplied : (int)($rows[$key] ?? 0);
            
            // Percentage of top-of-funnel
            $pctOfTotal = $totalApplied > 0 ? round(($count / $totalApplied) * 100, 1) : 0.0;

            // Conversion from previous step
            $stepConversion = $prevCount > 0 ? round(($count / $prevCount) * 100, 1) : 0.0;
            if ($stepConversion > 100.0) $stepConversion = 100.0;

            $funnel[] = [
                'stage_key'       => $key,
                'stage_label'     => $label,
                'candidate_count' => $count,
                'pct_of_total'    => $pctOfTotal,
                'step_conversion' => $stepConversion
            ];

            if ($count > 0) {
                $prevCount = $count;
            }
        }

        return [
            'total_applications' => $totalApplied,
            'funnel_stages'      => $funnel
        ];
    }

    /**
     * Compute average Time to Fill and Time to Hire metrics.
     */
    public function getTimeToHireMetrics(PDO $db, array $filters = []): array {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $diffExpr = "(julianday(a.stage_updated_at) - julianday(a.created_at))";
        } else {
            $diffExpr = "DATEDIFF(a.stage_updated_at, a.created_at)";
        }

        $stmt = $db->query("
            SELECT 
                COUNT(*) AS total_hires,
                AVG({$diffExpr}) AS avg_days_to_hire,
                MIN({$diffExpr}) AS min_days_to_hire,
                MAX({$diffExpr}) AS max_days_to_hire
            FROM recruitment_applications a
            WHERE a.current_stage = 'hired' AND a.stage_updated_at IS NOT NULL
        ");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgDays = $data && $data['avg_days_to_hire'] !== null ? round((float)$data['avg_days_to_hire'], 1) : 0.0;
        $totalHires = (int)($data['total_hires'] ?? 0);

        return [
            'total_hires'       => $totalHires,
            'avg_days_to_hire'  => $avgDays,
            'min_days_to_hire'  => round((float)($data['min_days_to_hire'] ?? 0.0), 1),
            'max_days_to_hire'  => round((float)($data['max_days_to_hire'] ?? 0.0), 1),
            'benchmark_target'  => 30.0 // Standard 30-day enterprise target
        ];
    }

    /**
     * Calculate candidate sourcing channel distribution and conversion efficiency.
     */
    public function getSourcingChannelRoi(PDO $db, array $filters = []): array {
        $stmt = $db->query("
            SELECT 
                COALESCE(c.source, 'direct') AS channel,
                COUNT(a.application_id) AS applications_count,
                SUM(CASE WHEN a.current_stage IN ('technical_interview', 'offered', 'hired') THEN 1 ELSE 0 END) AS qualified_count,
                SUM(CASE WHEN a.current_stage = 'hired' THEN 1 ELSE 0 END) AS hires_count
            FROM recruitment_applications a
            JOIN recruitment_candidates c ON a.candidate_id = c.candidate_id
            GROUP BY COALESCE(c.source, 'direct')
            ORDER BY applications_count DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $results = [];
        foreach ($rows as $row) {
            $apps = (int)$row['applications_count'];
            $hires = (int)$row['hires_count'];
            $hireRate = $apps > 0 ? round(($hires / $apps) * 100, 1) : 0.0;

            $results[] = [
                'channel'            => ucfirst((string)$row['channel']),
                'applications_count' => $apps,
                'qualified_count'    => (int)$row['qualified_count'],
                'hires_count'        => $hires,
                'conversion_rate'    => $hireRate
            ];
        }

        return $results;
    }

    /**
     * Calculate formal offer acceptance ratio and decline analytics.
     */
    public function getOfferAcceptanceRate(PDO $db, array $filters = []): array {
        $stmt = $db->query("
            SELECT 
                COUNT(*) AS total_released,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) AS declined_count,
                SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) AS pending_count
            FROM recruitment_offers
            WHERE status IN ('released', 'accepted', 'declined')
        ");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $released = (int)($data['total_released'] ?? 0);
        $accepted = (int)($data['accepted_count'] ?? 0);
        $declined = (int)($data['declined_count'] ?? 0);
        $pending  = (int)($data['pending_count'] ?? 0);

        $acceptanceRate = ($accepted + $declined) > 0 
            ? round(($accepted / ($accepted + $declined)) * 100, 1) 
            : 0.0;

        return [
            'total_released'  => $released,
            'accepted_count'  => $accepted,
            'declined_count'  => $declined,
            'pending_count'   => $pending,
            'acceptance_rate' => $acceptanceRate
        ];
    }

    /**
     * Consolidate high-level KPI tiles for executive dashboard.
     */
    public function getExecutiveKpis(PDO $db, array $filters = []): array {
        $funnel = $this->getFunnelMetrics($db, $filters);
        $timeToHire = $this->getTimeToHireMetrics($db, $filters);
        $offers = $this->getOfferAcceptanceRate($db, $filters);

        // Open requisitions count
        $stmtReq = $db->query("
            SELECT COUNT(*), COALESCE(SUM(vacancies), 0)
            FROM recruitment_requisitions 
            WHERE status IN ('approved', 'in_progress')
        ");
        [$openReqs, $openPositions] = $stmtReq->fetch(PDO::FETCH_NUM) ?: [0, 0];

        // Overall ATS funnel conversion rate (Hired / Total Applications)
        $totalApps = $funnel['total_applications'];
        $totalHires = $timeToHire['total_hires'];
        $funnelConversion = $totalApps > 0 ? round(($totalHires / $totalApps) * 100, 1) : 0.0;

        return [
            'total_applications'     => $totalApps,
            'total_hires'            => $totalHires,
            'open_requisitions'      => (int)$openReqs,
            'open_positions'         => (int)$openPositions,
            'avg_days_to_hire'       => $timeToHire['avg_days_to_hire'],
            'funnel_conversion_rate' => $funnelConversion,
            'offer_acceptance_rate'  => $offers['acceptance_rate']
        ];
    }
}
