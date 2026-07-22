<?php
// Raptor CRM Reports Controller - Sprint 12 report suite.

class ReportsController extends Controller {
    private $reportModel;

    public function __construct() {
        $this->requireAuth();
        $this->requirePermission('reports', 'view');
        $this->reportModel = $this->model('ReportSuite');
    }

    public function index() {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        if ($from !== '' && $to !== '' && $to < $from) {
            $_SESSION['reports_error'] = 'To Date cannot be earlier than From Date.';
            $_GET['from'] = date('Y-m-d', strtotime('-30 days'));
            $_GET['to'] = date('Y-m-d');
        }

        $visibleUserIds = $this->visibleUserIds();
        $data = [
            'title' => 'Reports Center | Raptor CRM',
            'active_tab' => 'operations',
            'reports' => $this->reportModel->definitions(),
            'users' => $this->reportModel->getUsersForScope($visibleUserIds),
            'teams' => $this->reportModel->getTeamsForScope($visibleUserIds),
            'filters' => $this->filtersFromRequest(),
        ];

        $this->viewWithLayout('reports/index', 'main', $data);
    }

    public function run() {
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        if ($from !== '' && $to !== '' && $to < $from) {
            $_SESSION['reports_error'] = 'To Date cannot be earlier than From Date.';
            $this->redirect('index.php?route=reports/index');
            return;
        }

        $visibleUserIds = $this->visibleUserIds();
        $result = $this->reportModel->run(
            $_GET['report_key'] ?? 'daily_summary',
            $this->filtersFromRequest(),
            $visibleUserIds
        );

        $data = [
            'title' => $result['title'] . ' | Reports',
            'active_tab' => 'operations',
            'reports' => $this->reportModel->definitions(),
            'users' => $this->reportModel->getUsersForScope($visibleUserIds),
            'teams' => $this->reportModel->getTeamsForScope($visibleUserIds),
            'filters' => $result['filters'],
            'result' => $result,
        ];

        if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            $this->view('reports/result_inner', $data);
        } else {
            $this->viewWithLayout('reports/result', 'main', $data);
        }
    }

    public function export() {
        $this->requirePermission('reports', 'export');

        $visibleUserIds = $this->visibleUserIds();
        $result = $this->reportModel->run(
            $_GET['report_key'] ?? 'daily_summary',
            $this->filtersFromRequest(),
            $visibleUserIds
        );
        $format = strtolower($_GET['format'] ?? 'csv');

        if ($format === 'pdf') {
            $this->exportPdf($result);
            return;
        }

        $this->exportCsv($result);
    }

    private function filtersFromRequest(): array {
        return [
            'from' => $_GET['from'] ?? date('Y-m-d', strtotime('-30 days')),
            'to' => $_GET['to'] ?? date('Y-m-d'),
            'user_id' => $_GET['user_id'] ?? 0,
            'team_id' => $_GET['team_id'] ?? 0,
        ];
    }

    private function exportCsv(array $result): void {
        $filename = $this->downloadName($result, 'csv');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($result['columns']));
        foreach ($result['rows'] as $row) {
            $line = [];
            foreach ($result['columns'] as $field) {
                $line[] = $row[$field] ?? '';
            }
            fputcsv($output, $line);
        }
        fclose($output);
        exit();
    }

    private function exportPdf(array $result): void {
        $html = $this->reportHtml($result, true);

        if (class_exists('\\Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream($this->downloadName($result, 'pdf'), ['Attachment' => true]);
            exit();
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $this->downloadName($result, 'html') . '"');
        echo $html;
        exit();
    }

    private function reportHtml(array $result, bool $printReady = false): string {
        ob_start();
        $title = htmlspecialchars($result['title']);
        
        $logoPath = dirname(dirname(dirname(__FILE__))) . '/public/logo.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo $title; ?></title>
            <style>
                body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
                .report-header { display: block; border-bottom: 2px solid #1F5FAE; padding-bottom: 10px; margin-bottom: 15px; }
                .report-title-container { float: left; width: 70%; }
                .report-logo-container { float: right; width: 25%; text-align: right; }
                h1 { font-size: 20px; color: #1F5FAE; margin: 0; }
                .muted { color: #6b7280; font-size: 11px; margin-top: 3px; }
                table { border-collapse: collapse; width: 100%; font-size: 11px; clear: both; margin-top: 20px; }
                th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; }
                th { background: #f3f4f6; color: #111827; font-weight: bold; }
                @media print { button { display: none; } body { margin: 12px; } }
            </style>
        </head>
        <body>
            <?php if ($printReady): ?><button onclick="window.print()">Print / Save PDF</button><?php endif; ?>
            
            <div class="report-header">
                <div class="report-title-container">
                    <h1><?php echo $title; ?></h1>
                    <div class="muted">
                        <?php echo htmlspecialchars($result['filters']['from']); ?> to
                        <?php echo htmlspecialchars($result['filters']['to']); ?> |
                        Generated <?php echo date('Y-m-d H:i'); ?>
                    </div>
                </div>
                <div class="report-logo-container">
                    <?php if ($logoBase64): ?>
                        <img src="<?php echo $logoBase64; ?>" style="height: 38px; width: auto; max-width: 100%; object-fit: contain;" alt="Raptor Logo">
                    <?php endif; ?>
                </div>
                <div style="clear: both;"></div>
            </div>
            <table>
                <thead><tr>
                    <?php foreach (array_keys($result['columns']) as $label): ?>
                        <th><?php echo htmlspecialchars($label); ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($result['columns'] as $field): ?>
                                <td><?php echo htmlspecialchars((string)($row[$field] ?? '')); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>if (window.location.search.indexOf('autoprint=1') !== -1) window.print();</script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function downloadName(array $result, string $ext): string {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($result['key']));
        return 'raptor_' . trim($slug, '_') . '_' . date('Ymd_His') . '.' . $ext;
    }
}
