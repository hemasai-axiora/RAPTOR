<?php if (!empty($_SESSION['meeting_error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['meeting_error']); unset($_SESSION['meeting_error']); ?></div>
<?php endif; ?>

<div class="pulse-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="text-white mb-1">Meetings & Demos</h4>
            <div class="text-secondary" style="font-size:0.9rem;">Schedule visits, check in/out with GPS and selfie, then record outcome and feedback.</div>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMeetingModal" style="background: var(--primary); border: none;">
            <i class="fa-solid fa-calendar-plus me-2"></i>Schedule
        </button>
    </div>

    <form method="GET" action="index.php" class="row g-3 mb-4">
        <input type="hidden" name="route" value="meetings/index">
        <?php if (!Policy::isEmployee()): ?>
            <div class="col-md-3">
                <label class="form-label text-secondary">Owner</label>
                <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                    <option value="">All visible</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php echo (string) $filters['assigned_to_user_id'] === (string) $user->user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label text-secondary">Type</label>
            <select name="type" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($types as $type): ?><option value="<?php echo $type; ?>" <?php echo $filters['type'] === $type ? 'selected' : ''; ?>><?php echo strtoupper($type); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">Status</label>
            <select name="status" class="form-select bg-dark border-secondary text-white">
                <option value="">All</option>
                <?php foreach ($statuses as $status): ?><option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $status)); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">From</label>
            <input type="date" name="date_from" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_from'], 0, 10)); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label text-secondary">To</label>
            <input type="date" name="date_to" class="form-control bg-dark border-secondary text-white" value="<?php echo htmlspecialchars(substr($filters['date_to'], 0, 10)); ?>">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-light w-100" type="submit"><i class="fa-solid fa-filter"></i></button>
        </div>
    </form>

    <div class="row g-3">
        <?php if (empty($meetings)): ?>
            <div class="col-12 text-center py-4 text-secondary">No meetings or demos found.</div>
        <?php endif; ?>

        <?php foreach ($meetings as $meeting): ?>
            <?php
                $tone = [
                    'scheduled' => 'primary',
                    'checked_in' => 'warning',
                    'completed' => 'success',
                    'cancelled' => 'secondary',
                ][$meeting->status] ?? 'secondary';
            ?>
            <div class="col-lg-6 col-xl-4">
                <div class="pulse-card h-100 p-3" style="border-radius:12px;">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo strtoupper($meeting->type); ?></span>
                        <span class="badge bg-<?php echo $tone; ?>-subtle text-<?php echo $tone; ?> border border-<?php echo $tone; ?>-subtle"><?php echo strtoupper(str_replace('_', ' ', $meeting->status)); ?></span>
                    </div>
                    <h5 class="text-white mb-1" style="font-size:1rem;"><?php echo htmlspecialchars($meeting->title); ?></h5>
                    <div class="text-secondary small mb-2">
                        <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($meeting->scheduled_start))); ?>
                        <?php if ($meeting->scheduled_end): ?> - <?php echo htmlspecialchars(date('h:i A', strtotime($meeting->scheduled_end))); ?><?php endif; ?>
                    </div>
                    <div class="text-secondary small mb-2"><i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($meeting->location ?: 'No location'); ?></div>
                    <div class="text-secondary small mb-3"><i class="fa-regular fa-user me-1"></i><?php echo htmlspecialchars($meeting->assignee_name); ?></div>
                    <?php if ($meeting->lead_id): ?>
                        <div class="mt-2 mb-2">
                            <a class="text-white small text-decoration-none" href="index.php?route=leads/view/<?php echo $meeting->lead_id; ?>">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">
                                    <i class="fa-solid fa-bullseye me-1"></i><?php echo htmlspecialchars($meeting->lead_code ?: 'LEAD'); ?>
                                </span>
                                <?php echo htmlspecialchars(trim(($meeting->lead_first_name ?? '') . ' ' . ($meeting->lead_last_name ?? '')) ?: $meeting->lead_company_name); ?>
                            </a>
                        </div>
                    <?php elseif (!empty($meeting->customer_id)): ?>
                        <div class="mt-2 mb-2">
                            <a class="text-white small text-decoration-none" href="index.php?route=customers/detail/<?php echo $meeting->customer_id; ?>">
                                <span class="badge bg-success-subtle text-success border border-success-subtle me-1">
                                    <i class="fa-solid fa-building me-1"></i><?php echo htmlspecialchars($meeting->customer_code ?: 'CUST'); ?>
                                </span>
                                <?php echo htmlspecialchars($meeting->customer_company_name ?: $meeting->customer_first_name); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ((int) $meeting->assigned_to_user_id === (int) $_SESSION['user_id'] && !in_array($meeting->status, ['completed', 'cancelled'], true)): ?>
                        <form class="meeting-check-form mt-3" action="index.php?route=meetings/check/<?php echo $meeting->meeting_id; ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="hidden" name="type" value="<?php echo $meeting->status === 'checked_in' ? 'out' : 'in'; ?>">
                            <input type="hidden" name="lat">
                            <input type="hidden" name="lng">
                            <input type="hidden" name="accuracy_m">
                            <label class="form-label text-secondary small">Selfie Proof</label>
                            <input type="file" name="selfie" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" accept="image/*" capture="user" required>
                            <button class="btn btn-outline-warning btn-sm w-100">
                                <i class="fa-solid fa-location-crosshairs me-1"></i><?php echo $meeting->status === 'checked_in' ? 'Check Out' : 'Check In'; ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($meeting->status !== 'completed' && $meeting->status !== 'cancelled'): ?>
                        <form class="mt-3" action="index.php?route=meetings/complete/<?php echo $meeting->meeting_id; ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <input type="text" name="outcome" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" placeholder="Outcome">
                            <textarea name="client_feedback" class="form-control form-control-sm bg-dark border-secondary text-white mb-2" rows="2" placeholder="Client feedback"></textarea>
                            <input type="datetime-local" name="next_follow_up_at" class="form-control form-control-sm bg-dark border-secondary text-white mb-2">
                            <button class="btn btn-outline-success btn-sm w-100">Complete Meeting</button>
                        </form>
                    <?php elseif ($meeting->outcome || $meeting->client_feedback): ?>
                        <div class="border-top border-secondary border-opacity-10 mt-3 pt-2 text-secondary small">
                            <div class="text-white"><?php echo htmlspecialchars($meeting->outcome ?: 'Completed'); ?></div>
                            <?php echo htmlspecialchars($meeting->client_feedback ?: ''); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($meeting->status !== 'completed' && $meeting->status !== 'cancelled'): ?>
                        <form class="mt-2" action="index.php?route=meetings/cancel/<?php echo $meeting->meeting_id; ?>" method="POST" onsubmit="return confirm('Cancel this meeting/demo?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                            <button class="btn btn-outline-secondary btn-sm w-100">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="addMeetingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Schedule Meeting / Demo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php?route=meetings/add" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-secondary font-weight-bold">Link Meeting To *</label>
                        <div class="btn-group w-100 mb-2" role="group">
                            <input type="radio" class="btn-check" name="link_type" id="link_type_lead" value="lead" checked onchange="toggleMeetingLinkType('lead')">
                            <label class="btn btn-outline-primary btn-sm" for="link_type_lead"><i class="fa-solid fa-bullseye me-1"></i> Lead</label>

                            <input type="radio" class="btn-check" name="link_type" id="link_type_customer" value="customer" onchange="toggleMeetingLinkType('customer')">
                            <label class="btn btn-outline-primary btn-sm" for="link_type_customer"><i class="fa-solid fa-building me-1"></i> Customer</label>

                            <input type="radio" class="btn-check" name="link_type" id="link_type_none" value="none" onchange="toggleMeetingLinkType('none')">
                            <label class="btn btn-outline-secondary btn-sm" for="link_type_none"><i class="fa-solid fa-ban me-1"></i> None</label>
                        </div>

                        <!-- Lead Select -->
                        <div id="lead-select-container">
                            <select name="lead_id" id="meeting_lead_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- Select Linked Lead --</option>
                                <?php foreach ($leads as $lead): ?>
                                    <?php 
                                        $leadName = trim($lead->first_name . ' ' . ($lead->last_name ?? ''));
                                        $leadComp = $lead->lead_company_name ?: $lead->client_company_name ?: 'Individual';
                                    ?>
                                    <option value="<?php echo $lead->lead_id; ?>">
                                        <?php echo htmlspecialchars(($lead->lead_code ? $lead->lead_code . ' - ' : '') . $leadName . ' (' . $leadComp . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Customer Select -->
                        <div id="customer-select-container" class="d-none">
                            <select name="customer_id" id="meeting_customer_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- Select Linked Customer --</option>
                                <?php if (!empty($customers)): ?>
                                    <?php foreach ($customers as $cust): ?>
                                        <?php 
                                            $custName = $cust->company_name ?: ($cust->first_name . ' ' . ($cust->last_name ?? ''));
                                        ?>
                                        <option value="<?php echo $cust->customer_id; ?>">
                                            <?php echo htmlspecialchars(($cust->customer_code ? $cust->customer_code . ' - ' : '') . $custName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <?php if (!Policy::isEmployee()): ?>
                        <div class="mb-3">
                            <label class="form-label text-secondary">Owner</label>
                            <select name="assigned_to_user_id" class="form-select bg-dark border-secondary text-white">
                                <?php foreach ($users as $user): ?><option value="<?php echo $user->user_id; ?>"><?php echo htmlspecialchars($user->name); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label text-secondary">Type</label>
                            <select name="type" class="form-select bg-dark border-secondary text-white">
                                <option value="meeting">MEETING</option>
                                <option value="demo">DEMO</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label text-secondary">Title</label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-secondary">Start Date & Time (Future Only) *</label>
                            <input type="datetime-local" name="scheduled_start" min="<?php echo date('Y-m-d\TH:i'); ?>" class="form-control bg-dark border-secondary text-white" required>
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label text-secondary">Additional Attendees (Optional comma-separated email list)</label>
                            <input type="text" name="attendees_list" class="form-control bg-dark border-secondary text-white" placeholder="e.g. contact1@client.com, contact2@client.com">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label text-secondary">Location</label>
                        <input type="text" name="location" class="form-control bg-dark border-secondary text-white">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" style="background: var(--primary); border: none;">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleMeetingLinkType(type) {
    if (type === 'lead') {
        $('#lead-select-container').removeClass('d-none');
        $('#customer-select-container').addClass('d-none');
        $('#meeting_customer_id').val('');
    } else if (type === 'customer') {
        $('#customer-select-container').removeClass('d-none');
        $('#lead-select-container').addClass('d-none');
        $('#meeting_lead_id').val('');
    } else {
        $('#lead-select-container').addClass('d-none');
        $('#customer-select-container').addClass('d-none');
        $('#meeting_lead_id').val('');
        $('#meeting_customer_id').val('');
    }
}

$(function() {
    $('.meeting-check-form').on('submit', function(e) {
        var form = this;
        if (form.dataset.geoReady === '1' || !navigator.geolocation) return true;
        e.preventDefault();
        navigator.geolocation.getCurrentPosition(function(pos) {
            form.querySelector('[name="lat"]').value = pos.coords.latitude.toFixed(7);
            form.querySelector('[name="lng"]').value = pos.coords.longitude.toFixed(7);
            form.querySelector('[name="accuracy_m"]').value = Math.round(pos.coords.accuracy || 0);
            form.dataset.geoReady = '1';
            form.submit();
        }, function() {
            form.dataset.geoReady = '1';
            form.submit();
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
    });
});
</script>
