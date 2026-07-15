<!-- Sprint 12 Reports Center -->

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="pulse-card">
            <h4 class="text-white mb-4"><i class="fa-solid fa-chart-column text-primary me-2"></i>Reports Center</h4>
            <form id="report-filter-form" method="get" action="index.php" class="row g-3">
                <input type="hidden" name="route" value="reports/run">
                <div class="col-12">
                    <label class="form-label text-secondary">Report</label>
                    <select name="report_key" class="form-select bg-dark text-white border-secondary">
                        <?php $selectedReport = $_GET['report_key'] ?? 'daily_summary'; ?>
                        <?php foreach ($reports as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selectedReport === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label text-secondary">From</label>
                    <input type="date" name="from" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($filters['from']); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label text-secondary">To</label>
                    <input type="date" name="to" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($filters['to']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label text-secondary">Team</label>
                    <select name="team_id" class="form-select bg-dark text-white border-secondary">
                        <option value="0">All visible teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo (int)$team->team_id; ?>" <?php echo (int)$filters['team_id'] === (int)$team->team_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($team->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label text-secondary">Person</label>
                    <select name="user_id" class="form-select bg-dark text-white border-secondary">
                        <option value="0">All visible people</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo (int)$user->user_id; ?>" <?php echo (int)$filters['user_id'] === (int)$user->user_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($user->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">
                        <i class="fa-solid fa-play me-2"></i>Run
                    </button>
                    <?php if ($_SESSION['user_role'] !== 'employer'): ?>
                        <button class="btn btn-outline-success" type="submit" formaction="index.php" name="format" value="csv" onclick="this.form.querySelector('[name=route]').value='reports/export'">
                            <i class="fa-solid fa-file-csv"></i>
                        </button>
                        <button class="btn btn-outline-light" type="submit" formaction="index.php" name="format" value="pdf" onclick="this.form.querySelector('[name=route]').value='reports/export'">
                            <i class="fa-solid fa-file-pdf"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-8" id="report-results-container">
        <div class="row g-3">
            <?php foreach ($reports as $key => $label): ?>
                <div class="col-md-6 col-xxl-4">
                    <a class="text-decoration-none report-card-link" href="index.php?route=reports/run&report_key=<?php echo urlencode($key); ?>&from=<?php echo urlencode($filters['from']); ?>&to=<?php echo urlencode($filters['to']); ?>">
                        <div class="pulse-card h-100">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <div class="text-white fw-semibold"><?php echo htmlspecialchars($label); ?></div>
                                    <div class="text-secondary small mt-2">Shared team, person, and date filters.</div>
                                </div>
                                <i class="fa-solid fa-arrow-up-right-from-square text-primary"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
$(function() {
    function loadReportAjax(url) {
        const container = $('#report-results-container');
        container.html('<div class="text-center text-secondary py-5"><i class="fa-solid fa-spinner fa-spin fa-2xl mb-3"></i><br>Running report...</div>');
        
        const ajaxUrl = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1';
        
        $.get(ajaxUrl, function(html) {
            container.html(html);
            
            // Re-intercept the back button inside the ajax content
            container.find('a[href*="route=reports/index"]').on('click', function(e) {
                e.preventDefault();
                // Reload main page or restore cards by a quick reload
                location.reload();
            });
        }).fail(function() {
            container.html('<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Failed to run report. Please try again.</div>');
        });
    }

    // Intercept form submit
    $('#report-filter-form').on('submit', function(e) {
        const activeBtn = $(document.activeElement);
        if (activeBtn.is('[value="csv"]') || activeBtn.is('[value="pdf"]')) {
            return;
        }
        
        e.preventDefault();
        const url = 'index.php?' + $(this).serialize();
        loadReportAjax(url);
    });

    // Intercept report card links
    $(document).on('click', '.report-card-link', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        loadReportAjax(url);
    });
});
</script>
