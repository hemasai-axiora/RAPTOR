<div class="pulse-card mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
        <div>
            <h4 class="text-white mb-1">Notification Center</h4>
            <div class="text-secondary small"><?php echo (int) $unread_count; ?> unread alert(s)</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!empty($web_push_enabled) && !empty($vapid_public_key)): ?>
                <button class="btn btn-outline-info" id="enable-push-alerts" type="button">
                    <i class="fa-solid fa-satellite-dish me-2"></i>Enable Push
                </button>
            <?php endif; ?>
            <?php if ($unread_count > 0): ?>
                <form method="post" action="index.php?route=notifications/readAll">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button class="btn btn-outline-light"><i class="fa-solid fa-check-double me-2"></i>Mark All Read</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if (empty($notifications)): ?>
        <div class="col-12">
            <div class="pulse-card text-center text-secondary py-5">No notifications yet.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($notifications as $notification): ?>
        <?php
            $severityClass = $notification->severity === 'critical' ? 'danger' : ($notification->severity === 'warning' ? 'warning' : 'info');
        ?>
        <div class="col-12">
            <div class="pulse-card <?php echo !$notification->is_read ? 'border border-' . $severityClass . ' border-opacity-25' : ''; ?>">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-<?php echo $severityClass; ?>"><?php echo htmlspecialchars($notification->severity); ?></span>
                            <span class="text-secondary small"><?php echo htmlspecialchars($notification->category ?? $notification->type); ?></span>
                            <?php if (!$notification->is_read): ?><span class="badge bg-primary">Unread</span><?php endif; ?>
                        </div>
                        <h5 class="text-white mb-1"><?php echo htmlspecialchars($notification->title); ?></h5>
                        <div class="text-secondary"><?php echo htmlspecialchars($notification->message); ?></div>
                        <div class="text-secondary small mt-2"><?php echo htmlspecialchars($notification->created_at); ?></div>
                    </div>
                    <div class="d-flex gap-2 align-items-start">
                        <?php if (!empty($notification->action_url)): ?>
                            <a class="btn btn-outline-light btn-sm" href="<?php echo htmlspecialchars($notification->action_url); ?>">Open</a>
                        <?php endif; ?>
                        <?php if (!$notification->is_read): ?>
                            <form method="post" action="index.php?route=notifications/read/<?php echo (int) $notification->notification_id; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button class="btn btn-primary btn-sm">Mark Read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($web_push_enabled) && !empty($vapid_public_key)): ?>
<script>
(function () {
    var btn = document.getElementById('enable-push-alerts');
    if (!btn || !('serviceWorker' in navigator) || !('PushManager' in window)) return;

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    }

    btn.addEventListener('click', function () {
        navigator.serviceWorker.register('sw.js')
            .then(function (registration) {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(<?php echo json_encode($vapid_public_key); ?>)
                });
            })
            .then(function (subscription) {
                return fetch('index.php?route=notifications/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': <?php echo json_encode($_SESSION['csrf_token']); ?>
                    },
                    body: JSON.stringify(subscription)
                });
            })
            .then(function () {
                btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>Push Enabled';
                btn.disabled = true;
            })
            .catch(function () {
                btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i>Push Blocked';
            });
    });
})();
</script>
<?php endif; ?>
