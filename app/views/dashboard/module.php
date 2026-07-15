<?php
$accentMap = [
    'indigo' => '#6366f1',
    'emerald' => '#10b981',
    'amber' => '#f59e0b',
    'rose' => '#f43f5e',
    'cyan' => '#06b6d4',
];
$accent = $accentMap[$prefs['theme_accent']] ?? $accentMap['indigo'];
$kpis = $dashboard_data['kpis'];
$fmt = function ($value, $decimals = 0) {
    return number_format((float) $value, $decimals);
};
$meta = function ($widget) use ($widget_meta) {
    return $widget_meta[$widget] ?? ['label' => ucwords(str_replace('_', ' ', $widget)), 'icon' => 'fa-square'];
};
?>

<style>
    .dashboard-accent { border-top: 3px solid <?php echo $accent; ?>; }
    .dashboard-widget { min-height: 180px; }
    .dashboard-kpi { font-size: 1.8rem; font-weight: 700; color: #fff; }
    .widget-shell { display: contents; }
</style>

<div class="pulse-card dashboard-accent mb-4">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
        <div>
            <div class="text-secondary small mb-1">Dashboard Module</div>
            <h4 class="text-white mb-1"><?php echo htmlspecialchars($dashboard['label']); ?></h4>
            <div class="text-secondary"><?php echo htmlspecialchars($dashboard['description']); ?></div>
            <div class="text-secondary small mt-2">Range: <?php echo htmlspecialchars($dashboard_data['range_label']); ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-self-start align-self-xl-center">
            <?php foreach ($dashboards as $key => $item): ?>
                <a class="btn btn-sm <?php echo $key === $dashboard['key'] ? 'btn-primary' : 'btn-outline-light'; ?>"
                   href="index.php?route=dashboard/show/<?php echo urlencode($key); ?>">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="pulse-card mb-4">
    <form method="post" action="index.php?route=dashboard/configure/<?php echo urlencode($dashboard['key']); ?>" class="row g-3 align-items-end">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="col-md-2">
            <label class="form-label text-secondary">Range Days</label>
            <input type="number" name="date_range_days" min="1" max="365" class="form-control bg-dark text-white border-secondary" value="<?php echo (int) $prefs['date_range_days']; ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Accent</label>
            <select name="theme_accent" class="form-select bg-dark text-white border-secondary">
                <?php foreach ($accentMap as $name => $color): ?>
                    <option value="<?php echo $name; ?>" <?php echo $prefs['theme_accent'] === $name ? 'selected' : ''; ?>><?php echo ucfirst($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label text-secondary">Widgets</label>
            <div class="row g-2">
                <?php foreach ($dashboard['widgets'] as $i => $widget): ?>
                    <?php $m = $meta($widget); ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="d-flex gap-2 align-items-center bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded p-2">
                            <input type="number" name="widget_order[<?php echo htmlspecialchars($widget); ?>]" value="<?php echo $i + 1; ?>" min="1" class="form-control form-control-sm bg-dark text-white border-secondary" style="width:64px;">
                            <input class="form-check-input" type="checkbox" name="hidden_widgets[]" value="<?php echo htmlspecialchars($widget); ?>" <?php echo in_array($widget, $prefs['hidden_widgets'], true) ? 'checked' : ''; ?>>
                            <span class="text-secondary small flex-grow-1">Hide <?php echo htmlspecialchars($m['label']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save Dashboard</button>
        </div>
    </form>
</div>

<div class="row g-4">
    <?php foreach ($widgets as $widget): ?>
        <?php $m = $meta($widget); ?>
        <div class="col-12 col-xl-6">
            <div class="pulse-card dashboard-widget">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="text-white mb-0"><i class="fa-solid <?php echo htmlspecialchars($m['icon']); ?> me-2" style="color:<?php echo $accent; ?>"></i><?php echo htmlspecialchars($m['label']); ?></h5>
                    <span class="text-secondary small">Customizable</span>
                </div>

                <?php if (in_array($widget, ['revenue', 'conversion_rate', 'target_completion', 'performance_rank', 'distance', 'communications', 'meetings', 'tasks', 'followups', 'attendance', 'task_completion', 'followup_discipline'], true)): ?>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-secondary small">Revenue</div>
                            <div class="dashboard-kpi"><?php echo $fmt($kpis['revenue']); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Pipeline / Forecast</div>
                            <div class="dashboard-kpi"><?php echo $fmt($kpis['pipeline_value']); ?> <span class="text-secondary fs-6">/ <?php echo $fmt($kpis['forecast']); ?></span></div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Leads / Conversions</div>
                            <div class="dashboard-kpi"><?php echo $fmt($kpis['leads']); ?> <span class="text-secondary fs-6">/ <?php echo $fmt($kpis['conversions']); ?></span></div>
                        </div>
                        <div class="col-6">
                            <div class="text-secondary small">Activity / Distance</div>
                            <div class="dashboard-kpi"><?php echo $fmt($kpis['communications']); ?> <span class="text-secondary fs-6">/ <?php echo $fmt($kpis['distance'], 1); ?> km</span></div>
                        </div>
                    </div>
                <?php elseif ($widget === 'pipeline' || $widget === 'lead_funnel'): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead><tr><th>Status</th><th>Count</th><th>Value</th></tr></thead>
                            <tbody>
                                <?php foreach ($dashboard_data['pipeline'] as $row): ?>
                                    <tr><td><?php echo htmlspecialchars($row->status); ?></td><td><?php echo (int) $row->count; ?></td><td><?php echo $fmt($row->value); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($widget === 'activity_mix'): ?>
                    <div class="row g-2">
                        <?php foreach ($dashboard_data['activity_mix'] as $item): ?>
                            <div class="col-6 col-md-4">
                                <div class="bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded p-3">
                                    <div class="text-secondary small"><?php echo htmlspecialchars($item['label']); ?></div>
                                    <div class="h4 text-white mb-0"><?php echo $fmt($item['value']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'tasks'): ?>
                    <div class="row g-3">
                        <?php foreach ($dashboard_data['tasks'] as $label => $value): ?>
                            <div class="col-6"><div class="text-secondary small"><?php echo htmlspecialchars(str_replace('_', ' ', $label)); ?></div><div class="dashboard-kpi"><?php echo $fmt($value); ?></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'followups'): ?>
                    <div class="row g-3">
                        <?php foreach ($dashboard_data['followups'] as $label => $value): ?>
                            <div class="col-4"><div class="text-secondary small"><?php echo htmlspecialchars($label); ?></div><div class="dashboard-kpi"><?php echo $fmt($value); ?></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'today_targets' || $widget === 'daily_summary'): ?>
                    <?php $rollup = $dashboard_data['today_rollup']; ?>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-secondary small">Target %</div><div class="dashboard-kpi"><?php echo $fmt($rollup['targets']['completion'], 1); ?>%</div></div>
                        <div class="col-6"><div class="text-secondary small">Completed Tasks</div><div class="dashboard-kpi"><?php echo $fmt($rollup['tasks']['completed_today']); ?></div></div>
                        <div class="col-6"><div class="text-secondary small">Leads / Converted</div><div class="dashboard-kpi"><?php echo $fmt($rollup['leads']['generated']); ?> <span class="text-secondary fs-6">/ <?php echo $fmt($rollup['leads']['converted']); ?></span></div></div>
                        <div class="col-6"><div class="text-secondary small">Missed Follow-ups</div><div class="dashboard-kpi"><?php echo $fmt($rollup['followups']['missed']); ?></div></div>
                    </div>
                <?php elseif ($widget === 'route_health'): ?>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-secondary small">Distance</div><div class="dashboard-kpi"><?php echo $fmt($kpis['distance'], 1); ?> km</div></div>
                        <div class="col-6"><div class="text-secondary small">Location Issues</div><div class="dashboard-kpi"><?php echo $fmt(count(array_filter($dashboard_data['live_board'], function ($r) { return $r->location_off; }))); ?></div></div>
                    </div>
                <?php elseif ($widget === 'proofs'): ?>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-secondary small">Task Proofs</div><div class="dashboard-kpi"><?php echo $fmt($dashboard_data['proofs']['task_proofs']); ?></div></div>
                        <div class="col-6"><div class="text-secondary small">Meeting Selfies</div><div class="dashboard-kpi"><?php echo $fmt($dashboard_data['proofs']['meeting_selfies']); ?></div></div>
                    </div>
                <?php elseif ($widget === 'live_board'): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm mb-0">
                            <thead><tr><th>Person</th><th>State</th><th>Location</th></tr></thead>
                            <tbody>
                                <?php foreach ($dashboard_data['live_board'] as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row->name); ?></td>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', $row->state)); ?></td>
                                        <td><?php echo $row->location_off ? 'Stale/off' : 'Recent'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($widget === 'top_performers' || $widget === 'low_performers'): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($dashboard_data[$widget] as $row): ?>
                            <div class="list-group-item bg-transparent text-white border-secondary border-opacity-10 d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($row->name); ?></span>
                                <span><?php echo $fmt($row->overall_score, 1); ?> · <?php echo htmlspecialchars($row->performance_band); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'risk_alerts'): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($dashboard_data['risk_alerts'] as $row): ?>
                            <div class="list-group-item bg-transparent text-white border-secondary border-opacity-10">
                                <span class="badge bg-warning text-dark me-2"><?php echo htmlspecialchars($row->severity); ?></span><?php echo htmlspecialchars($row->title); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'review_queue'): ?>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-secondary small">Task Reviews</div><div class="dashboard-kpi"><?php echo $fmt($dashboard_data['review_queue']['tasks']); ?></div></div>
                        <div class="col-6"><div class="text-secondary small">Attendance Approvals</div><div class="dashboard-kpi"><?php echo $fmt($dashboard_data['review_queue']['attendance']); ?></div></div>
                    </div>
                <?php elseif ($widget === 'high_value_leads'): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($dashboard_data['high_value_leads'] as $row): ?>
                            <div class="list-group-item bg-transparent text-white border-secondary border-opacity-10 d-flex justify-content-between">
                                <span><?php echo htmlspecialchars(trim($row->first_name . ' ' . ($row->last_name ?? ''))); ?></span>
                                <span><?php echo $fmt($row->lead_value); ?> · <?php echo htmlspecialchars($row->status); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($widget === 'source_mix'): ?>
                    <div class="row g-2">
                        <?php foreach ($dashboard_data['source_mix'] as $row): ?>
                            <div class="col-6"><div class="bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded p-3"><div class="text-secondary small"><?php echo htmlspecialchars($row->source); ?></div><div class="h4 text-white mb-0"><?php echo $fmt($row->count); ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-secondary">This widget is connected to the selected dashboard KPIs and will expand as more source data is captured.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
