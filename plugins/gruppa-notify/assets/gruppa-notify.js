/* Gruppa Notify – registrácia zariadenia pre Web Push */
(function () {
    const C = window.GruppaNotify;
    if (!C) return;

    const T = C.i18n;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

    function keyToUint8(base64) {
        const pad = '='.repeat((4 - (base64.length % 4)) % 4);
        const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(raw, (c) => c.charCodeAt(0));
    }

    function api(method, sub) {
        return fetch(C.restUrl, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
            body: JSON.stringify(sub.toJSON ? sub.toJSON() : sub),
        });
    }

    const off = {
        get: () => { try { return localStorage.getItem('gn_off') === '1'; } catch (e) { return false; } },
        set: (v) => { try { v ? localStorage.setItem('gn_off', '1') : localStorage.removeItem('gn_off'); } catch (e) {} },
    };

    let regPromise = null;
    function registration() {
        if (!regPromise) {
            const scope = new URL(C.swUrl).pathname;
            regPromise = navigator.serviceWorker
                .register(C.swUrl, { scope })
                .then(() => navigator.serviceWorker.ready);
        }
        return regPromise;
    }

    async function subscribe() {
        const reg = await registration();
        let sub = await reg.pushManager.getSubscription();
        const key = keyToUint8(C.publicKey);

        // Ak sa zmenil VAPID kľúč, starú subscription zahoď.
        if (sub && sub.options && sub.options.applicationServerKey) {
            const cur = new Uint8Array(sub.options.applicationServerKey);
            if (cur.length !== key.length || cur.some((b, i) => b !== key[i])) {
                await sub.unsubscribe();
                sub = null;
            }
        }
        if (!sub) {
            sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        }
        await api('POST', sub);
        return sub;
    }

    async function unsubscribe() {
        const reg = await registration();
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await api('DELETE', { endpoint: sub.endpoint });
            await sub.unsubscribe();
        }
    }

    async function isSubscribed() {
        if (Notification.permission !== 'granted') return false;
        const reg = await registration();
        return !!(await reg.pushManager.getSubscription());
    }

    /* ---------- tlačidlá [gruppa_notify_button] ---------- */

    const buttons = document.querySelectorAll('[data-gn-push]');

    function render(state, msg) {
        buttons.forEach((btn) => {
            const box = btn.parentElement.querySelector('[data-gn-msg]');
            btn.disabled = state === 'busy' || state === 'blocked';
            btn.dataset.state = state;
            btn.textContent =
                state === 'on' ? T.disable :
                state === 'busy' ? T.working : T.enable;
            if (box) box.textContent = msg || (state === 'on' ? T.enabled : '');
        });
    }

    async function refresh() {
        if (!supported) {
            if (isIOS && !standalone) return render('blocked', T.ios);
            return render('blocked', T.unsupported);
        }
        if (Notification.permission === 'denied') return render('blocked', T.denied);
        render((await isSubscribed()) ? 'on' : 'off');
    }

    buttons.forEach((btn) =>
        btn.addEventListener('click', async () => {
            const wasOn = btn.dataset.state === 'on';
            render('busy');
            try {
                if (wasOn) {
                    await unsubscribe();
                    off.set(true);
                } else {
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') return refresh();
                    await subscribe();
                    off.set(false);
                }
            } catch (e) {
                console.error('[Gruppa Notify]', e);
            }
            refresh();
        })
    );

    /* ---------- tichá synchronizácia pri každom načítaní ---------- */

    if (supported) {
        registration()
            .then(() => {
                if (Notification.permission === 'granted' && !off.get()) return subscribe();
            })
            .catch((e) => console.error('[Gruppa Notify]', e))
            .finally(refresh);
    } else {
        refresh();
    }
})();
