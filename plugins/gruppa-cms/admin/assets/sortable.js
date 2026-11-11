jQuery(function ($) {
	$('.gruppa-sortable').each(function () {
		var $body = $(this);
		if (!$body.find('tr[data-id]').length) return;

		$body.sortable({
			items: 'tr[data-id]',
			handle: '.gruppa-handle',
			axis: 'y',
			placeholder: 'gruppa-placeholder',
			helper: function (e, tr) {
				var $orig = tr.children(), $h = tr.clone();
				$h.children().each(function (i) { $(this).width($orig.eq(i).width()); });
				return $h;
			},
			start: function (e, ui) {
				ui.placeholder.html('<td colspan="' + ui.item.children().length + '"></td>');
			},
			update: function () {
				var ids = $body.find('tr[data-id]').map(function () { return $(this).data('id'); }).get();
				$.post(gruppaSort.ajax, {
					action: 'gruppa_reorder',
					nonce: gruppaSort.nonce,
					key: $body.data('key'),
					offset: $body.data('offset') || 0,
					ids: ids
				}).done(function () {
					if ($body.data('key') === 'types') window.location.reload();
				});
			}
		});
	});

	$(document).on('click', '.gruppa-delete', function (e) {
		if (this.hasAttribute('onclick')) return; // Asks with its own message.
		if (!window.confirm('Delete?')) e.preventDefault();
	});

	// Items list: select all + bulk actions.
	$(document).on('change', '.gruppa-check-all', function () {
		$(this).closest('table').find('tbody input[name="ids[]"]').prop('checked', this.checked);
	});
	$(document).on('submit', '.gruppa-bulk-form', function (e) {
		var $f = $(this), act = $f.find('[name="bulk_action"]').val(), n = $f.find('input[name="ids[]"]:checked').length;
		if (!act || !n) { e.preventDefault(); window.alert(!n ? 'Select at least one item.' : 'Choose a bulk action.'); return; }
		if (act === 'delete' && !window.confirm('Delete ' + n + ' item(s)?')) e.preventDefault();
	});
});
