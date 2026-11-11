/* Gruppa Table Builder — table editor: source rows, column cards, query options.
   Types are keyed "origin:slug" (gruppa / jet / wp). Columns: [ key, label, type label, normalized type ]. */
jQuery(function ($) {
	var D = window.gruppaTB || { types: {}, index: 0 };
	var $type = $('#gtb-type'), $source = $('#gtb-source'), $field = $('#gtb-field');
	var $list = $('#gfa-list'), $add = $('#gtb-add-key'), tmpl = $('#tmpl-gtb-card').html() || '', idx = D.index || 0;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function T() { return D.types[$type.val()] || null; }

	function options($sel, list, empty) {
		var cur = $sel.data('current');
		$sel.html((empty ? '<option value="">' + esc(empty) + '</option>' : '') + list.map(function (o) {
			return '<option value="' + esc(o[0]) + '">' + esc(o[1]) + '</option>';
		}).join(''));
		if (cur !== undefined && $sel.find('option').filter(function () { return this.value === String(cur); }).length) $sel.val(String(cur));
	}

	// Columns that can be added now: [ key, label, field type ].
	function available() {
		var t = T();
		if (!t) return [];
		if ($source.val() === 'repeater') {
			var r = t.repeaters.filter(function (x) { return x[0] === $field.val(); })[0];
			return r ? r[2] : [];
		}
		return t.items;
	}
	function used() { return $list.find('.gtb-card').map(function () { return $(this).data('key'); }).get(); }

	function refreshAdd() {
		var u = used(), list = available().filter(function (a) { return u.indexOf(a[0]) === -1; });
		$add.html(list.length ? list.map(function (a) { return '<option value="' + esc(a[0]) + '">' + esc(a[1]) + ' (' + esc(a[0]) + ')</option>'; }).join('')
			: '<option value="">' + ($type.val() ? 'All columns added' : 'Select a content type first') + '</option>');
		$('#gtb-add, #gtb-add-all').prop('disabled', !list.length);
	}

	function addColumn(a, open) {
		$list.find('.gtb-card.is-open').removeClass('is-open');
		var html = tmpl.replace(/__i__/g, 'n' + (idx++)).replace(/__key__/g, esc(a[0])).replace(/__name__/g, esc(a[1])).replace(/__ftype__/g, esc(a[2])).replace(/__type__/g, esc(a[3] || 'text'));
		var $c = $(html);
		if (!open) $c.removeClass('is-open');
		$list.append($c);
		numRow($c);
	}

	function numRow($c) {
		var f = $c.find('.gtb-format').val(), t = String($c.attr('data-type') || '');
		$c.find('.gtb-num-row').toggle(f === 'number' || (f === 'auto' && t === 'number'));
	}

	function sourceRows() {
		var rep = $source.val() === 'repeater', t = T();
		$('.gtb-rep-row').toggle(rep);
		$('.gtb-items-only').toggle(!rep);
		$field.prop('required', rep);
		$list.find('.gtb-format option[value="item_link"]').prop('disabled', rep);
		if (t) {
			options($field, t.repeaters.map(function (r) { return [r[0], r[1]]; }), t.repeaters.length ? '— select —' : '— this type has no Repeater field —');
			$field.data('current', $field.val());
			options($('#gtb-orderby'), t.order);
			options($('#gtb-context'), t.context, '— no —');
			options($('#gtb-group'), t.groups, 'All groups');
			$('#gtb-group-row').toggle(t.groups.length > 0);
			$('#gtb-context-row').toggle(t.context.length > 0);
			$('#gtb-item-slug').prop('disabled', !t.slugItem).toggle(!!t.slugItem);
			if (!t.slugItem && $('#gtb-item').val() === 'slug') $('#gtb-item').val('id');
		}
		$('#gtb-item-value').toggle($('#gtb-item').val() !== 'current');
		refreshAdd();
	}

	// Columns no longer valid after a change of type / source / repeater are removed (after a question).
	function prune() {
		var keys = available().map(function (a) { return a[0]; });
		var bad = $list.find('.gtb-card').filter(function () { return keys.indexOf($(this).data('key')) === -1; });
		if (bad.length && window.confirm(bad.length + ' column(s) do not exist in the new source and will be removed. Continue?')) bad.remove();
	}

	$type.on('change', function () { $field.data('current', ''); sourceRows(); prune(); refreshAdd(); });
	$source.on('change', function () { sourceRows(); prune(); refreshAdd(); });
	$field.on('change', function () { prune(); refreshAdd(); });
	$('#gtb-item').on('change', function () { $('#gtb-item-value').toggle(this.value !== 'current'); });

	$('#gtb-add').on('click', function () {
		var k = $add.val(), a = available().filter(function (x) { return x[0] === k; })[0];
		if (a) addColumn(a, true);
		refreshAdd();
	});
	$('#gtb-add-all').on('click', function () {
		var u = used();
		available().forEach(function (a) { if (u.indexOf(a[0]) === -1) addColumn(a, false); });
		refreshAdd();
	});

	$list.on('click', '.gruppa-field-head', function (e) {
		if ($(e.target).closest('.gruppa-handle').length) return;
		$(this).closest('.gtb-card').toggleClass('is-open');
	});
	$list.on('click', '.gtb-remove', function () { $(this).closest('.gtb-card').remove(); refreshAdd(); });
	$list.on('input', '.gtb-label', function () {
		var $c = $(this).closest('.gtb-card');
		$c.find('.gruppa-field-title').text(this.value || this.placeholder);
	});
	$list.on('change', '.gtb-format', function () { numRow($(this).closest('.gtb-card')); });
	$list.sortable({ items: '> .gtb-card', handle: '.gruppa-handle', axis: 'y' });
	$list.find('.gtb-card').each(function () { numRow($(this)); });

	$('#gtb-form').on('submit', function (e) {
		if (!$list.find('.gtb-card').length && !window.confirm('The table has no columns yet. Save anyway?')) e.preventDefault();
	});

	// Name → slug (like Gruppa CMS): lower case, no accents, a-z 0-9 _ ; dash is turned into _.
	var $name = $('#g-name'), $slug = $('input#g-slug:not([readonly])');
	function slugify(v) {
		return String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);
	}
	if ($slug.length) {
		$slug.on('input', function () { $slug.attr('data-touched', '1'); });
		$slug.on('change blur', function () { $slug.val(slugify($slug.val())); });
		$name.on('input', function () { if (!$slug.attr('data-touched')) $slug.val(slugify($name.val())); });
		$('#gtb-form').on('submit', function () { $slug.val(slugify($slug.val() || $name.val())); });
	}

	sourceRows();
});
