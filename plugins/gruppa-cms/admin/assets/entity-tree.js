/* Gruppa CMS — Content Types / Data Types: the tree (= admin menu). Two levels: .is-root and .is-child rows. */
jQuery(function ($) {
	var $body = $('.gruppa-entity-tree');
	if (!$body.length || !$body.find('tr[data-slug]').length || !window.gruppaTree) return;

	var T = window.gruppaTree, $kids = $(), busy = false;

	function rows() { return $body.find('tr[data-slug]'); }
	function isChild($r) { return $r.hasClass('is-child'); }
	function setChild($r, on) { $r.toggleClass('is-child', on).toggleClass('is-root', !on); }
	function block($root) { return $root.nextUntil('.is-root'); } // Child rows below a level 1 row.

	/** Parents from the screen order: a child row belongs to the nearest level 1 row above it. */
	function normalize() {
		var root = '';
		rows().each(function () {
			var $r = $(this);
			if (isChild($r) && !root) setChild($r, false);
			if (isChild($r)) {
				$r.attr('data-parent', root);
			} else {
				root = String($r.data('slug'));
				$r.attr('data-parent', '');
			}
		});
		// The same entity twice under one parent → keep one row.
		var seen = {};
		rows().each(function () {
			var $r = $(this), k = $r.data('slug') + '|' + $r.attr('data-parent');
			if (seen[k]) $r.remove(); else seen[k] = true;
		});
	}

	/** Level 1 again: other rows of the same (shared) entity go away. */
	function toRoot($r) {
		rows().filter(function () { return String($(this).data('slug')) === String($r.data('slug')); }).not($r).remove();
		setChild($r, false);
	}

	function save() {
		if (busy) return;
		normalize();
		busy = true;
		$body.closest('table').addClass('is-saving');
		var data = rows().map(function () {
			return { slug: String($(this).data('slug')), parent: $(this).attr('data-parent') || '' };
		}).get();
		$.post(T.ajax, { action: 'gruppa_entity_tree', nonce: T.nonce, rows: data })
			.done(function (r) {
				if (r && r.success) { window.location.reload(); return; }
				fail();
			})
			.fail(fail);
	}

	function fail() {
		busy = false;
		$body.closest('table').removeClass('is-saving');
		window.alert(T.i18n.error);
	}

	$body.sortable({
		items: 'tr[data-slug]',
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
			// A level 1 row moves together with its children.
			$kids = isChild(ui.item) ? $() : block(ui.item).not(ui.placeholder).not(ui.helper);
			$kids.hide();
			$body.sortable('refreshPositions');
		},
		stop: function (e, ui) {
			var $it = ui.item;
			if (!isChild($it)) {
				var $n = $it.next(), $last = null;
				while ($n.length && isChild($n) && !$kids.is($n)) { $last = $n; $n = $n.next(); }
				if ($kids.length) {
					// Has children → stays level 1, after the block it landed in.
					if ($last) $it.insertAfter($last);
				} else if ($last) {
					setChild($it, true); // Dropped between children → one of them.
				}
				$kids.insertAfter($it).show();
			} else if (!$it.prevAll('tr[data-slug]').length) {
				toRoot($it); // A child dropped at the very top.
			}
			$kids = $();
			save();
		}
	});

	// → under the level 1 entity above.
	$body.on('click', '.gruppa-tree-in', function () {
		var $r = $(this).closest('tr');
		if (isChild($r)) return;
		if (block($r).length) { window.alert(T.i18n.hasKids); return; }
		if (!$r.prevAll('.is-root').length) { window.alert(T.i18n.noParent); return; }
		setChild($r, true);
		save();
	});

	// ← back to level 1 (after the block it was in).
	$body.on('click', '.gruppa-tree-out', function () {
		var $r = $(this).closest('tr');
		if (!isChild($r)) return;
		var $end = $r.nextUntil('.is-root').last();
		if ($end.length) $r.insertAfter($end);
		toRoot($r);
		save();
	});
});
