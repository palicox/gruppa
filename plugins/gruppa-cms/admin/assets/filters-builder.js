/* Gruppa CMS — Search Filters editor */
jQuery(function ($) {
	var cfg = window.gruppaFilters;
	var $list = $('#gfa-list');
	if (!cfg || !$list.length) return;
	var $type = $('#gfl-type');
	var index = $list.children().length;

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	// Fields of the type with a filter-relevant kind ("status" excluded; title = text, created/modified = date).
	function fields(kinds) {
		var list = (cfg.typeFields[$type.val()] || []).filter(function (f) {
			return f.type !== 'status' && f.format !== 'password' && kinds.indexOf(f.type) !== -1;
		});
		if (kinds.indexOf('date') !== -1) {
			list = list.concat([{ key: 'created', label: 'Created', type: 'date' }, { key: 'modified', label: 'Modified', type: 'date' }]);
		}
		return list;
	}

	function fill($card) {
		$card.find('select.gfl-field').each(function () {
			var $s = $(this), cur = $s.val() || String($s.data('current') || ''), found = !cur;
			var html = '<option value="">— select field —</option>';
			fields(String($s.data('kinds')).split(',')).forEach(function (f) {
				if (f.key === cur) found = true;
				html += '<option value="' + esc(f.key) + '"' + (f.key === cur ? ' selected' : '') + '>' + esc(f.label + ' (' + f.key + ')') + '</option>';
			});
			if (!found) html += '<option value="' + esc(cur) + '" selected>' + esc(cur) + ' (missing)</option>';
			$s.html(html);
		});
		$card.find('.gfl-fields').each(function () {
			var $b = $(this), name = $b.data('name');
			var cur = $b.find('input:checked').map(function () { return this.value; }).get();
			if (!$b.data('ready')) { cur = $b.data('current') || ['title']; $b.data('ready', 1); }
			var html = '';
			fields(['text', 'textarea', 'editor']).forEach(function (f) {
				html += '<label class="gfl-check"><input type="checkbox" name="' + esc(name) + '" value="' + esc(f.key) + '"' + (cur.indexOf(f.key) !== -1 ? ' checked' : '') + '> ' + esc(f.label) + '</label>';
			});
			$b.html(html || '<span class="gruppa-muted">Select the entity first.</span>');
		});
	}

	function summary($card) {
		var t = $card.data('type'), label = $card.find('[name$="[label]"]').val();
		$card.find('.gruppa-field-title').text(label || $card.find('.gruppa-field-type').text());
		var s = t === 'search' ? '?' + ($card.find('[name$="[param]"]').val() || 'q') + '=' : ($card.find('select.gfl-field').val() || '');
		$card.find('.gfa-summary').text(s);
	}

	$('#gfl-add').on('click', function () {
		var html = $('#tmpl-gfl-' + $('#gfl-add-type').val()).html();
		if (!html) return;
		var $card = $(html.replace(/__i__/g, 'n' + (index++)));
		$list.find('.gfa-card').removeClass('is-open');
		$list.append($card);
		fill($card);
		summary($card);
	});
	$list.on('click', '.gruppa-field-head', function () { $(this).closest('.gfa-card').toggleClass('is-open'); });
	$list.on('click', '.gfa-remove', function () {
		if (window.confirm('Remove this filter?')) $(this).closest('.gfa-card').remove();
	});
	$list.on('input change', 'input, select', function () { summary($(this).closest('.gfa-card')); });
	$list.sortable({ items: '> .gfa-card', handle: '.gruppa-handle', placeholder: 'gruppa-field-placeholder', tolerance: 'pointer' });

	$type.on('change', function () { $list.find('.gfa-card').each(function () { fill($(this)); }); });
	$('#gfl-apply').on('change', function () { $('.gfl-button-row').toggle(this.value === 'button'); }).trigger('change');

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

	$list.find('.gfa-card').each(function () { fill($(this)); summary($(this)); });
});
