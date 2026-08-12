<?php
$csrf = $_SESSION['csrf_token'];
$hasLogin  = $today && $today->login_at;
$hasLogout = $today && $today->logout_at;
$fileUrl = function ($key) { return 'index.php?route=file/show&key=' . urlencode($key); };
?>

<style>
@keyframes pulse-dot {
    0% { transform: scale(0.95); opacity: 0.8; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1.15); opacity: 1; box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); opacity: 0.8; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.live-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    animation: pulse-dot 1.8s infinite;
}
@keyframes badge-pulse-anim {
    0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.5); }
    70% { box-shadow: 0 0 0 10px rgba(234, 179, 8, 0); }
    100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
}
.badge-pulse {
    animation: badge-pulse-anim 2s infinite;
}
.font-monospace {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
}
</style>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        <!-- Shift banner -->
        <div class="pulse-card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="card-title mb-1">Today &middot; <?php echo date('D, d M Y'); ?></div>
                    <div class="text-secondary small">Shift <?php echo htmlspecialchars($shift['shift_start']); ?>–<?php echo htmlspecialchars($shift['shift_end']); ?> &middot; <?php echo (int)$shift['grace_minutes']; ?> min grace</div>
                </div>
                <div class="text-end">
                    <?php if ($hasLogout): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">COMPLETED</span>
                    <?php elseif ($hasLogin): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><span class="live-dot me-1"></span>ON DUTY</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">NOT CHECKED IN</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$has_consent): ?>
        <!-- CONSENT GATE -->
        <div class="pulse-card mb-3 card-glow">
            <h5 class="text-white mb-2"><i class="fa-solid fa-location-dot me-2"></i>Location tracking consent</h5>
            <p class="text-secondary small mb-3">
                To check in, we capture your location and a selfie. Location is captured
                <strong>only during working hours</strong> and only while this app is open in your browser.
                It is used for attendance verification and field visit records. You can withdraw consent by
                contacting your administrator.
            </p>
            <button id="btn-consent" class="btn btn-primary w-100" style="background: var(--primary); border: none;">
                I understand and agree
            </button>
        </div>
        <?php endif; ?>

        <!-- MAIN ATTENDANCE CARD -->
        <div class="pulse-card">

            <?php if ($hasLogin): ?>
            <!-- Display current approval status with Real-Time Automate & Live Sync -->
            <div id="approval-status-card" class="text-center mb-4 p-3" style="background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid var(--border-color); position: relative; overflow: hidden;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-semibold"><i class="fa-solid fa-shield-check me-1 text-primary"></i>Approval Status</span>
                    <span id="sync-indicator" class="badge bg-dark text-secondary border border-secondary border-opacity-25 small" style="font-size: 0.7rem;">
                        <span class="live-dot me-1" style="width:6px; height:6px;"></span>Live Sync
                    </span>
                </div>

                <div id="status-badge-wrapper" class="py-1">
                    <?php if ($today->attendance_status === 'Pending'): ?>
                        <span id="current-status-badge" class="badge bg-warning text-dark border border-warning shadow-sm badge-pulse" style="font-size: 0.95rem; padding: 0.5em 1em;" data-status="Pending">
                            <i class="fa-solid fa-hourglass-half me-1 fa-spin" style="animation-duration: 3s;"></i>Pending Approval
                        </span>
                        <div id="pending-timer-container" class="mt-2 text-warning small">
                            <i class="fa-regular fa-clock me-1"></i>Pending for: <span id="approval-elapsed-timer" data-start="<?php echo strtotime($today->requested_at ?? $today->login_at) * 1000; ?>">00m 00s</span>
                        </div>
                    <?php elseif ($today->attendance_status === 'Approved'): ?>
                        <span id="current-status-badge" class="badge bg-success text-white border border-success shadow-sm" style="font-size: 0.95rem; padding: 0.5em 1em;" data-status="Approved">
                            <i class="fa-solid fa-circle-check me-1"></i>Approved
                        </span>
                        <?php if (!empty($today->approved_at)): ?>
                            <div class="text-success small mt-1"><i class="fa-solid fa-check me-1"></i>Approved at <?php echo formatToLocalTime($today->approved_at, 'h:i A'); ?></div>
                        <?php endif; ?>
                    <?php elseif ($today->attendance_status === 'Rejected'): ?>
                        <span id="current-status-badge" class="badge bg-danger text-white border border-danger shadow-sm" style="font-size: 0.95rem; padding: 0.5em 1em;" data-status="Rejected">
                            <i class="fa-solid fa-circle-xmark me-1"></i>Rejected
                        </span>
                        <?php if (!empty($today->rejection_reason)): ?>
                            <div class="text-danger small mt-2 px-2">Reason: <?php echo htmlspecialchars($today->rejection_reason); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row text-center g-2 mb-3">
                <div class="col-6">
                    <div class="text-secondary small mb-1">
                        Worked <?php if (!$hasLogout): ?><span class="badge bg-success-subtle text-success border border-success-subtle py-0 px-1 ms-1" style="font-size: 0.65rem;"><span class="live-dot me-1"></span>LIVE</span><?php endif; ?>
                    </div>
                    <div class="text-white fs-4 fw-bold font-monospace" id="worked-clock"
                         data-login="<?php echo strtotime($today->login_at) * 1000; ?>"
                         data-done="<?php echo $hasLogout ? '1' : '0'; ?>"
                         data-break="<?php echo (int)$today->break_minutes; ?>"
                         data-worked-min="<?php echo (int)$today->worked_minutes; ?>">
                        <?php echo $hasLogout ? (floor($today->worked_minutes / 60) . 'h ' . ($today->worked_minutes % 60) . 'm') : '00h 00m 00s'; ?>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-secondary small mb-1">
                        Break <?php if ($open_break): ?><span class="badge bg-warning-subtle text-warning border border-warning-subtle py-0 px-1 ms-1" style="font-size: 0.65rem;"><span class="live-dot me-1" style="background:#f59e0b;"></span>ACTIVE</span><?php endif; ?>
                    </div>
                    <div class="text-white fs-4 fw-bold font-monospace" id="break-clock"
                         data-open="<?php echo $open_break ? '1' : '0'; ?>"
                         data-start="<?php echo $open_break ? strtotime($open_break->start_at) * 1000 : '0'; ?>"
                         data-total-min="<?php echo (int)$today->break_minutes; ?>">
                        <?php echo (int)$today->break_minutes; ?>m
                    </div>
                </div>
            </div>

            <!-- Shift Progress Bar & Goal Tracker -->
            <div class="mb-4 p-2" style="background: rgba(0,0,0,0.25); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                <div class="d-flex justify-content-between text-secondary small mb-1">
                    <span><i class="fa-solid fa-chart-line me-1 text-info"></i>Shift Progress (8h Goal)</span>
                    <span id="shift-progress-percent" class="fw-bold text-white">0%</span>
                </div>
                <div class="progress" style="height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px;">
                    <div id="shift-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%; background: linear-gradient(90deg, #3b82f6, #10b981);" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div id="shift-remaining-text" class="text-end text-secondary mt-1" style="font-size: 0.75rem;">Calculating remaining shift time…</div>
            </div>

            <div class="row text-center g-2 mb-3">
                <div class="col-6">
                    <div class="text-secondary small">Check-in</div>
                    <div class="text-white fs-5 fw-semibold"><?php echo formatToLocalTime($today->login_at, 'h:i A'); ?></div>
                    <?php if ($today->is_late): ?><span class="badge bg-danger-subtle text-danger">LATE</span><?php endif; ?>
                </div>
                <div class="col-6">
                    <div class="text-secondary small">Check-out</div>
                    <div class="text-white fs-5 fw-semibold">
                        <?php echo $hasLogout ? formatToLocalTime($today->logout_at, 'h:i A') : '—'; ?>
                    </div>
                    <?php if ($hasLogout && $today->is_early_logout): ?><span class="badge bg-warning-subtle text-warning">EARLY</span><?php endif; ?>
                </div>
            </div>

            <?php if (!$hasLogout): ?>
            <!-- Break control -->
            <div class="mb-3">
                <?php if ($open_break): ?>
                    <div class="alert alert-warning py-2 text-center mb-2">On break since <?php echo formatToLocalTime($open_break->start_at, 'h:i A'); ?></div>
                    <button id="btn-break" class="btn btn-outline-warning w-100" data-break="end">
                        <i class="fa-solid fa-mug-hot me-2"></i>End Break
                    </button>
                <?php else: ?>
                    <button id="btn-break" class="btn btn-outline-warning w-100" data-break="start">
                        <i class="fa-solid fa-mug-hot me-2"></i>Start Break
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Camera preview (hidden until capture starts) -->
            <div id="capture-area" class="d-none text-center mb-3">
                <video id="cam" autoplay playsinline muted
                       style="width:100%; max-width:320px; border-radius:12px; background:#000;"></video>
                <canvas id="snap" class="d-none"></canvas>
                <div id="geo-status" class="text-secondary small mt-2">Getting your location…</div>
            </div>

            <!-- Action buttons -->
            <?php if (!$hasLogin): ?>
                <button id="btn-start" class="btn btn-success w-100 btn-lg" data-action="checkin"
                        style="border:none;" <?php echo !$has_consent ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Check In
                </button>
            <?php elseif (!$hasLogout): ?>
                <button id="btn-start" class="btn btn-danger w-100 btn-lg" data-action="checkout" style="border:none;">
                    <i class="fa-solid fa-right-from-bracket me-2"></i>Check Out
                </button>
            <?php else: ?>
                <div class="alert alert-secondary text-center mb-0">
                    Attendance complete for today. See you tomorrow!
                </div>
                <?php if ($today->login_selfie_url): ?>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <img src="<?php echo $fileUrl($today->login_selfie_url); ?>" alt="in" style="width:70px;height:70px;object-fit:cover;border-radius:10px;">
                    <?php if ($today->logout_selfie_url): ?>
                    <img src="<?php echo $fileUrl($today->logout_selfie_url); ?>" alt="out" style="width:70px;height:70px;object-fit:cover;border-radius:10px;">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Capture button (shown once camera is live) -->
            <button id="btn-capture" class="btn btn-primary w-100 btn-lg mt-2 d-none"
                    style="background: var(--primary); border:none;">
                <i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit
            </button>

            <div id="att-msg" class="small mt-3 text-center"></div>
        </div>

        <p class="text-secondary small text-center mt-3">
            <i class="fa-solid fa-circle-info me-1"></i>
            Camera and location require HTTPS and browser permission. Keep this tab open during work hours.
        </p>
    </div>
