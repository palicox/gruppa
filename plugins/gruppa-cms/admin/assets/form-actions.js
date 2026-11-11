/* Gruppa CMS — Dynamic Forms editor: Actions */
jQuery(function ($) {
	var $list = $('#gfa-list');
	if (!$list.length) return;

	var cfg = window.gruppaFormBuilder || {};
	var TF = cfg.typeFields || {};
	var $type = $('#gfb-type');
	var index = window.gruppaFormActionsIndex || 0;

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	function fields(slug) { return TF[slug || $type.val()] || []; }

	function options(list, cur, empty) {
		var html = '<option value="">' + esc(empty) + '</option>', found = !cur;
		list.forEach(function (f) {
			if (f.key === cur) found = true;
			html += '<option value="' + esc(f.key) + '"' + (f.key === cur ? ' selected' : '') + '>' + esc(f.label + ' (' + f.key + ')') + '</option>';
		});
		if (!found) html += '<option value="' + esc(cur) + '" selected>' + esc(cur) + ' (missing)</option>';
		return html;
	}

	// Form-type field selects.
	function fillFields($scope) {
		var all = fields();
		$scope.find('select.gfa-field').each(function () {
			var $s = $(this), kind = $s.data('kind'), cur = $s.val() || String($s.data('current') || '');
			var list = all.filter(function (f) {
				if (kind === 'relation') return f.type === 'relation';
				if (kind === 'text') return f.type === 'text' || f.type === 'textarea';
				return f.type !== 'status' || true;
			});
			$s.html(options(list, cur, $s.data('empty') || '— select field —'));
		});
		$scope.find('.gfa-card').each(function () { fillTarget($(this)); });
		var keys = all.filter(function (f) { return f.type !== 'status'; }).map(function (f) { return '{' + f.key + '}'; });
		$scope.find('.gfa-ph-fields').text(keys.length ? ' · Fields: ' + keys.join(' ') : '');
	}

	// "Update related item": fields of the related type.
	function fillTarget($card) {
		var $t = $card.find('select.gfa-target');
		if (!$t.length) return;
		var relKey = $card.find('select.gfa-field[data-kind="relation"]').val();
		var rel = fields().filter(function (f) { return f.key === relKey; })[0];
		var list = rel && rel.related ? fields(rel.related).filter(function (f) { return f.key !== 'title' && f.type !== 'media' && f.type !== 'relation'; }) : [];
		var cur = $t.val() || String($t.data('current') || '');
		$t.html(options(list, cur, rel ? '— select —' : '— choose Related item first —'));
	}

	function summary($card) {
		var t = $card.data('type'), s = '';
		if (t === 'email') s = $card.find('[name$="[to]"]').val();
		if (t === 'webhook') s = $card.find('[name$="[url]"]').val();
		if (t === 'update_item') {
			var r = $card.find('select.gfa-field[data-kind="relation"]').val(), g = $card.find('select.gfa-target').val();
			s = r && g ? r + ' → ' + g : '';
		}
		if (t === 'register') s = 'role: ' + $card.find('[name$="[role]"]').val();
		// Add-on actions (Payments, Notify…): keep the summary rendered by PHP.
		if (['email', 'webhook', 'update_item', 'register'].indexOf(t) !== -1) $card.find('.gfa-summary').text(s || '');
		var title = $card.find('[name$="[title]"]').val();
		$card.find('.gruppa-field-title').text(title || $card.find('.gruppa-field-type').text());
	}

	// Add.
	$('#gfa-add').on('click', function () {
		var t = $('#gfa-add-type').val();
		var html = $('#tmpl-gfa-' + t).html();
		if (!html) return;
		var $card = $(html.replace(/__i__/g, 'n' + (index++)));
		$list.find('.gfa-card').removeClass('is-open');
		$list.append($card);
		fillFields($card);
		summary($card);
	});

	// Toggle open (not when clicking the enable switch).
	$list.on('click', '.gruppa-field-head', function (e) {
		if ($(e.target).closest('.gfa-enabled').length) return;
		$(this).closest('.gfa-card').toggleClass('is-open');
	});
	$list.on('change', '.gfa-enabled input[type=checkbox]', function () {
		$(this).closest('.gfa-card').toggleClass('is-disabled', !this.checked);
	});

	$list.on('click', '.gfa-remove', function () {
		if (window.confirm('Remove this action?')) $(this).closest('.gfa-card').remove();
	});

	// Conditions.
	$list.on('click', '.gfa-cond-add', function () {
		var $card = $(this).closest('.gfa-card');
		var i = $card.data('index');
		var j = 'c' + Date.now();
		var $c = $($('#tmpl-gfa-cond').html().replace(/__i__/g, i).replace(/__j__/g, j));
		$card.find('.gfa-cond-list').append($c);
		fillFields($c);
		$c.find('.gfa-op').trigger('change');
	});
	$list.on('click', '.gfa-cond-remove', function () { $(this).closest('.gfa-cond').remove(); });
	$list.on('change', '.gfa-op', function () {
		var v = $(this).val();
		$(this).siblings('input[type=text]').toggle(v !== 'empty' && v !== 'not_empty');
	});

	$list.on('change', 'select.gfa-field[data-kind="relation"]', function () {
		var $card = $(this).closest('.gfa-card');
		$card.find('select.gfa-target').val('').data('current', '');
		fillTarget($card);
	});
	$list.on('input change', 'input, select', function () { summary($(this).closest('.gfa-card')); });

	$list.sortable({ items: '> .gfa-card', handle: '.gruppa-handle', placeholder: 'gruppa-field-placeholder', tolerance: 'pointer' });

	$type.on('change', function () { fillFields($list); });

	fillFields($list);
	$list.find('.gfa-op').trigger('change');
	$list.find('.gfa-card').each(function () { summary($(this)); });
});
