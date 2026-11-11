/* Gruppa CMS — Listing: "Load more" */
(function () {
	function runElementor(nodes) {
		if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;
		nodes.forEach(function (node) {
			node.querySelectorAll('.elementor-element').forEach(function (el) {
				try { elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery ? jQuery(el) : el); } catch (e) {}
			});
		});
	}

	document.addEventListener('click', function (ev) {
		var btn = ev.target.closest && ev.target.closest('.gruppa-listing-more-button');
		if (!btn) return;
		var box = btn.closest('.gruppa-listing');
		var next = (parseInt(box.dataset.page, 10) || 1) + 1;
		var url = new URL(box.dataset.endpoint, window.location.href);
		url.searchParams.set('page', next);
		url.searchParams.set('per_page', box.dataset.perPage);
		// Page context for {current…} and {get:…} filter values.
		url.searchParams.set('ctx', box.dataset.ctx || 0);
		url.searchParams.set('qs', window.location.search);
		if (box.dataset.filterGroup && box.dataset.filterGroup !== '0') url.searchParams.set('group', box.dataset.filterGroup);
		// Active filters (Gruppa Filter) travel with the request.
		if (box.dataset.filters) {
			new URLSearchParams(box.dataset.filters).forEach(function (v, k) { url.searchParams.append(k, v); });
		}
		btn.disabled = true;
		fetch(url.toString(), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d || typeof d.html !== 'string') return;
				var grid = box.querySelector('.gruppa-listing-grid');
				var tmp = document.createElement('div');
				tmp.innerHTML = d.html;
				var added = Array.prototype.slice.call(tmp.children);
				added.forEach(function (n) { grid.appendChild(n); });
				runElementor(added);
				box.dataset.page = d.page;
				box.dataset.pages = d.pages;
				if (d.page >= d.pages) btn.closest('.gruppa-listing-more').remove();
			})
			.catch(function () {})
			.then(function () { btn.disabled = false; });
	});
})();
