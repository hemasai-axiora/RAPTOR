<div class="pulse-card">
    <h4 class="text-white mb-4"><i class="fa-solid fa-file-invoice text-primary me-2"></i>HRMS Reports Centre</h4>

    <div class="row">
        <!-- Date Filters Panel (Left 4 Columns) -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3">Report Scope</h5>
                <div class="mb-3">
                    <label for="from_date" class="form-label small fw-bold text-secondary text-uppercase">From Date</label>
                    <input type="date" class="form-control py-2 border-secondary bg-dark text-white" id="from_date" name="from_date" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="mb-4">
                    <label for="to_date" class="form-label small fw-bold text-secondary text-uppercase">To Date</label>
                    <input type="date" class="form-control py-2 border-secondary bg-dark text-white" id="to_date" name="to_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-text text-secondary small">Set your filters above before triggering exports.</div>
            </div>
        </div>

        <!-- Export Actions Grid (Right 8 Columns) -->
        <div class="col-lg-8 mb-4">
            <div class="row">
                <!-- Attendance Report -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm p-3 h-100" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;">
                        <h6 class="text-white fw-bold mb-2 small"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Attendance Report</h6>
                        <p class="text-secondary small mb-3">Export detailed daily log records for check-ins, check-outs, and geofencing.</p>
                        <button class="btn btn-outline-primary btn-sm btn-export" data-type="attendance">
                            <i class="fa-solid fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>

                <!-- Leave Report -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm p-3 h-100" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;">
                        <h6 class="text-white fw-bold mb-2 small"><i class="fa-solid fa-plane-departure text-success me-2"></i>Leave Applications Report</h6>
                        <p class="text-secondary small mb-3">Export history of leave requests, types, status tracking, and reasons.</p>
                        <button class="btn btn-outline-success btn-sm btn-export" data-type="leaves">
                            <i class="fa-solid fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>

                <!-- Late Coming Report -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm p-3 h-100" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;">
                        <h6 class="text-white fw-bold mb-2 small"><i class="fa-solid fa-user-clock text-warning me-2"></i>Late Comers Report</h6>
                        <p class="text-secondary small mb-3">Export exceptions matching late logins exceeding the shift grace period.</p>
                        <button class="btn btn-outline-warning btn-sm btn-export" data-type="late_coming">
                            <i class="fa-solid fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>

                <!-- Employee Roster Report -->
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm p-3 h-100" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;">
                        <h6 class="text-white fw-bold mb-2 small"><i class="fa-solid fa-users text-info me-2"></i>Active Employee Roster</h6>
                        <p class="text-secondary small mb-3">Export active employees directory including codes, designations, and dates.</p>
                        <button class="btn btn-outline-info btn-sm btn-export" data-type="employees">
                            <i class="fa-solid fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var buttons = document.querySelectorAll('.btn-export');
    buttons.forEach(function(btn) {
        btn.onclick = function() {
            var type = btn.getAttribute('data-type');
            var from = document.getElementById('from_date').value;
            var to = document.getElementById('to_date').value;
            window.location.href = 'index.php?route=hrms/exportReport/' + type + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
        };
    });
});
</script>
