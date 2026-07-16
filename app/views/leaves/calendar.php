<div class="pulse-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-white mb-0"><i class="fa-solid fa-calendar-check text-primary me-2"></i>Holiday & Leave Calendar</h4>
        <a href="index.php?route=leaves/index" class="btn btn-outline-light btn-sm px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Leaves
        </a>
    </div>

    <div class="row">
        <!-- Leaves Calendar (Left Column) -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-plane-departure text-success me-2"></i>Approved Leaves List</h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border-secondary mb-0" id="approved-leaves-table">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['leaves'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">No approved leaves scheduled.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data['leaves'] as $l): ?>
                                    <?php 
                                        $days = $l->half_day ? 0.5 : (int)round((strtotime($l->to_date) - strtotime($l->from_date)) / 86400) + 1;
                                    ?>
                                    <tr class="small" style="border-bottom: 1px solid var(--border-color);">
                                        <td class="text-white fw-bold"><?php echo htmlspecialchars($l->employee_name); ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <?php echo htmlspecialchars($l->leave_type); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($l->from_date); ?></td>
                                        <td><?php echo htmlspecialchars($l->to_date); ?></td>
                                        <td class="text-white fw-bold"><?php echo $days; ?> Days</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Holiday List (Right Column) -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm p-4" style="background: #1a1d27; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px;">
                <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-holly-berry text-warning me-2"></i>Upcoming Holidays</h5>
                <div class="d-flex flex-column gap-3">
                    <?php if (empty($data['holidays'])): ?>
                        <div class="text-center text-secondary py-3">No holidays listed.</div>
                    <?php else: ?>
                        <?php foreach ($data['holidays'] as $h): ?>
                            <?php 
                                $isPast = strtotime($h->holiday_date) < time();
                                $bgClass = $isPast ? 'bg-secondary bg-opacity-10' : 'bg-warning bg-opacity-15';
                                $borderClass = $isPast ? 'border-secondary' : 'border-warning';
                                $textClass = $isPast ? 'text-secondary' : 'text-warning';
                            ?>
                            <div class="p-3 rounded border <?php echo $borderClass; ?> <?php echo $bgClass; ?> d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white fw-bold mb-0 small"><?php echo htmlspecialchars($h->holiday_name); ?></h6>
                                    <small class="text-secondary"><?php echo date('l', strtotime($h->holiday_date)); ?></small>
                                </div>
                                <span class="<?php echo $textClass; ?> fw-bold small"><?php echo date('M d, Y', strtotime($h->holiday_date)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($('#approved-leaves-table tbody tr').length > 1 || !$('#approved-leaves-table tbody tr td').hasClass('text-center')) {
        $('#approved-leaves-table').DataTable({
            "pageLength": 10,
            "lengthChange": false,
            "info": false,
            "searching": true,
            "language": {
                "search": "Filter Leaves:"
            }
        });
    }
});
</script>
