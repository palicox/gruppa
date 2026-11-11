/* Gruppa CMS — Search Filters: update connected listings without reloading the page. */
(function () {
	function each(list, fn) { Array.prototype.forEach.call(list, fn); }

	// { param: "a,b" } from the form; checkboxes "param[]" are joined by commas.
	function values(form) {
		var out = {};
		each(form.elements, function (el) {
			if (!el.name || el.disabled || el.type === 'submit' || el.type === 'reset' || el.type === 'button') return;
			var name = el.name.replace(/\[\]$/, '');
			if (el.type === 'checkbox') {
				if (!(name in out)) out[name] = [];
				if (el.checked) out[name].push(el.value);
				return;
			}
			out[name] = el.value.trim();
		});
		Object.keys(out).forEach(function (k) { if (Array.isArray(out[k])) out[k] = out[k].join(','); });
		return out;
	}

	function runElementor(nodes) {
		if (!window.elementorFrontend || !elementorFrontend.elementsHandler) return;
		nodes.forEach(function (node) {
			if (!node.querySelectorAll) return;
			node.querySelectorAll('.elementor-element').forEach(function (el) {
				try { elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery ? jQuery(el) : el); } catch (e) {}
			});
		});
	}

	function refresh(box, url) {
		var api = new URL(box.dataset.endpoint, window.location.href);
		api.searchParams.set('page', 1);
		api.searchParams.set('per_page', box.dataset.perPage);
		api.searchParams.set('ctx', box.dataset.ctx || 0);
		api.searchParams.set('group', box.dataset.filterGroup);
		api.searchParams.set('qs', url.search);
		api.searchParams.set('url', url.toString());
		box.classList.add('is-loading');
		return fetch(api.toString(), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (!d || typeof d.html !== 'string') return;
				var grid = box.querySelector('.gruppa-listing-grid');
				grid.innerHTML = d.html;
				runElementor(Array.prototype.slice.call(grid.children));
				box.dataset.page = 1;
				box.dataset.pages = d.pages;
				// Empty text, pagination / load more.
				each(box.querySelectorAll('.gruppa-listing-empty, .gruppa-listing-message, .gruppa-listing-pagination, .gruppa-listing-more'), function (n) { n.remove(); });
				if (!d.total) {
					var e = document.createElement('div');
					e.className = 'gruppa-listing-empty';
					e.textContent = box.dataset.empty || '';
					box.insertBefore(e, grid);
				}
				if (d.pages > 1 && box.dataset.pagination === 'numbers' && d.pagination) {
					grid.insertAdjacentHTML('afterend', d.pagination);
				} else if (d.pages > 1 && box.dataset.pagination === 'load_more') {
					var m = document.createElement('div');
					m.className = 'gruppa-listing-more';
					m.innerHTML = '<button type="button" class="gruppa-listing-more-button"></button>';
					m.firstChild.textContent = box.dataset.moreText || 'Load more';
					box.appendChild(m);
				}
				box.dispatchEvent(new CustomEvent('gruppa:filtered', { detail: d, bubbles: true }));
			})
			.catch(function () {})
			.then(function () { box.classList.remove('is-loading'); });
	}

	function apply(form) {
		var group = form.dataset.group;
		var url = new URL(window.location.href);
		(form.dataset.params || '').split(',').forEach(function (p) { if (p) url.searchParams.delete(p); });
		var v = values(form);
		Object.keys(v).forEach(function (k) { if (v[k] !== '') url.searchParams.set(k, v[k]); });
		var boxes = document.querySelectorAll('.gruppa-listing[data-filter-group="' + group + '"]');
		// No listing set to this group: take listings of the same content type that have no group.
		if (!boxes.length && form.dataset.type) {
			boxes = document.querySelectorAll('.gruppa-listing[data-type="' + form.dataset.type + '"][data-filter-group="0"]');
			each(boxes, function (box) { box.dataset.filterGroup = group; });
		}
		// A new filter starts on page 1.
		each(boxes, function (box) { url.searchParams.delete('lp' + box.dataset.listing); });
		window.history.replaceState(null, '', url.toString());
		if (!boxes.length) { window.location.href = url.toString(); return; }
		each(boxes, function (box) { refresh(box, url); });
		// Other filter widgets of the same group follow.
		each(document.querySelectorAll('form.gruppa-filters[data-group="' + group + '"]'), function (other) {
			if (other === form) return;
			each(other.elements, function (el) {
				var name = (el.name || '').replace(/\[\]$/, '');
				if (!name || !(name in v)) return;
				if (el.type === 'checkbox') el.checked = v[name].split(',').indexOf(el.value) !== -1;
				else el.value = v[name];
			});
		});
	}

	function init(form) {
		if (form.__gruppa) return;
		form.__gruppa = true;
		var instant = form.dataset.apply !== 'button', timer = null;
		form.addEventListener('submit', function (e) { e.preventDefault(); apply(form); });
		// Clear everything (a native reset would return to the values from the URL).
		form.addEventListener('click', function (e) {
			if (!e.target.closest || !e.target.closest('.gruppa-filters-reset')) return;
			e.preventDefault();
			each(form.elements, function (el) {
				if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
				else if (el.tagName === 'SELECT') el.selectedIndex = 0;
				else if (el.type !== 'button' && el.type !== 'submit' && el.type !== 'hidden') el.value = '';
			});
			apply(form);
		});
		form.addEventListener('change', function (e) {
			if (instant && e.target.type !== 'search') apply(form);
		});
		form.addEventListener('input', function (e) {
			if (!instant) return;
			if (e.target.type === 'search' || e.target.type === 'number') {
				clearTimeout(timer);
				timer = setTimeout(function () { apply(form); }, 450);
			}
		});
	}

	function boot() { each(document.querySelectorAll('form.gruppa-filters'), init); }
	if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && elementorFrontend.hooks) {
			elementorFrontend.hooks.addAction('frontend/element_ready/gruppa-search-filters.default', boot);
		}
	});
})();
