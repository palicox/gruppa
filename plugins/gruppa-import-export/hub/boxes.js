/* Gruppa CMS 0.32.3 — box titles inside the box.
 * Every h2.gruppa-fields-title followed by a .gruppa-card (or a list table) becomes the head of that box:
 * light lavender bar, 12px 24px, same as the Structure map. Lists of field rows stay as they are. */
(function () {
	function run() {
		var titles = document.querySelectorAll('.gruppa-wrap h2.gruppa-fields-title');
		Array.prototype.forEach.call(titles, function (h) {
			var n = h.nextElementSibling, box;
			if (!n) return;
			if (n.classList.contains('gruppa-card')) {
				box = n;
			} else if (n.classList.contains('gruppa-edit-layout') && n.querySelector('.gruppa-edit-main > .gruppa-card')) {
				box = n.querySelector('.gruppa-edit-main > .gruppa-card'); // Main Settings next to the side box.
			} else if (n.matches('table.gruppa-table')) {
				box = document.createElement('div');
				box.className = 'gruppa-card gruppa-box';
				n.parentNode.insertBefore(box, n);
				box.appendChild(n);
				n.classList.add('gruppa-box-table');
			} else {
				return;
			}
			var cs = window.getComputedStyle(box),
				pt = parseFloat(cs.paddingTop) || 0,
				pl = parseFloat(cs.paddingLeft) || 0,
				pr = parseFloat(cs.paddingRight) || 0,
				head = document.createElement('div');
			box.classList.add('gruppa-boxed');
			head.className = 'gruppa-box-head';
			head.style.margin = (-pt) + 'px ' + (-pr) + 'px ' + (pt ? 24 : 0) + 'px ' + (-pl) + 'px';
			h.classList.remove('gruppa-fields-title');
			h.classList.add('gruppa-box-title');
			head.appendChild(h);
			box.insertBefore(head, box.firstChild);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	} else {
		run();
	}
})();
