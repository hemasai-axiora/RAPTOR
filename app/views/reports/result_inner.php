<!-- AJAX Report Result Inner -->

<?php
$query = [
    'report_key' => $result['key'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'team_id' => $filters['team_id'],
    'user_id' => $filters['user_id'],
];
$csvUrl = 'index.php?route=reports/export&format=csv&' . http_build_query($query);
$pdfUrl = 'index.php?route=reports/export&format=pdf&' . http_build_query($query);
?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h4 class="text-white mb-1"><?php echo htmlspecialchars($result['title']); ?></h4>
            <div class="text-secondary small">
                <?php echo htmlspecialchars($filters['from']); ?> to <?php echo htmlspecialchars($filters['to']); ?> |
                <?php echo count($result['rows']); ?> rows
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light" href="index.php?route=reports/index">
                <i class="fa-solid fa-rotate-left me-2"></i>Back to List
            </a>
            <?php if ($_SESSION['user_role'] !== 'employer'): ?>
                <a class="btn btn-outline-success" href="<?php echo htmlspecialchars($csvUrl); ?>">
                    <i class="fa-solid fa-file-csv me-2"></i>CSV / Excel
                </a>
                <a class="btn btn-outline-light" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">
                    <i class="fa-solid fa-file-pdf me-2"></i>PDF
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($result['totals'])): ?>
    <div class="row g-3 mb-4">
        <?php foreach (array_slice($result['totals'], 0, 6, true) as $label => $value): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="pulse-card text-center">
                    <div class="text-secondary small text-uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $label)); ?></div>
                    <div class="h5 text-white mb-0 mt-2"><?php echo htmlspecialchars(is_float($value) ? number_format($value, 2) : (string)$value); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="pulse-card">
    <?php if (empty($result['rows'])): ?>
        <div class="text-center text-secondary py-5">No records found for the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <?php foreach (array_keys($result['columns']) as $label): ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
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
        </div>
    <?php endif; ?>
</div>
