/*
 * Gruppa admin help (Gruppa CMS + Gruppa Tools, identical copy in hub/).
 *
 * Every settings row with a description (td > p.description) gets an ⓘ at the right edge of
 * its label column; the description itself is hidden. ⓘ opens it in a bubble (hover, click,
 * keyboard). "Help" in the page header shows all descriptions under the fields instead
 * (icons disappear) — remembered per browser. "Tutorial" links the PDF manual (Gruppa CMS pages).
 * The layout of the fields is never changed.
 */
(function () {
	'use strict';
	if (window.gruppaHelpLoaded) return;
	window.gruppaHelpLoaded = true;

	var KEY = 'gruppaHelp', cfg = window.gruppaHelp || {}, pop = null, popFor = null, hideT = null, uid = 0;

	function on() { try { return window.localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
	function save(v) { try { window.localStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {} }

	function wraps() { return document.querySelectorAll('.gruppa-wrap'); }

	function apply(state) {
		Array.prototype.forEach.call(wraps(), function (w) { w.classList.toggle('gruppa-help-on', state); });
		Array.prototype.forEach.call(document.querySelectorAll('.gruppa-help-switch input'), function (i) { i.checked = state; });
		close();
	}

	/* ---------- Icons ---------- */

	function scan(root) {
		Array.prototype.forEach.call(root.querySelectorAll('.form-table tr'), function (tr) {
			if (tr.__gruppaHelp) return;
			var th = tr.querySelector(':scope > th'), td = tr.querySelector(':scope > td');
			if (!th || !td) return;
			var desc = td.querySelectorAll(':scope > p.description, :scope > .description');
			if (!desc.length) return;
			tr.__gruppaHelp = true;
			tr.classList.add('gruppa-has-help');
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'gruppa-help-i';
			b.setAttribute('aria-label', 'Help');
			b.setAttribute('aria-expanded', 'false');
			b.id = 'gruppa-help-' + (++uid);
			b.innerHTML = '<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><circle cx="10" cy="10" r="8.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 9v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="10" cy="6.2" r="1.05" fill="currentColor"/></svg>';
			th.classList.add('gruppa-help-th');
			th.appendChild(b);
		});
	}

	/* ---------- Bubble ---------- */

	function text(btn) {
		var td = btn.closest('tr').querySelector(':scope > td'), out = '';
		Array.prototype.forEach.call(td.querySelectorAll(':scope > p.description, :scope > .description'), function (d) { out += '<p>' + d.innerHTML + '</p>'; });
		return out;
	}

	function open(btn) {
		clearTimeout(hideT);
		if (popFor === btn) return;
		close();
		if (!pop) {
			pop = document.createElement('div');
			pop.className = 'gruppa-help-pop';
			pop.setAttribute('role', 'tooltip');
			pop.addEventListener('mouseenter', function () { clearTimeout(hideT); });
			pop.addEventListener('mouseleave', soon);
			document.body.appendChild(pop);
		}
		pop.innerHTML = text(btn);
		pop.id = btn.id + '-pop';
		btn.setAttribute('aria-describedby', pop.id);
		btn.setAttribute('aria-expanded', 'true');
		pop.hidden = false;
		var r = btn.getBoundingClientRect(), w = Math.min(340, window.innerWidth - 24);
		pop.style.width = w + 'px';
		var left = Math.max(12, Math.min(r.left + window.scrollX - 16, window.scrollX + window.innerWidth - w - 12));
		pop.style.left = left + 'px';
		pop.style.top = (r.bottom + window.scrollY + 8) + 'px';
		pop.style.setProperty('--gh-arrow', Math.max(12, r.left + window.scrollX + r.width / 2 - left - 6) + 'px');
		popFor = btn;
	}

	function close() {
		clearTimeout(hideT);
		if (pop) pop.hidden = true;
		if (popFor) { popFor.setAttribute('aria-expanded', 'false'); popFor.removeAttribute('aria-describedby'); }
		popFor = null;
	}
	function soon() { clearTimeout(hideT); hideT = setTimeout(close, 180); }

	/* ---------- Header: Tutorial + Help switch ---------- */

	function header(w) {
		if (w.querySelector('.gruppa-help-bar')) return;
		var end = w.querySelector(':scope > .wp-header-end, :scope > hr.wp-header-end');
		if (!end) return;
		var bar = document.createElement('div');
		bar.className = 'gruppa-help-bar';
		var html = '';
		if (cfg.tutorial) html += '<a class="gruppa-help-tutorial" href="' + cfg.tutorial + '" target="_blank" rel="noopener">Tutorial<span class="screen-reader-text"> (opens in a new tab)</span> ↗</a>';
		html += '<label class="gruppa-help-switch"><input type="checkbox"' + (on() ? ' checked' : '') + '><span class="gruppa-help-track" aria-hidden="true"></span>Help</label>';
		bar.innerHTML = html;
		// Next to the search of an items list, otherwise at the right of the title row.
		var search = w.querySelector(':scope > .gruppa-head-search, :scope > form.gruppa-head-search');
		if (search) search.parentNode.insertBefore(bar, search);
		else end.parentNode.insertBefore(bar, end);
		bar.querySelector('input').addEventListener('change', function () { save(this.checked); apply(this.checked); });
	}

	function boot() {
		Array.prototype.forEach.call(wraps(), function (w) { header(w); scan(w); });
		apply(on());
		// Rows added later (new fields, filters, columns, actions) get their icon too.
		if (window.MutationObserver) {
			var t = null;
			new MutationObserver(function () {
				clearTimeout(t);
				t = setTimeout(function () { Array.prototype.forEach.call(wraps(), scan); }, 60);
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	document.addEventListener('mouseover', function (e) {
		var b = e.target.closest && e.target.closest('.gruppa-help-i');
		if (b) open(b);
	});
	document.addEventListener('mouseout', function (e) {
		var b = e.target.closest && e.target.closest('.gruppa-help-i');
		if (b && !(e.relatedTarget && b.contains(e.relatedTarget))) soon();
	});
	document.addEventListener('focusin', function (e) {
		if (e.target.classList && e.target.classList.contains('gruppa-help-i')) open(e.target);
	});
	document.addEventListener('focusout', function (e) {
		if (e.target.classList && e.target.classList.contains('gruppa-help-i')) soon();
	});
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('.gruppa-help-i');
		if (b) { e.preventDefault(); if (popFor === b && pop && !pop.hidden) close(); else { popFor = null; open(b); } return; }
		if (pop && !pop.hidden && !(e.target.closest && e.target.closest('.gruppa-help-pop'))) close();
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
	window.addEventListener('resize', close);

	if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
})();
