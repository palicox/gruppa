jQuery(function ($) {
	var $wrap = $('#gruppa-fields');
	if (!$wrap.length) return;

	var fieldTmpl = $('#tmpl-gruppa-field').html();
	var optTmpl = $('#tmpl-gruppa-option').html();
	var idx = window.gruppaFieldIndex || 0;
	var optIdx = 1000;

	function slugKey(s) {
		return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);
	}

	function applyType($f) {
		var type = $f.find('.g-type').val();
		$f.attr('data-type', type);
		$f.find('tr[data-for]').each(function () {
			var f = $(this).data('for');
			$(this).toggle(f === '*' || (' ' + f + ' ').indexOf(' ' + type + ' ') > -1);
		});
		$f.find('.gruppa-field-type').text($f.find('.g-type option:selected').text());
	}

	function sortOptions($f) {
		$f.find('.gruppa-options-list').sortable({ handle: '.gruppa-handle', axis: 'y' });
	}

	function addOption($f, value, label) {
		var html = optTmpl.replace(/__i__/g, $f.data('index')).replace(/__j__/g, optIdx++);
		var $o = $(html);
		$o.find('.g-opt-value').val(value || '');
		$o.find('.g-opt-label').val(label || '');
		$f.find('.gruppa-options-list').append($o);
		return $o;
	}

	function manualToBulk($f) {
		var lines = [];
		$f.find('.gruppa-option').each(function () {
			var v = $.trim($(this).find('.g-opt-value').val());
			var l = $.trim($(this).find('.g-opt-label').val());
			if (!v && l) v = l;
			if (!v) return;
			lines.push(l && l !== v ? v + ' : ' + l : v);
		});
		$f.find('.g-opt-bulk').val(lines.join('\n'));
	}

	function bulkToManual($f) {
		$f.find('.gruppa-options-list').empty();
		$.each($f.find('.g-opt-bulk').val().split(/\r?\n/), function (_, line) {
			line = $.trim(line);
			if (!line) return;
			var p = line.split(':');
			var v = $.trim(p.shift());
			var l = $.trim(p.join(':')) || v;
			addOption($f, v, l);
		});
	}

	$wrap.find('.gruppa-field').each(function () {
		applyType($(this));
		sortOptions($(this));
	});

	$wrap.sortable({
		items: '> .gruppa-field',
		handle: '.gruppa-handle',
		axis: 'y',
		placeholder: 'gruppa-field-placeholder'
	});

	$('#gruppa-add-field').on('click', function () {
		$wrap.find('.gruppa-field.is-open').removeClass('is-open');
		var $f = $(fieldTmpl.replace(/__i__/g, idx++));
		$wrap.append($f);
		var pick = $('#gruppa-add-field-type').val();
		if (pick) $f.find('.g-type').val(pick).trigger('change');
		applyType($f);
		sortOptions($f);
		$f.find('.g-label').trigger('focus');
	});

	$wrap.on('click', '.gruppa-field-head', function (e) {
		if ($(e.target).closest('.gruppa-handle, .gruppa-field-copy').length) return;
		$(this).closest('.gruppa-field').toggleClass('is-open');
	});

	$wrap.on('click', '.gruppa-remove-field', function () {
		if (window.confirm('Remove?')) $(this).closest('.gruppa-field').remove();
	});

	$wrap.on('input', '.g-label', function () {
		var $f = $(this).closest('.gruppa-field');
		var $key = $f.find('.g-key');
		$f.find('.gruppa-field-title').text(this.value || 'New field');
		if (!$key.attr('data-touched')) {
			$key.val(slugKey(this.value));
			$f.find('.gruppa-field-key').text($key.val());
		}
	});

	$wrap.on('input', '.g-key', function () {
		var clean = slugKey(this.value.replace(/-/g, '_'));
		if (/[^a-z0-9_]/.test(this.value)) this.value = clean;
		$(this).attr('data-touched', '1');
		$(this).closest('.gruppa-field').find('.gruppa-field-key').text(this.value);
	});

	$wrap.on('change', '.g-type', function () {
		applyType($(this).closest('.gruppa-field'));
	});

	$wrap.on('change', '.g-opt-mode', function () {
		var $f = $(this).closest('.gruppa-field');
		var mode = this.value;
		if (mode === 'bulk') manualToBulk($f); else bulkToManual($f);
		$f.find('.gruppa-options').attr('data-mode', mode);
	});

	$wrap.on('click', '.gruppa-option-add', function () {
		addOption($(this).closest('.gruppa-field')).find('.g-opt-value').trigger('focus');
	});

	$wrap.on('click', '.gruppa-option-remove', function () {
		$(this).closest('.gruppa-option').remove();
	});

	// Repeater columns.
	var subTmpl = $('#tmpl-gruppa-sub').html() || '', subIdx = 1000;
	function sortSubs($f) {
		$f.find('.gruppa-subs-list').sortable({ handle: '.gruppa-handle', axis: 'y' });
	}
	$wrap.find('.gruppa-field').each(function () { sortSubs($(this)); });
	$wrap.on('click', '.gruppa-sub-add', function () {
		var $f = $(this).closest('.gruppa-field');
		var $s = $(subTmpl.replace(/__i__/g, $f.data('index')).replace(/__j__/g, subIdx++));
		$f.find('.gruppa-subs-list').append($s);
		sortSubs($f);
		$s.find('.g-sub-label').trigger('focus');
	});
	$wrap.on('click', '.gruppa-sub-remove', function () {
		$(this).closest('.gruppa-sub').remove();
	});
	$wrap.on('input', '.g-sub-label', function () {
		var $k = $(this).siblings('.g-sub-key');
		if (!$k.attr('data-touched')) $k.val(slugKey(this.value));
	});
	$wrap.on('input', '.g-sub-key', function () {
		$(this).attr('data-touched', '1');
		if (/[^a-z0-9_]/.test(this.value)) this.value = slugKey(this.value);
	});
	$wrap.on('change', '.g-sub-type', function () {
		$(this).closest('.gruppa-sub').attr('data-type', this.value);
	});

	// 0.33: Conditions — Show item [field] When [operator] [value] [and / or].
	var condTmpl = $('#tmpl-gruppa-cond').html() || '', condIdx = 1000;
	function fieldChoices($self) {
		var out = [];
		$wrap.find('> .gruppa-field').each(function () {
			if (this === $self[0]) return;
			var key = $.trim($(this).find('.g-key').val()), label = $.trim($(this).find('.g-label').val());
			var type = $(this).find('.g-type').val();
			if (key && type !== 'html') out.push([key, label || key]);
		});
		return out;
	}
	function fillConds($f) {
		var choices = fieldChoices($f);
		$f.find('.g-cond-field').each(function () {
			var $s = $(this), cur = $s.val() || $s.data('value') || '';
			$s.find('option:not(:first)').remove();
			$.each(choices, function (_, c) {
				$s.append($('<option>').val(c[0]).text(c[1] + ' (' + c[0] + ')'));
			});
			if (cur && !$s.find('option').filter(function () { return this.value === cur; }).length) {
				$s.append($('<option>').val(cur).text(cur + ' (missing)'));
			}
			$s.val(cur);
		});
		$f.find('.g-cond-op').each(function () { opState($(this)); });
	}
	function opState($op) {
		var none = $op.val() === 'empty' || $op.val() === 'not_empty';
		$op.siblings('.g-cond-value').prop('disabled', none).attr('placeholder', none ? '—' : 'Write value');
	}
	$wrap.on('focus mousedown', '.g-cond-field', function () {
		var $f = $(this).closest('.gruppa-field'), $s = $(this);
		$s.data('value', $s.val());
		fillConds($f);
	});
	$wrap.on('change', '.g-cond-op', function () { opState($(this)); });
	$wrap.on('click', '.gruppa-cond-add', function () {
		var $f = $(this).closest('.gruppa-field');
		$f.find('.gruppa-cond-list').append(condTmpl.replace(/__i__/g, $f.data('index')).replace(/__j__/g, condIdx++));
		fillConds($f);
	});
	$wrap.on('click', '.gruppa-cond-remove', function () {
		var $list = $(this).closest('.gruppa-cond-list');
		if ($list.children('.gruppa-cond').length > 1) {
			$(this).closest('.gruppa-cond').remove();
		} else {
			var $c = $(this).closest('.gruppa-cond');
			$c.find('.g-cond-field').val('').data('value', '');
			$c.find('.g-cond-value').val('');
			$c.find('.g-cond-op').val('is').trigger('change');
		}
	});
	$wrap.find('> .gruppa-field').each(function () { fillConds($(this)); });

	// 0.33: Copy a field — a new field under the original, with "_copy" key, open for editing.
	$wrap.on('click', '.gruppa-field-copy', function (e) {
		e.stopPropagation();
		var $src = $(this).closest('.gruppa-field'), old = String($src.data('index')), ni = idx++;
		var $c = $src.clone(false);
		$c.find('.ui-sortable').removeClass('ui-sortable');
		$c.find('input[name$="[orig_key]"]').remove();
		$c.find('[name]').each(function () {
			this.name = this.name.replace('fields[' + old + ']', 'fields[' + ni + ']');
		});
		// Values a clone does not carry: selects and textareas.
		var $ss = $src.find('select, textarea'), $cs = $c.find('select, textarea');
		$ss.each(function (k) { $cs.eq(k).val($(this).val()); });
		$c.attr('data-index', ni).data('index', ni);
		var $key = $c.find('.g-key'), $label = $c.find('.g-label');
		var key = slugKey(($key.val() || 'field') + '_copy');
		$key.val(key).attr('data-touched', '1');
		$label.val(($label.val() || 'Field') + ' (copy)');
		$c.find('.gruppa-field-title').text($label.val());
		$c.find('.gruppa-field-key').text(key);
		// A copy is a new field: every type is offered again.
		var $type = $c.find('.g-type'), cur = $type.val();
		$type.html($('#tmpl-gruppa-field').length ? $($('#tmpl-gruppa-field').html()).find('.g-type').html() : $type.html()).val(cur);
		$wrap.find('.gruppa-field.is-open').removeClass('is-open');
		$c.addClass('is-open').insertAfter($src);
		applyType($c);
		sortOptions($c);
		sortSubs($c);
		fillConds($c);
		$label.trigger('focus').select();
	});

	// Type name -> slug.
	var $name = $('#g-name'), $slug = $('#g-slug');
	if ($slug.length) {
		$slug.on('input', function () { $slug.attr('data-touched', '1'); });
		$name.on('input', function () { if (!$slug.attr('data-touched')) { $slug.val(slugKey($name.val())); $slug.trigger('table'); } });
		$slug.on('input table', function () { $('#g-table').text($('#g-table').data('prefix') + $slug.val()); });
	}

	$('#g-rest').on('change', function () {
		$('.g-rest-row').toggle(this.value === '1');
	}).trigger('change');
	$('.g-acc').on('change', function () {
		var $cap = $(this).siblings('.g-acc-cap').toggle(this.value === 'cap');
		$cap.prop('required', this.value === 'cap');
	});
	$('#g-slug').on('input table', function () {
		var $e = $('#g-endpoint');
		$e.text($e.data('base') + $(this).val());
	});

	// Data type (0.22): structure, attachment, groups.
	function dataRows() {
		var data = $('#g-kind').val() === 'data', groups = $('#g-structure').val() === 'groups';
		$('.g-data-row').toggle(data);
		$('.g-values-row').toggle(data && !groups);
		$('.g-groups-row').toggle(data && groups);
	}
	$('#g-kind').on('change', function () {
		var on = this.value === 'content';
		$('#g-post-type-row').toggle(on);
		$('#g-post-type').prop('required', on);
		dataRows();
	}).trigger('change');
	$('#g-structure').on('change', dataRows);

	var $groups = $('#g-groups'), groupTmpl = $('#tmpl-gruppa-group').html() || '', gIdx = window.gruppaGroupIndex || 0;
	$groups.sortable({ items: '> .g-group', handle: '.gruppa-handle', axis: 'y' });
	$('#g-add-group').on('click', function () {
		var $g = $(groupTmpl.replace(/__g__/g, 'n' + (gIdx++)));
		$groups.append($g);
		$g.find('.g-group-name').trigger('focus');
	});
	$groups.on('input', '.g-group-name', function () {
		var $slug = $(this).siblings('.g-group-slug');
		if (!$slug.prop('readonly') && !$slug.attr('data-touched')) $slug.val(slugKey(this.value));
	});
	$groups.on('input', '.g-group-slug', function () {
		$(this).attr('data-touched', '1');
		if (/[^a-z0-9_]/.test(this.value)) this.value = slugKey(this.value);
	});
	$groups.on('click', '.g-group-remove', function () {
		var $g = $(this).closest('.g-group');
		if (+$g.data('count') > 0) return;
		if (!$g.find('.g-group-name').val() || window.confirm('Remove this group?')) $g.remove();
	});

	// Open invalid fields before the browser blocks submit.
	$('#gruppa-type-form').on('submit', function (e) {
		var reserved = window.gruppaReserved || [], seen = {}, bad = null;
		$wrap.find('.gruppa-field').each(function () {
			var $f = $(this), $k = $f.find('.g-key');
			if (!$k.val()) $k.val(slugKey($f.find('.g-label').val()));
			var k = $k.val();
			if (!bad && k && (reserved.indexOf(k) > -1 || seen[k])) bad = { $f: $f, $k: $k, k: k };
			seen[k] = true;
		});
		if (!bad && $('#g-kind').val() === 'data' && $('#g-structure').val() === 'groups' && !$groups.find('.g-group-name').filter(function () { return $.trim(this.value) !== ''; }).length) {
			e.preventDefault();
			window.alert('Add at least one group, or choose Records "Single records".');
			return;
		}
		if (bad) {
			e.preventDefault();
			bad.$f.addClass('is-open');
			window.alert('Key "' + bad.k + '" is reserved or duplicate.');
			bad.$k.trigger('focus');
		}
	});
	document.getElementById('gruppa-type-form').addEventListener('invalid', function (e) {
		$(e.target).closest('.gruppa-field').addClass('is-open');
	}, true);
});

