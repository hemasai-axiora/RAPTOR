self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }
    var title = data.title || 'RAPTOR';
    var options = {
        body: data.body || data.message || 'You have a new notification.',
        icon: data.icon || '',
        badge: data.badge || '',
        data: { url: data.url || '/public/index.php?route=notifications/index' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/public/index.php?route=notifications/index';
    event.waitUntil(clients.openWindow(url));
});
