jQuery(function ($) {
	var cfg = window.gruppaIE || { fields: {}, ids: {} };

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	/* ---------- Export ---------- */
	var $fmt = $('.gruppa-io-export input[name="format"]');
	function toggleCsv() { $('.gio-csv').toggle($fmt.filter(':checked').val() === 'csv'); }
	$fmt.on('change', toggleCsv);
	toggleCsv();

	/* ---------- Import ---------- */
	if (!$('#gio-rows').length) return;

	var S = { headers: [], rows: [], format: '' };
	var $rows = $('#gio-rows'), $run = $('#gio-run'), $type = $('#gio-type'), $result = $('#gio-result');
	var $bar = $('#gio-progress'), $txt = $('#gio-progress-text'), $info = $('#gio-file-info');

	// Fields of the chosen type: [ key, label, hint, auto ].
	function typeFields() { return cfg.fields[$type.val()] || []; }
	function idKey() { return cfg.ids[String($type.val()).split(':')[0]] || 'id'; }

	function sample(h) {
		for (var i = 0; i < S.rows.length && i < 25; i++) {
			var v = S.rows[i][h];
			if (v !== undefined && v !== null && v !== '') return typeof v === 'object' ? JSON.stringify(v) : String(v);
		}
		return '';
	}

	// Auto-mapping of all file columns at once; every target field is used at most once.
	function plan(H, fields) {
		var out = {}, taken = {}, id = idKey(), i, j;
		var auto = fields.filter(function (f) { return f[3]; });
		var alias = [
			['cct_status', 'status', 'post_status'],
			['title', 'post_title', 'name', 'nazov', 'názov'],
			['slug', 'post_name'],
			['content', 'post_content'],
			['excerpt', 'post_excerpt', 'perex'],
			['order_index', 'menu_order'],
			['featured_image', 'thumbnail', 'image', 'obrazok', 'obrázok']
		];
		function take(h, key) { out[h] = key; taken[key] = true; }
		function free(h) { return out[h] === undefined; }
		// 1) exact key — the only way the ID field gets mapped.
		H.forEach(function (h) {
			for (i = 0; i < auto.length; i++) if (auto[i][0] === h && !taken[h]) { take(h, h); return; }
		});
		var rest = auto.filter(function (f) { return f[0] !== id; });
		// 2) case-insensitive key or label
		H.forEach(function (h) {
			if (!free(h)) return;
			var lh = h.toLowerCase().trim();
			for (i = 0; i < rest.length; i++) {
				var k = rest[i][0];
				if (!taken[k] && (k.toLowerCase() === lh || String(rest[i][1]).toLowerCase().trim() === lh)) { take(h, k); return; }
			}
		});
		// 3) same meaning across JetEngine / Gruppa CMS / WordPress exports
		H.forEach(function (h) {
			if (!free(h)) return;
			var lh = h.toLowerCase().trim();
			for (j = 0; j < alias.length; j++) {
				if (alias[j].indexOf(lh) === -1) continue;
				for (i = 0; i < rest.length; i++) {
					var k = rest[i][0];
					if (!taken[k] && alias[j].indexOf(k.toLowerCase()) !== -1) { take(h, k); return; }
				}
			}
		});
		return out;
	}

	function fieldLabel(f) { return f[1] && f[1] !== f[0] ? f[0] + ' — ' + f[1] : f[0]; }

	function render() {
		var F = typeFields(), H = S.headers, html = '';
		$result.empty();

		if (H.length && F.length) {
			var P = plan(H, F);
			H.forEach(function (h) {
				var g = P[h] || '', opts = '<option value="">— skip —</option>';
				F.forEach(function (f) {
					opts += '<option value="' + esc(f[0]) + '"' + (g === f[0] ? ' selected' : '') + '>' + esc(fieldLabel(f)) + '</option>';
				});
				html += '<tr><td><code>' + esc(h) + '</code><span class="gruppa-io-sample">' + esc(sample(h)) + '</span></td>' +
					'<td class="gruppa-io-arrow">→</td>' +
					'<td><select class="gio-target" data-col="' + esc(h) + '">' + opts + '</select></td></tr>';
			});
		} else {
			var n = Math.max(H.length, F.length, 1);
			for (var i = 0; i < n; i++) {
				var l = H[i] !== undefined ? '<code>' + esc(H[i]) + '</code><span class="gruppa-io-sample">' + esc(sample(H[i])) + '</span>'
					: (i === 0 && !H.length ? '<span class="gruppa-muted">File columns</span>' : '');
				var r = F[i] ? '<code>' + esc(F[i][0]) + '</code><span class="gruppa-io-hint">' + esc(F[i][2]) + '</span>'
					: (i === 0 && !F.length ? '<span class="gruppa-muted">Target type fields</span>' : '');
				html += '<tr><td>' + l + '</td><td class="gruppa-io-arrow"></td><td>' + r + '</td></tr>';
			}
		}
		$rows.html(html);
		refresh();
	}

	function refresh() {
		var any = false, id = idKey(), count = {};
		$rows.find('.gio-target').each(function () { var v = $(this).val(); if (v) count[v] = (count[v] || 0) + 1; });
		$rows.find('.gio-target').each(function () {
			var v = $(this).val(), $tr = $(this).closest('tr');
			$tr.toggleClass('is-mapped', !!v && v !== id).toggleClass('is-id', v === id).toggleClass('is-off', !v)
				.toggleClass('is-dup', !!v && count[v] > 1);
			if (v && v !== id) any = true;
		});
		$run.prop('disabled', !any);
	}

	function plural(c) { return c + (c === 1 ? ' item' : ' items'); }

	$('#gio-file').on('change', function () {
		var file = this.files[0];
		S.headers = []; S.rows = []; $info.text(''); $bar.prop('hidden', true);
		if (!file) { render(); return; }

		var fd = new FormData();
		fd.append('action', 'gruppa_ie_parse');
		fd.append('nonce', cfg.nonce);
		fd.append('file', file);
		$info.text('loading…');

		$.ajax({ url: cfg.ajax, type: 'POST', data: fd, processData: false, contentType: false })
			.done(function (r) {
				if (!r.success) { $info.text(''); $result.html('<div class="notice notice-error inline"><p>' + esc(r.data) + '</p></div>'); return; }
				S.headers = r.data.headers; S.rows = r.data.rows; S.format = r.data.format;
				$info.text(file.name + ' · ' + plural(S.rows.length));
				render();
			})
			.fail(function () { $info.text(''); $result.html('<div class="notice notice-error inline"><p>Upload failed.</p></div>'); });
		this.value = '';
	});

	$type.on('change', render);
	$rows.on('change', '.gio-target', refresh);

	$run.on('click', function () {
		var mapping = {}, used = {}, dup = null;
		$rows.find('.gio-target').each(function () {
			var t = $(this).val();
			if (!t) return;
			if (used[t]) dup = t;
			used[t] = true;
			mapping[$(this).data('col')] = t;
		});
		if (dup) { window.alert('Field "' + dup + '" is selected more than once.'); return; }
		if (used[idKey()] && !window.confirm('Items with an existing ID will be overwritten. Continue?')) return;

		var total = S.rows.length, done = 0, sum = { created: 0, updated: 0, skipped: 0, errors: [] };
		$run.prop('disabled', true);
		$result.empty();
		$bar.prop('hidden', false).find('div').css('width', '0%');
		$txt.text('0 / ' + total);

		function finish(failed) {
			$run.prop('disabled', false);
			var type = failed ? 'error' : (sum.errors.length ? 'warning' : 'success');
			var out = '<div class="notice notice-' + type + ' inline"><p><strong>Created: ' + sum.created + ' · Updated: ' + sum.updated +
				' · Skipped: ' + sum.skipped + '</strong>' + (failed ? '<br>Stopped: ' + esc(failed) : '') + '</p>';
			if (sum.errors.length) {
				out += '<ul class="gruppa-errors">' + sum.errors.slice(0, 200).map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>';
			}
			$result.html(out + '</div>');
		}

		(function next() {
			if (done >= total) { finish(); return; }
			var chunk = S.rows.slice(done, done + cfg.chunk);
			$.post(cfg.ajax, {
				action: 'gruppa_ie_import',
				nonce: cfg.nonce,
				type: $type.val(),
				offset: done,
				author: $('#gio-author').val(),
				keep_author: $('#gio-keep-author').is(':checked') ? 1 : 0,
				mapping: JSON.stringify(mapping),
				rows: JSON.stringify(chunk)
			}).done(function (r) {
				if (!r.success) { finish(r.data || 'error'); return; }
				sum.created += r.data.created; sum.updated += r.data.updated; sum.skipped += r.data.skipped;
				sum.errors = sum.errors.concat(r.data.errors);
				done += r.data.processed > 0 ? Math.min(r.data.processed, chunk.length) : chunk.length;
				$bar.find('div').css('width', Math.round(done / total * 100) + '%');
				$txt.text(done + ' / ' + total);
				next();
			}).fail(function (x) {
				var msg = 'request failed at row ' + (done + 1);
				if (x && x.status) { msg += ' (HTTP ' + x.status + (x.status === 504 || x.status === 524 ? ' — server timeout' : '') + ')'; }
				var body = x && x.responseText ? $('<div>').html(x.responseText).text().replace(/\s+/g, ' ').trim() : '';
				if (body) { msg += ': ' + body.slice(0, 300); }
				finish(msg);
			});
		})();
	});

	render();
});
