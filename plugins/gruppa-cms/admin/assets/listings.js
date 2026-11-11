/* Gruppa CMS — Listing Builder editor */
jQuery(function ($) {
	var cfg = window.gruppaListing;
	if (!cfg) return;
	var $type = $('#gl-type');

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	// System columns + fields of the chosen type (no media, no password, no "status").
	function columns() {
		var out = [];
		Object.keys(cfg.system).forEach(function (k) { out.push({ key: k, label: cfg.system[k] }); });
		(cfg.typeFields[$type.val()] || []).forEach(function (f) {
			if (f.key === 'title' || f.type === 'status' || f.type === 'media' || f.format === 'password' || f.link) return;
			out.push({ key: f.key, label: f.label });
		});
		return out;
	}

	function fill($s, extra) {
		var cur = $s.val() || String($s.data('current') || ''), found = !cur, html = '';
		(extra || []).concat(columns()).forEach(function (c) {
			if (c.key === cur) found = true;
			html += '<option value="' + esc(c.key) + '"' + (c.key === cur ? ' selected' : '') + '>' + esc(c.label + (c.key !== 'rand' ? ' (' + c.key + ')' : '')) + '</option>';
		});
		if (!found) html += '<option value="' + esc(cur) + '" selected>' + esc(cur) + ' (missing)</option>';
		$s.html(html);
	}

	function fillContext() {
		var $s = $('#gl-context'), cur = $s.val() || String($s.data('current') || ''), html = '<option value="">— no —</option>';
		(cfg.typeFields[$type.val()] || []).forEach(function (f) {
			if (f.type !== 'relation') return;
			html += '<option value="' + esc(f.key) + '"' + (f.key === cur ? ' selected' : '') + '>' + esc(f.label + ' (' + f.key + ')') + '</option>';
		});
		$s.html(html);
	}

	function fillGroups() {
		var $s = $('#gl-group'), cur = String($s.val() && $s.val() !== '0' ? $s.val() : ($s.data('current') || '0'));
		var list = (cfg.groups || {})[$type.val()] || [], html = '<option value="0">All groups</option>';
		list.forEach(function (g) {
			html += '<option value="' + esc(g.id) + '"' + (String(g.id) === cur ? ' selected' : '') + '>' + esc(g.name) + '</option>';
		});
		$s.html(html);
		$('#gl-group-row').toggle(list.length > 0);
	}

	function fillAll() {
		fill($('#gl-orderby'), [{ key: 'rand', label: 'Random' }]);
		fillContext();
		fillGroups();
	}

	$type.on('change', fillAll);

	// Name → slug.
	var $name = $('#g-name'), $slug = $('input#g-slug:not([readonly])');
	if ($slug.length) {
		$slug.on('input', function () { $slug.attr('data-touched', '1'); });
		$name.on('input', function () {
			if ($slug.attr('data-touched')) return;
			$slug.val(String($name.val()).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
				.replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40));
		});
	}

	fillAll();
});
