<?php
// Build a plain array of [lat,lng] for the polyline and JSON for JS.
$route = [];
foreach ($points as $p) { $route[] = [(float) $p->lat, (float) $p->lng]; }
$routeJson = json_encode($route);
$pinsJson  = json_encode([
    'login'  => ($pins && $pins->login_lat  !== null) ? [(float)$pins->login_lat,  (float)$pins->login_lng]  : null,
    'logout' => ($pins && $pins->logout_lat !== null) ? [(float)$pins->logout_lat, (float)$pins->logout_lng] : null,
]);
$hasPoints = count($route) > 0;
$baseRoute = $is_self ? 'location/myday' : ('location/member/' . (int)$user_id);
?>

<!-- Leaflet (map) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="pulse-card mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="text-white mb-1"><?php echo $is_self ? 'My Route' : htmlspecialchars($emp_name) . "'s Route"; ?></h4>
            <div class="text-secondary small">
                <?php echo htmlspecialchars($date); ?> &middot;
                <?php echo count($route); ?> points &middot;
                <strong class="text-white"><?php echo $distance; ?> km</strong> travelled
            </div>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-end">
            <input type="hidden" name="route" value="<?php echo $baseRoute; ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control bg-dark border-secondary text-white" style="max-width:180px;">
            <button class="btn btn-primary" style="background: var(--primary); border: none;">View</button>
        </form>
    </div>
</div>

<div class="pulse-card p-0" style="overflow:hidden;">
    <div id="route-map" style="height: 60vh; min-height: 380px; width: 100%; background:#0b0d16;"></div>
</div>

<?php if (!$hasPoints): ?>
<p class="text-secondary small text-center mt-3">
    <i class="fa-solid fa-circle-info me-1"></i>
    No location points recorded for this day. Location is only captured while the employee is checked in and the app is open.
</p>
<?php endif; ?>

<script>
$(function () {
    var route = <?php echo $routeJson; ?>;
    var pins  = <?php echo $pinsJson; ?>;

    var map = L.map('route-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var bounds = [];

    if (route.length > 0) {
        var line = L.polyline(route, { color: '#6366f1', weight: 4, opacity: 0.85 }).addTo(map);
        bounds = route.slice();

        // Start / end markers
        L.circleMarker(route[0], { radius: 7, color: '#10b981', fillColor: '#10b981', fillOpacity: 1 })
            .addTo(map).bindPopup('Start');
        L.circleMarker(route[route.length - 1], { radius: 7, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1 })
            .addTo(map).bindPopup('Latest');
    }

    // Attendance check-in / check-out pins
    if (pins.login)  { L.marker(pins.login).addTo(map).bindPopup('Check-in');  bounds.push(pins.login); }
    if (pins.logout) { L.marker(pins.logout).addTo(map).bindPopup('Check-out'); bounds.push(pins.logout); }

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
    } else {
        map.setView([20.5937, 78.9629], 4); // fallback (India centroid)
    }
});
</script>
