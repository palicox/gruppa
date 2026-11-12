jQuery(function ($) {
	// Title -> slug.
	var $name = $('#gi-title'), $slug = $('#gi-slug');
	function slugify(s) {
		return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
			.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
	}
	$slug.on('input', function () { $slug.attr('data-touched', '1'); });
	$name.on('input', function () { if (!$slug.attr('data-touched')) $slug.val(slugify($name.val())); });

	// Long checkbox lists (multiple Relation / Select): filter by text.
	$('.gruppa-choice-search').on('input', function () {
		var q = this.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		$(this).closest('.gruppa-choice').find('label').each(function () {
			var t = $(this).text().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
			$(this).toggle(!q || t.indexOf(q) !== -1 || $(this).find('input').is(':checked'));
		});
	}).on('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

	// Data link "One (select with search)": the search narrows the options (rebuilt, works in every browser).
	function fold(s) { return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
	$('.gruppa-select-search').each(function () {
		var $sel = $(this).siblings('select'), all = $sel.find('option').map(function () { return { v: this.value, t: this.text }; }).get();
		$(this).on('input', function () {
			var q = fold(this.value), cur = $sel.val();
			$sel.html(all.filter(function (o) { return !o.v || !q || o.v === cur || fold(o.t).indexOf(q) !== -1; }).map(function (o) {
				return $('<option>').val(o.v).text(o.t).prop('selected', o.v === cur);
			}));
			// One match: pick it right away.
			var hits = $sel.find('option').filter(function () { return this.value && this.value !== cur; });
			if (q && hits.length === 1 && !cur) $sel.val(hits.val());
		}).on('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
	});

	// Repeater: add / remove / drag rows.
	var rIdx = 0;
	function sortRows($rep) {
		$rep.find('.gruppa-repeater-rows').sortable({
			handle: '.gruppa-handle',
			axis: 'y',
			// A moved TinyMCE iframe loses its content: remove the editors while dragging, start them again after.
			start: function (e, ui) { removeMini(ui.item); },
			stop: function (e, ui) { initMini(ui.item); }
		});
	}
	$('.gruppa-repeater').each(function () { sortRows($(this)); });
	$(document).on('click', '.gruppa-repeater-add', function () {
		var $rep = $(this).closest('.gruppa-repeater');
		var html = $rep.children('.gruppa-repeater-tmpl').html().replace(/__r__/g, 'n' + Date.now() + '_' + (rIdx++));
		var $row = $(html);
		$rep.children('.gruppa-repeater-rows').append($row);
		$row.find('.gruppa-media').each(function () { initMedia($(this)); });
		initMini($row);
		sortRows($rep);
		$row.find('input, textarea, select').filter(':visible').first().trigger('focus');
	});
	$(document).on('click', '.gruppa-repeater-remove', function () {
		var $row = $(this).closest('.gruppa-repeater-row');
		removeMini($row);
		$row.remove();
	});

	// 0.33: Editor in a Repeater row — small WordPress editor (wp.editor) on a textarea.
	var miniN = 0;
	function initMini($scope) {
		if (!window.wp || !wp.editor || !wp.editor.initialize) return;
		$scope.find('textarea.gruppa-mini-editor').each(function () {
			if ($(this).closest('script').length) return;
			if (!this.id) this.id = 'gruppa-me-' + (miniN++);
			if ($(this).data('mini')) return;
			$(this).data('mini', 1);
			wp.editor.initialize(this.id, {
				tinymce: { wpautop: true, height: 140, toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink,undo,redo', toolbar2: '' },
				quicktags: true,
				mediaButtons: false
			});
		});
	}
	function removeMini($scope) {
		if (!window.wp || !wp.editor || !wp.editor.remove) return;
		$scope.find('textarea.gruppa-mini-editor').each(function () {
			if (!$(this).data('mini')) return;
			if (window.tinymce && tinymce.get(this.id)) tinymce.get(this.id).save();
			wp.editor.remove(this.id);
			$(this).data('mini', 0);
		});
	}
	$('.gruppa-repeater-rows').each(function () { initMini($(this)); });
	$('.gruppa-item-form').on('submit', function () { if (window.tinymce) tinymce.triggerSave(); });

	// 0.33: Icon field — Dashicons picker.
	$(document).on('click', '.gruppa-icon-field .gruppa-icon-btn', function () {
		var $p = $(this).closest('.gruppa-icon-field'), $panel = $p.find('.gruppa-icon-panel');
		var open = $panel.prop('hidden');
		$('.gruppa-icon-field .gruppa-icon-panel').prop('hidden', true);
		$('.gruppa-icon-field .gruppa-icon-btn').attr('aria-expanded', 'false');
		$panel.prop('hidden', !open);
		$(this).attr('aria-expanded', open ? 'true' : 'false');
		if (open) $p.find('.gruppa-icon-search').trigger('focus');
	});
	function setIcon($p, ic) {
		$p.find('.gruppa-icon-value').val(ic).trigger('change');
		$p.find('.gruppa-icon-btn .dashicons').attr('class', 'dashicons ' + (ic || 'dashicons-marker'));
		$p.find('.gruppa-icon-label').text(ic ? ic.replace(/^dashicons-/, '') : 'Choose icon');
		$p.find('.gruppa-icon-reset').prop('hidden', !ic);
		$p.find('.gruppa-icon-opt').removeClass('is-current').filter('[data-icon="' + ic + '"]').addClass('is-current');
	}
	$(document).on('click', '.gruppa-icon-field .gruppa-icon-opt', function () {
		var $p = $(this).closest('.gruppa-icon-field');
		setIcon($p, $(this).data('icon'));
		$p.find('.gruppa-icon-panel').prop('hidden', true);
		$p.find('.gruppa-icon-btn').attr('aria-expanded', 'false').trigger('focus');
	});
	$(document).on('click', '.gruppa-icon-field .gruppa-icon-reset', function () {
		setIcon($(this).closest('.gruppa-icon-field'), '');
	});
	$(document).on('input', '.gruppa-icon-field .gruppa-icon-search', function () {
		var q = $.trim(this.value).toLowerCase();
		$(this).closest('.gruppa-icon-field').find('.gruppa-icon-opt').each(function () {
			$(this).toggle(!q || String($(this).data('icon')).indexOf(q) > -1);
		});
	});
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.gruppa-icon-field').length) {
			$('.gruppa-icon-field .gruppa-icon-panel').prop('hidden', true);
			$('.gruppa-icon-field .gruppa-icon-btn').attr('aria-expanded', 'false');
		}
	});

	// 0.33: Conditions — a field is shown only when its conditions are met (same rules as on save).
	var $form = $('.gruppa-item-form');
	function fieldValue(key) {
		var $in = $form.find('[name="gf[' + key + ']"], [name="gf[' + key + '][]"]').not('[type="hidden"]');
		if (!$in.length) $in = $form.find('[name="gf[' + key + ']"]');
		if ($in.filter('[type="checkbox"]').length === 1 && $in.length === 1) return $in.prop('checked') ? '1' : '0'; // Switcher.
		if ($in.filter('[type="checkbox"], [type="radio"]').length) {
			return $in.filter(':checked').map(function () { return this.value; }).get().filter(function (v) { return v !== ''; });
		}
		if ($in.is('select[multiple]')) return ($in.val() || []).filter(function (v) { return v !== ''; });
		if ($in.is('textarea') && window.tinymce && tinymce.get($in.attr('id'))) return $.trim(tinymce.get($in.attr('id')).getContent({ format: 'text' }));
		return $.trim(String($in.val() || ''));
	}
	function check(c) {
		var v = fieldValue(c.field), w = String(c.value || ''), s = $.isArray(v) ? v.join(',') : v, n = parseFloat(s), m = parseFloat(w);
		switch (c.op) {
			case 'is': return $.isArray(v) ? v.indexOf(w) > -1 : s.toLowerCase() === w.toLowerCase();
			case 'is_not': return $.isArray(v) ? v.indexOf(w) === -1 : s.toLowerCase() !== w.toLowerCase();
			case 'contains': return w !== '' && s.toLowerCase().indexOf(w.toLowerCase()) > -1;
			case 'not_contains': return w === '' || s.toLowerCase().indexOf(w.toLowerCase()) === -1;
			case 'empty': return s === '' || s === '0';
			case 'not_empty': return s !== '' && s !== '0';
			case 'gt': return !isNaN(n) && !isNaN(m) && n > m;
			case 'lt': return !isNaN(n) && !isNaN(m) && n < m;
		}
		return true;
	}
	function applyConditions() {
		$form.find('tr[data-cond]').each(function () {
			var conds = $(this).data('cond') || [], res = null, join = 'and';
			$.each(conds, function (_, c) {
				var ok = check(c);
				res = res === null ? ok : (join === 'or' ? (res || ok) : (res && ok));
				join = c.join;
			});
			var show = res === null ? true : res;
			$(this).toggle(show);
			// Hidden fields are not required in the browser either (the server skips them too).
			$(this).find('[required], [data-was-required]').each(function () {
				if (!show && this.required) { $(this).attr('data-was-required', '1').prop('required', false); }
				else if (show && $(this).attr('data-was-required')) { $(this).prop('required', true).removeAttr('data-was-required'); }
			});
		});
	}
	if ($form.find('tr[data-cond]').length) {
		$form.on('input change', ':input', applyConditions);
		applyConditions();
	}

	// Media.
	function initMedia($box) {
		$box.find('.gruppa-media-list').sortable({ update: function () { sync($box); } });
		sync($box);
	}
	function sync($box) {
		var ids = $box.find('.gruppa-media-list li').map(function () { return $(this).data('id'); }).get();
		$box.find('input[type=hidden]').val(ids.join(','));
		var max = parseInt($box.data('max'), 10) || 0;
		$box.find('.gruppa-media-add').toggle(!max || max === 1 || ids.length < max);
	}

	$('.gruppa-media').each(function () { initMedia($(this)); });

	$(document).on('click', '.gruppa-media-remove', function () {
		var $box = $(this).closest('.gruppa-media');
		$(this).closest('li').remove();
		sync($box);
	});

	$(document).on('click', '.gruppa-media-add', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.gruppa-media');
		var max = parseInt($box.data('max'), 10) || 0;
		var mb = parseFloat($box.data('mb')) || 0;
		var types = String($box.data('types') || '').split(',').filter(Boolean);
		var mimes = $box.data('mimes') || [];
		var current = max === 1 ? 0 : $box.find('.gruppa-media-list li').length;

		var opts = { multiple: max === 1 ? false : 'add' };
		if (mimes.length) opts.library = { type: mimes };

		var frame = wp.media(opts);
		frame.on('select', function () {
			var errors = [];
			frame.state().get('selection').each(function (att) {
				var a = att.toJSON();
				if (max && current >= max) { errors.push('Max ' + max + ' files'); return; }
				if ($box.find('li[data-id="' + a.id + '"]').length) return;

				var ext = (a.filename || a.url || '').split('.').pop().toLowerCase();
				if (types.length && types.indexOf(ext) === -1) { errors.push(a.filename + ': file type'); return; }
				if (mb && a.filesizeInBytes && a.filesizeInBytes > mb * 1048576) { errors.push(a.filename + ': max ' + mb + ' MB'); return; }

				var thumb = a.type === 'image'
					? ((a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url)
					: a.icon;
				var $li = $('<li/>').attr('data-id', a.id)
					.append($('<img/>').attr('src', thumb).attr('alt', ''))
					.append($('<span class="gruppa-media-name"/>').text(a.filename))
					.append('<button type="button" class="gruppa-media-remove" aria-label="Remove">&times;</button>');

				if (max === 1) $box.find('.gruppa-media-list').empty();
				$box.find('.gruppa-media-list').append($li);
				current = $box.find('.gruppa-media-list li').length;
			});
			sync($box);
			if (errors.length) window.alert(errors.join('\n'));
		});
		frame.open();
	});
});
