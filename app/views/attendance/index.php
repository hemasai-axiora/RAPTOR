<?php
$csrf = $_SESSION['csrf_token'];
$hasLogin  = $today && $today->login_at;
$hasLogout = $today && $today->logout_at;
$fileUrl = function ($key) { return 'index.php?route=file/show&key=' . urlencode($key); };
?>

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
                        <span class="badge bg-success-subtle text-success border border-success-subtle">ON DUTY</span>
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
            <div class="row text-center g-2 mb-3">
                <div class="col-6">
                    <div class="text-secondary small">Check-in</div>
                    <div class="text-white fs-5 fw-semibold"><?php echo date('h:i A', strtotime($today->login_at)); ?></div>
                    <?php if ($today->is_late): ?><span class="badge bg-danger-subtle text-danger">LATE</span><?php endif; ?>
                </div>
                <div class="col-6">
                    <div class="text-secondary small">Check-out</div>
                    <div class="text-white fs-5 fw-semibold">
                        <?php echo $hasLogout ? date('h:i A', strtotime($today->logout_at)) : '—'; ?>
                    </div>
                    <?php if ($hasLogout && $today->is_early_logout): ?><span class="badge bg-warning-subtle text-warning">EARLY</span><?php endif; ?>
                </div>
            </div>

            <div class="row text-center g-2 mb-3">
                <div class="col-6">
                    <div class="text-secondary small">Worked</div>
                    <div class="text-white fs-4 fw-bold" id="worked-clock"
                         data-login="<?php echo strtotime($today->login_at) * 1000; ?>"
                         data-done="<?php echo $hasLogout ? '1' : '0'; ?>"
                         data-break="<?php echo (int)$today->break_minutes; ?>">
                        <?php echo $hasLogout ? (floor($today->worked_minutes / 60) . 'h ' . ($today->worked_minutes % 60) . 'm') : '0h 0m'; ?>
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-secondary small">Break</div>
                    <div class="text-white fs-4 fw-bold"><?php echo (int)$today->break_minutes; ?>m</div>
                </div>
            </div>

            <?php if (!$hasLogout): ?>
            <!-- Break control -->
            <div class="mb-3">
                <?php if ($open_break): ?>
                    <div class="alert alert-warning py-2 text-center mb-2">On break since <?php echo date('h:i A', strtotime($open_break->start_at)); ?></div>
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

        // Location (best-effort)
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (pos) {
                coords.lat = pos.coords.latitude.toFixed(7);
                coords.lng = pos.coords.longitude.toFixed(7);
                coords.accuracy = Math.round(pos.coords.accuracy);
                $('#geo-status').html('<i class="fa-solid fa-location-crosshairs me-1"></i>Location acquired (±' + coords.accuracy + 'm)');
            }, function (err) {
                $('#geo-status').html('<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Location access blocked. Please enable GPS and allow location access. <a href="#" onclick="location.reload(); return false;" class="badge bg-danger text-white ms-2">Retry</a></span>');
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
                    $('#att-msg').html('<div class="alert alert-danger py-2 mt-3 text-center"><i class="fa-solid fa-video-slash me-2"></i>Camera access is blocked. Please check browser settings and allow camera access. <a href="#" onclick="location.reload(); return false;" class="alert-link text-decoration-underline ms-2">Reload &amp; Retry</a></div>');
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

        fetch('index.php?route=attendance/' + currentAction, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                msg(d.message, d.success);
                if (d.success) { setTimeout(() => location.reload(), 900); }
                else { btn.prop('disabled', false).html('<i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit'); }
            })
            .catch(() => { btn.prop('disabled', false).html('<i class="fa-solid fa-camera me-2"></i>Capture &amp; Submit'); msg('Network error.', false); });
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

    // --- Live worked clock while on duty ---
    const clock = document.getElementById('worked-clock');
    if (clock && clock.dataset.done === '0') {
        const loginMs = parseInt(clock.dataset.login, 10);
        const brk = parseInt(clock.dataset.break || '0', 10);
        const tick = function () {
            let mins = Math.floor((Date.now() - loginMs) / 60000) - brk;
            if (mins < 0) mins = 0;
            clock.textContent = Math.floor(mins / 60) + 'h ' + (mins % 60) + 'm';
        };
        tick();
        setInterval(tick, 30000);
    }
});
</script>
