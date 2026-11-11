/* Gruppa Notify – service worker */
const GN_AJAX = '__GN_AJAX__';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (err) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Notifikácia';
    const options = {
        body: data.body || '',
        icon: data.icon || undefined,
        badge: data.icon || undefined,
        tag: data.id ? 'gn-' + data.id : undefined,
        requireInteraction: true,
        data: { url: data.url || '/', id: data.id || 0, t: data.t || '', l: data.l || 0, lt: data.lt || '' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const d = event.notification.data || {};
    const url = d.url || '/';

    let body = '';
    if (d.l && d.lt) body = 'action=gruppa_notify_read&l=' + encodeURIComponent(d.l) + '&lt=' + encodeURIComponent(d.lt);
    else if (d.id && d.t) body = 'action=gruppa_notify_read&id=' + encodeURIComponent(d.id) + '&t=' + encodeURIComponent(d.t);

    const markRead = body
        ? fetch(GN_AJAX, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body,
          }).catch(() => {})
        : Promise.resolve();

    const open = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const c of list) {
            if (c.url === url && 'focus' in c) return c.focus();
        }
        return self.clients.openWindow(url);
    });

    event.waitUntil(Promise.all([markRead, open]));
});