/* Content Types / Data Types → Menu icon (Dashicons picker). */
jQuery(function ($) {
	var $p = $('.gruppa-icon-picker');
	if (!$p.length) return;
	var $in = $p.find('#g-icon'), $btn = $p.find('.gruppa-icon-btn'), $panel = $p.find('.gruppa-icon-panel');
	var $reset = $p.find('.gruppa-icon-reset'), $search = $p.find('.gruppa-icon-search');

	function def() {
		return $p.data($('#g-kind').val() === 'data' ? 'default-data' : 'default-content');
	}
	function show() {
		var v = $in.val(), ic = v || def();
		$btn.find('.dashicons').attr('class', 'dashicons ' + ic);
		$btn.find('.gruppa-icon-label').text(v ? v.replace(/^dashicons-/, '') : 'Default');
		$reset.prop('hidden', !v);
		$p.find('.gruppa-icon-opt').removeClass('is-current').filter('[data-icon="' + v + '"]').addClass('is-current');
	}
	function toggle(open) {
		$panel.prop('hidden', !open);
		$btn.attr('aria-expanded', open ? 'true' : 'false');
		if (open) $search.val('').trigger('input').trigger('focus');
	}
	$btn.on('click', function () { toggle($panel.prop('hidden')); });
	$p.on('click', '.gruppa-icon-opt', function () {
		$in.val($(this).data('icon') === def() ? '' : $(this).data('icon'));
		show(); toggle(false); $in.trigger('change');
	});
	$reset.on('click', function () { $in.val('').trigger('change'); show(); });
	$search.on('input', function () {
		var q = $.trim(this.value).toLowerCase();
		$p.find('.gruppa-icon-opt').each(function () {
			$(this).toggle(!q || $(this).attr('title').indexOf(q) > -1);
		});
	});
	$('#g-kind').on('change', show);
	$(document).on('mousedown keydown', function (e) {
		if ($panel.prop('hidden')) return;
		if (e.type === 'keydown' ? e.key === 'Escape' : !$(e.target).closest('.gruppa-icon-picker').length) toggle(false);
	});
});
