/* Gruppa CMS — Gruppa Text: active link (link to the page you are on). */
(function () {
	function norm(p) {
		try { p = decodeURIComponent(p); } catch (e) {}
		return '/' + p.replace(/^\/+|\/+$/g, '');
	}

	function isCurrent(href, match) {
		var a;
		try { a = new URL(href, location.href); } catch (e) { return false; }
		if (a.origin !== location.origin) return false;
		var raw = href.trim();
		if (raw === '' || raw.charAt(0) === '#') return false;
		var lp = norm(a.pathname), cp = norm(location.pathname);
		var ok = lp === cp || (match === 'section' && lp !== '/' && (cp + '/').indexOf(lp + '/') === 0);
		if (ok && a.search) {
			var cur = new URLSearchParams(location.search);
			a.searchParams.forEach(function (v, k) { if (cur.get(k) !== v) ok = false; });
		}
		return ok;
	}

	function mark(root) {
		var list = root.querySelectorAll ? root.querySelectorAll('[data-gruppa-active]') : [];
		if (root.matches && root.matches('[data-gruppa-active]')) list = [root].concat(Array.prototype.slice.call(list));
		Array.prototype.forEach.call(list, function (el) {
			var link = el.querySelector('a[href]');
			if (!link) return;
			var on = isCurrent(link.getAttribute('href'), el.getAttribute('data-gruppa-active'));
			el.classList.toggle('is-active', on);
			if (on) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
		});
	}

	function start() {
		mark(document);
		// Listing pages loaded later (Load more, pagination, filters).
		if (window.MutationObserver) {
			new MutationObserver(function (records) {
				records.forEach(function (r) {
					Array.prototype.forEach.call(r.addedNodes, function (n) { if (n.nodeType === 1) mark(n); });
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
