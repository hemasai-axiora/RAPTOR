<?php
/**
 * Raptor CRM — Recruitment Analytics & Reporting Controller (Sprint 10)
 * -------------------------------------------------------------------------
 * Manages executive ATS analytics dashboards, funnel progression metrics,
 * sourcing channel ROI, and CSV report exports.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../services/recruitment/RecruitmentAnalyticsService.php';

class RecruitmentAnalyticsController extends Controller {

    private RecruitmentAnalyticsService $analyticsService;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('recruitment.reports.view');
        $this->analyticsService = new RecruitmentAnalyticsService();
    }

    /**
     * Primary Analytics Dashboard View.
     */
    public function index(): void {
        $db = Database::getInstance()->getConnection();

        $department = trim((string)($_GET['department'] ?? ''));
        $filters = [];
        if (!empty($department)) {
            $filters['department'] = $department;
        }

        $kpis       = $this->analyticsService->getExecutiveKpis($db, $filters);
        $funnel     = $this->analyticsService->getFunnelMetrics($db, $filters);
        $timeToHire = $this->analyticsService->getTimeToHireMetrics($db, $filters);
        $sourcing   = $this->analyticsService->getSourcingChannelRoi($db, $filters);
        $offers     = $this->analyticsService->getOfferAcceptanceRate($db, $filters);

        // Fetch distinct departments for filter dropdown
        $stmtDepts = $db->query("SELECT DISTINCT department FROM recruitment_jobs WHERE department IS NOT NULL ORDER BY department ASC");
        $departments = $stmtDepts ? $stmtDepts->fetchAll(PDO::FETCH_COLUMN) : [];

        $this->viewWithLayout('recruitment/analytics/index', 'main', [
            'title'        => 'Recruitment Analytics & Funnel Velocity | Raptor ATS',
            'active_tab'   => 'recruitment_analytics',
            'kpis'         => $kpis,
            'funnel'       => $funnel,
            'timeToHire'   => $timeToHire,
            'sourcing'     => $sourcing,
            'offers'       => $offers,
            'departments'  => $departments,
            'selectedDept' => $department,
            'csrf_token'   => $this->generateCsrfToken()
        ]);
    }

    /**
     * Export Sourcing Channel & Conversion Performance as CSV.
     */
    public function exportCsv(): void {
        $db = Database::getInstance()->getConnection();
        $sourcing = $this->analyticsService->getSourcingChannelRoi($db);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="recruitment_sourcing_roi_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Sourcing Channel', 'Total Applications', 'Qualified Candidates', 'Total Hires', 'Conversion Rate (%)']);

        foreach ($sourcing as $row) {
            fputcsv($output, [
                $row['channel'],
                $row['applications_count'],
                $row['qualified_count'],
                $row['hires_count'],
                $row['conversion_rate'] . '%'
            ]);
        }

        fclose($output);
        exit;
    }
}

