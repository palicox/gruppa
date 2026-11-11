/* Gruppa Table Builder — click on a column header sorts the rows (numbers as numbers). */
(function () {
	function sortBy(th) {
		var table = th.closest('table'), body = table.tBodies[0];
		if (!body) return;
		var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
		var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
		Array.prototype.forEach.call(th.parentNode.children, function (h) { if (h.hasAttribute('data-sort')) h.setAttribute('aria-sort', 'none'); });
		th.setAttribute('aria-sort', dir);
		var rows = Array.prototype.slice.call(body.rows);
		rows.sort(function (a, b) {
			var x = a.cells[idx].getAttribute('data-sort-value') || '', y = b.cells[idx].getAttribute('data-sort-value') || '';
			var nx = parseFloat(x), ny = parseFloat(y), r;
			if (x !== '' && y !== '' && !isNaN(nx) && !isNaN(ny) && String(nx) === x.replace(/\.0+$/, '') && String(ny) === y.replace(/\.0+$/, '')) r = nx - ny;
			else r = x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' });
			return dir === 'ascending' ? r : -r;
		});
		rows.forEach(function (r) { body.appendChild(r); });
	}
	document.addEventListener('click', function (e) {
		var th = e.target.closest && e.target.closest('.gruppa-table th[data-sort]');
		if (th) sortBy(th);
	});
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') return;
		var th = e.target.closest && e.target.closest('.gruppa-table th[data-sort]');
		if (th) { e.preventDefault(); sortBy(th); }
	});
})();
