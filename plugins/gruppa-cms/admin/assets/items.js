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
		$rep.find('.gruppa-repeater-rows').sortable({ handle: '.gruppa-handle', axis: 'y' });
	}
	$('.gruppa-repeater').each(function () { sortRows($(this)); });
	$(document).on('click', '.gruppa-repeater-add', function () {
		var $rep = $(this).closest('.gruppa-repeater');
		var html = $rep.children('.gruppa-repeater-tmpl').html().replace(/__r__/g, 'n' + Date.now() + '_' + (rIdx++));
		var $row = $(html);
		$rep.children('.gruppa-repeater-rows').append($row);
		$row.find('.gruppa-media').each(function () { initMedia($(this)); });
		sortRows($rep);
		$row.find('input, textarea, select').filter(':visible').first().trigger('focus');
	});
	$(document).on('click', '.gruppa-repeater-remove', function () {
		$(this).closest('.gruppa-repeater-row').remove();
	});

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