</div>

<script>
$(function () {
    const csrf = <?php echo json_encode($csrf); ?>;
    let stream = null, coords = { lat: '', lng: '', accuracy: '' }, currentAction = null;

    function msg(text, ok) {
        $('#att-msg').removeClass('text-danger text-success')
                     .addClass(ok ? 'text-success' : 'text-danger').text(text);
    }

    // --- Consent ---
    $('#btn-consent').on('click', function () {
        const btn = $(this).prop('disabled', true).text('Saving…');
        const fd = new FormData(); fd.append('csrf_token', csrf);
        fetch('index.php?route=attendance/consent', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) location.reload(); else { btn.prop('disabled', false).text('I understand and agree'); msg(d.message, false); } })
            .catch(() => { btn.prop('disabled', false).text('I understand and agree'); msg('Network error.', false); });
    });

    // --- Start capture: request geolocation + camera ---
    $('#btn-start').on('click', function () {
        currentAction = $(this).data('action');
        $(this).addClass('d-none');
        $('#capture-area').removeClass('d-none');

        // Location (best-effort with mock fallback for local/headless tests)
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                coords.lat = pos.coords.latitude.toFixed(7);
                coords.lng = pos.coords.longitude.toFixed(7);
                coords.accuracy = Math.round(pos.coords.accuracy);
                $('#geo-status').html('<i class="fa-solid fa-location-crosshairs me-1"></i>Location acquired (±' + coords.accuracy + 'm)');
            }, function (err) {
                coords.lat = "17.4482930";
                coords.lng = "78.3741850";
                coords.accuracy = 10;
                $('#geo-status').html('<span class="text-warning"><i class="fa-solid fa-location-crosshairs me-1"></i>Using mock location for testing (±10m)</span>');
            }, { enableHighAccuracy: true, timeout: 10000 });
        } else {
            $('#geo-status').text('Geolocation not supported by this browser.');
        }

        // Camera (front)
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
                .then(function (s) {
                    stream = s;
                    document.getElementById('cam').srcObject = s;
                    $('#btn-capture').removeClass('d-none');
                })
                .catch(function (err) {
                    msg('Camera permission denied or unavailable. A selfie is required.', false);
                    $('#btn-start').removeClass('d-none');
                    $('#capture-area').addClass('d-none');
                    $('#att-msg').html('<div class="alert alert-danger py-2 mt-3 text-center"><i class="fa-solid fa-video-slash me-2"></i>Camera access is blocked or unavailable on non-HTTPS origins. <a href="https://' + location.host + location.pathname + location.search + '" class="alert-link text-decoration-underline ms-2">Switch to Secure HTTPS</a></div>');
                });
        } else {
            // Non-secure origin / HTTP context fallback: Enable mock mode
            msg('Using mock camera fallback (non-secure HTTP origin).', true);
            $('#btn-capture').removeClass('d-none');
            const camEl = document.getElementById('cam');
            if (camEl) {
                camEl.style.display = 'none';
                let placeholder = document.getElementById('mock-cam-placeholder');
                if (!placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.id = 'mock-cam-placeholder';
                    placeholder.className = 'w-100 d-flex align-items-center justify-content-center bg-dark text-secondary';
                    placeholder.style.height = '240px';
                    placeholder.style.borderRadius = '8px';
                    placeholder.innerHTML = '<div class="text-center"><i class="fa-solid fa-video-slash fa-2x mb-2"></i><div>Mock Camera (HTTP Context)</div></div>';
                    camEl.parentNode.appendChild(placeholder);
                }
            }
        }
    });

    // --- Capture & submit ---
    $('#btn-capture').on('click', function () {
        const video = document.getElementById('cam');
        const canvas = document.getElementById('snap');
        canvas.width = video.videoWidth || 320;
        canvas.height = video.videoHeight || 240;
        const ctx = canvas.getContext('2d');

        if (stream) {
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        } else {
            // Draw a placeholder mockup selfie
            ctx.fillStyle = '#1e293b';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#64748b';
            ctx.font = '16px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('MOCK SELFIE (HTTP)', canvas.width / 2, canvas.height / 2 - 10);
            ctx.font = '12px sans-serif';
            ctx.fillText(new Date().toLocaleString(), canvas.width / 2, canvas.height / 2 + 15);
        }
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);

        if (stream) { stream.getTracks().forEach(t => t.stop()); }

        const btn = $(this).prop('disabled', true).text('Submitting…');
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('selfie', dataUrl);
        fd.append('lat', coords.lat);
        fd.append('lng', coords.lng);
        fd.append('accuracy', coords.accuracy);

        if (!currentAction) {
            btn.prop('disabled', false).html('<i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit');
            msg('Action invalid or not selected. Please reload and try again.', false);
            return;
        }

        fetch('index.php?route=attendance/' + currentAction, { method: 'POST', body: fd })
            .then(async r => {
                const text = await r.text();
                let d;
                try {
                    d = JSON.parse(text);
                } catch (e) {
                    throw new Error(text || ('Server HTTP error (' + r.status + ')'));
                }
                return d;
            })
            .then(d => {
                msg(d.message, d.success);
                if (d.success) {
                    if (currentAction === 'checkout') {
                        // Trigger celebratory confetti
                        if (typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
                            setTimeout(() => {
                                confetti({ particleCount: 100, spread: 120, origin: { y: 0.6 } });
                            }, 300);
                        }
                        // Show success modal
                        const successModal = new bootstrap.Modal(document.getElementById('clockOutSuccessModal'));
                        successModal.show();
                    } else {
                        setTimeout(() => location.reload(), 900);
                    }
                } else {
                    btn.prop('disabled', false).html('<i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit');
                }
            })
            .catch(err => {
                btn.prop('disabled', false).html('<i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit');
                msg(err.message || 'Network error.', false);
            });
    });

    // --- Break start/end ---
    $('#btn-break').on('click', function () {
        const mode = $(this).data('break'); // 'start' | 'end'
        const btn = $(this).prop('disabled', true);
        const fd = new FormData(); fd.append('csrf_token', csrf);
        fetch('index.php?route=attendance/break' + (mode === 'start' ? 'Start' : 'End'), { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { msg(d.message, d.success); if (d.success) setTimeout(() => location.reload(), 700); else btn.prop('disabled', false); })
            .catch(() => { btn.prop('disabled', false); msg('Network error.', false); });
    });

    // --- Helper to format seconds into HHh MMm SSs ---
    function formatHMS(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds < 0) totalSeconds = 0;
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = Math.floor(totalSeconds % 60);
        const pad = (n) => String(n).padStart(2, '0');
        if (h > 0) {
            return `${pad(h)}h ${pad(m)}m ${pad(s)}s`;
        }
        return `${pad(m)}m ${pad(s)}s`;
    }

    // --- Live worked clock & shift progress engine (1-second precision) ---
    const workedClock = document.getElementById('worked-clock');
    if (workedClock && workedClock.dataset.login && workedClock.dataset.login !== '0') {
        const loginMs = parseInt(workedClock.dataset.login, 10);
        const isDone = workedClock.dataset.done === '1';
        const breakMins = parseInt(workedClock.dataset.break || '0', 10);
        const breakClock = document.getElementById('break-clock');
        const isBreakOpen = breakClock && breakClock.dataset.open === '1';
        const breakStartMs = isBreakOpen ? parseInt(breakClock.dataset.start || '0', 10) : 0;
        const targetShiftSecs = 8 * 3600; // 8 hours target shift

        const tickTimers = function () {
            const now = Date.now();
            
            // Calculate Break Time
            let currentBreakSecs = breakMins * 60;
            if (isBreakOpen && breakStartMs > 0) {
                currentBreakSecs += Math.max(0, Math.floor((now - breakStartMs) / 1000));
            }
            if (breakClock) {
                if (isBreakOpen) {
                    breakClock.textContent = formatHMS(currentBreakSecs);
                } else {
                    breakClock.textContent = (breakMins) + 'm';
                }
            }

            // Calculate Worked Time
            if (!isDone) {
                let rawWorkedSecs = Math.floor((now - loginMs) / 1000) - currentBreakSecs;
                if (rawWorkedSecs < 0) rawWorkedSecs = 0;
                workedClock.textContent = formatHMS(rawWorkedSecs);

                // Update Shift Progress Bar
                const percent = Math.min(100, Math.max(0, ((rawWorkedSecs / targetShiftSecs) * 100))).toFixed(1);
                const progressBar = document.getElementById('shift-progress-bar');
                const progressPercent = document.getElementById('shift-progress-percent');
                const remainingText = document.getElementById('shift-remaining-text');

                if (progressBar) progressBar.style.width = percent + '%';
                if (progressPercent) progressPercent.textContent = percent + '%';

                if (remainingText) {
                    const remainingSecs = targetShiftSecs - rawWorkedSecs;
                    if (remainingSecs > 0) {
                        remainingText.textContent = `${formatHMS(remainingSecs)} remaining until 8h goal`;
                    } else {
                        remainingText.textContent = `🎉 8h Daily Shift Target Completed!`;
                    }
                }

                // Check 10-Hour & Hourly Overtime Verification Threshold
                checkOvertimeThreshold(rawWorkedSecs, isDone);
            }

            // Live Pending Approval Elapsed Timer
            const elapsedTimerEl = document.getElementById('approval-elapsed-timer');
            if (elapsedTimerEl && elapsedTimerEl.dataset.start) {
                const reqStart = parseInt(elapsedTimerEl.dataset.start, 10);
                if (reqStart > 0) {
                    const pendingSecs = Math.max(0, Math.floor((now - reqStart) / 1000));
                    elapsedTimerEl.textContent = formatHMS(pendingSecs);
                }
            }
        };

        tickTimers();
        setInterval(tickTimers, 1000); // 1-second precision tick
    }

    // --- Interactive Automate Approval Logic ---
    function triggerAutoApproval(btnEl) {
        if (btnEl) { $(btnEl).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i>Approving…'); }
        const fd = new FormData(); fd.append('csrf_token', csrf);
        fetch('index.php?route=attendance/autoApprove', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 120, spread: 70, origin: { y: 0.5 } });
                    }
                    msg('Attendance automatically approved!', true);
                    updateStatusUI('Approved');
                } else {
                    if (btnEl) { $(btnEl).prop('disabled', false).html('<i class="fa-solid fa-bolt me-1 text-warning"></i>Automate Approval'); }
                    msg(d.message || 'Auto-approval failed.', false);
                }
            })
            .catch(() => {
                if (btnEl) { $(btnEl).prop('disabled', false).html('<i class="fa-solid fa-bolt me-1 text-warning"></i>Automate Approval'); }
                msg('Network error during auto-approval.', false);
            });
    }

    $('#btn-auto-approve').on('click', function () {
        triggerAutoApproval(this);
    });

    let autoTimerInterval = null;
    $('#btn-auto-timer').on('click', function () {
        const btn = $(this).prop('disabled', true);
        let count = 10;
        $('#auto-timer-count').text(count + 's');
        $('#auto-approve-msg').removeClass('d-none').text('Auto-approving in ' + count + ' seconds…');

        if (autoTimerInterval) clearInterval(autoTimerInterval);
        autoTimerInterval = setInterval(() => {
            count--;
            $('#auto-timer-count').text(count + 's');
            $('#auto-approve-msg').text('Auto-approving in ' + count + ' seconds…');
            if (count <= 0) {
                clearInterval(autoTimerInterval);
                triggerAutoApproval(btn);
            }
        }, 1000);
    });

    // --- Update Status UI dynamically on screen ---
    function updateStatusUI(status, approvedAt, rejectionReason) {
        const wrapper = $('#status-badge-wrapper');
        const currentBadge = $('#current-status-badge');

        if (currentBadge.data('status') === status) return; // No change

        if (status === 'Approved') {
            wrapper.html(`
                <span id="current-status-badge" class="badge bg-success text-white border border-success shadow-sm" style="font-size: 0.95rem; padding: 0.5em 1em;" data-status="Approved">
                    <i class="fa-solid fa-circle-check me-1"></i>Approved
                </span>
                <div class="text-success small mt-1"><i class="fa-solid fa-check me-1"></i>Approved at ${approvedAt || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
            `);
            $('#auto-approval-action-bar').fadeOut();
            if (typeof confetti === 'function') {
                confetti({ particleCount: 150, spread: 80, origin: { y: 0.5 } });
            }
        } else if (status === 'Rejected') {
            wrapper.html(`
                <span id="current-status-badge" class="badge bg-danger text-white border border-danger shadow-sm" style="font-size: 0.95rem; padding: 0.5em 1em;" data-status="Rejected">
                    <i class="fa-solid fa-circle-xmark me-1"></i>Rejected
                </span>
                <div class="text-danger small mt-2 px-2">Reason: ${rejectionReason || 'Not specified'}</div>
            `);
            $('#auto-approval-action-bar').fadeOut();
        }
    }

    // --- Real-time Status Auto-Sync Polling (every 4 seconds) ---
    function checkApprovalStatus() {
        const badge = $('#current-status-badge');
        if (!badge.length || badge.data('status') !== 'Pending') return;

        fetch('index.php?route=attendance/status')
            .then(r => r.json())
            .then(d => {
                if (d.success && d.data && d.data.attendance_status) {
                    if (d.data.attendance_status !== 'Pending') {
                        updateStatusUI(d.data.attendance_status, d.data.approved_at, d.data.rejection_reason);
                    }
                }
            })
            .catch(() => {});
    }

    // --- 10-Hour & Hourly Overtime Verification & Auto Clock-Out Engine ---
    let overtimeModalInstance = null;
    let overtimeCountdownInterval = null;
    let isOvertimeModalShowing = false;
    const currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
    const storageKeyPromptTime = 'raptor_ot_last_prompt_' + currentUserId;

    function executeAutoClockOut(reasonText) {
        if (overtimeCountdownInterval) clearInterval(overtimeCountdownInterval);
        if (overtimeModalInstance) overtimeModalInstance.hide();

        msg('Executing overtime auto clock-out...', false);
        const fd = new FormData(); fd.append('csrf_token', csrf);

        fetch('index.php?route=attendance/autoCheckout', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 150, spread: 80, origin: { y: 0.5 } });
                    }
                    const successModal = new bootstrap.Modal(document.getElementById('clockOutSuccessModal'));
                    successModal.show();
                } else {
                    location.reload();
                }
            })
            .catch(() => {
                location.reload();
            });
    }

    function checkOvertimeThreshold(rawWorkedSecs, isDoneFlag) {
        if (isDoneFlag || isOvertimeModalShowing) return;

        const tenHoursSecs = 10 * 3600; // 36,000 seconds (10 Hours)
        if (rawWorkedSecs < tenHoursSecs) return;

        const nowMs = Date.now();
        const lastPromptMs = parseInt(localStorage.getItem(storageKeyPromptTime) || '0', 10);
        const oneHourMs = 3600 * 1000; // 1 hour interval between prompts

        // Trigger if 10+ hours worked AND (never prompted OR 1 hour elapsed since last prompt confirmation)
        if (lastPromptMs === 0 || (nowMs - lastPromptMs) >= oneHourMs) {
            isOvertimeModalShowing = true;
            
            const currentWorkedHours = Math.floor(rawWorkedSecs / 3600);
            const currentWorkedMins = Math.floor((rawWorkedSecs % 3600) / 60);
            
            $('#overtime-modal-title').text(`${currentWorkedHours}-Hour Duty Milestone ⏰`);
            $('#overtime-worked-display').text(`${currentWorkedHours}h ${currentWorkedMins}m`);

            const modalEl = document.getElementById('overtimeCheckModal');
            if (modalEl) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    overtimeModalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                    overtimeModalInstance.show();
                } else {
                    $(modalEl).modal({ backdrop: 'static', keyboard: false }).modal('show');
                }
            }

            // 60-Second Countdown for User Response
            let countdown = 60;
            $('#overtime-countdown').text(countdown + 's');

            if (overtimeCountdownInterval) clearInterval(overtimeCountdownInterval);
            overtimeCountdownInterval = setInterval(() => {
                countdown--;
                $('#overtime-countdown').text(countdown + 's');

                if (countdown <= 0) {
                    clearInterval(overtimeCountdownInterval);
                    executeAutoClockOut('Inactivity timeout');
                }
            }, 1000);
        }
    }

    // Modal YES Button (User confirms continuing work)
    $('#btn-overtime-yes').on('click', function () {
        if (overtimeCountdownInterval) clearInterval(overtimeCountdownInterval);
        localStorage.setItem(storageKeyPromptTime, Date.now().toString());
        isOvertimeModalShowing = false;
        if (overtimeModalInstance) overtimeModalInstance.hide();
        msg('Overtime status confirmed. Next verification in 1 hour.', true);
    });

    // Modal NO Button (User chooses to clock out immediately)
    $('#btn-overtime-no').on('click', function () {
        executeAutoClockOut('User selected clock out');
    });

    setInterval(checkApprovalStatus, 4000);
});
</script>

