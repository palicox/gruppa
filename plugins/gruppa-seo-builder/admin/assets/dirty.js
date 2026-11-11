/* Gruppa SEO Builder — warn before leaving an edit screen with unsaved changes (same as Gruppa CMS dirty.js). */
(function ($) {
	var $forms = $('.gruppa-wrap form[method="post"], .gruppa-wrap form[method="POST"]').not('.gruppa-no-dirty');
	if (!$forms.length) return;

	var dirty = false, ready = false;
	function mark() { if (ready) dirty = true; }

	$forms.on('input change', 'input, select, textarea', mark);

	// Builders change the DOM (add / remove / reorder fields, actions, filters, media).
	if (window.MutationObserver) {
		var mo = new MutationObserver(mark);
		$forms.find('#gruppa-fields, #gfb-canvas, #gfa-list, #gfl-list, .gruppa-media-list, .gruppa-options-list').each(function () {
			mo.observe(this, { childList: true, subtree: true });
		});
	}
	$(document).on('sortupdate', mark);
	$(document).on('tinymce-editor-init', function (e, ed) { ed.on('input change undo redo', mark); });

	$forms.on('submit', function () { dirty = false; });
	$(window).on('load', function () { setTimeout(function () { ready = true; }, 300); });

	window.addEventListener('beforeunload', function (e) {
		if (!dirty) return;
		e.preventDefault();
		e.returnValue = '';
	});
})(jQuery);