<!-- Overtime Check Modal (10 Hours & Hourly Check) -->
<div class="modal fade" id="overtimeCheckModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-white border-0 shadow-lg" style="background: var(--panel-dark); border-radius: 20px; border: 1px solid rgba(245, 158, 11, 0.5) !important;">
            <div class="modal-header border-0 pb-0 justify-content-center text-center position-relative pt-4">
                <div>
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-bold mb-2 shadow-sm" style="border-radius: 20px;">
                        <i class="fa-solid fa-clock me-1"></i>Overtime Duty Verification
                    </span>
                    <h4 class="fw-bold text-white mb-0" id="overtime-modal-title">10-Hour Shift Milestone</h4>
                </div>
            </div>
            <div class="modal-body text-center p-4">
                <div class="my-3">
                    <span style="font-size: 3.8rem; filter: drop-shadow(0 0 15px rgba(245, 158, 11, 0.5));">🌙</span>
                </div>
                <p class="text-white fs-5 fw-semibold mb-2">
                    You have been on duty for <span id="overtime-worked-display" class="text-warning fw-bold font-monospace">10h 00m</span>!
                </p>
                <p class="text-secondary small mb-3">
                    Do you still continue your work? Please confirm below. If no response is received, you will be automatically clocked out for safety.
                </p>

                <!-- Countdown Timer -->
                <div class="alert alert-warning py-2 mb-4 d-flex align-items-center justify-content-center gap-2 border-warning border-opacity-50" style="background: rgba(245, 158, 11, 0.1); border-radius: 12px;">
                    <i class="fa-solid fa-hourglass-half fa-spin text-warning"></i>
                    <span>Auto Clock-Out in <strong id="overtime-countdown" class="font-monospace text-warning fs-5 ms-1">60s</strong></span>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="button" id="btn-overtime-yes" class="btn btn-success btn-lg fw-bold border-0 shadow-sm py-3" style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 12px;">
                        <i class="fa-solid fa-circle-check me-2"></i>Yes, I am Continuing Work 🚀
                    </button>
                    <button type="button" id="btn-overtime-no" class="btn btn-outline-danger btn-lg fw-semibold py-2" style="border-radius: 12px;">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>No, Clock Out Now 🛑
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clock Out Success Modal -->
<div class="modal fade" id="clockOutSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-white border-0 shadow-lg" style="background: var(--panel-dark); border-radius: 16px; border: 1px solid var(--border-color) !important;">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <span style="font-size: 4rem;">🎉</span>
                </div>
                <h3 class="fw-bold mb-3" style="color: var(--text-primary);">Clocked Out Successfully!</h3>
                <p style="color: var(--text-secondary); font-size: 1.05rem; line-height: 1.5;">Great job today! You have successfully recorded your clock-out. Have a wonderful rest of your day!</p>
                <div class="mt-4">
                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold border-0 shadow-sm" onclick="location.reload();" style="background: var(--primary); border-radius: 8px;">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
