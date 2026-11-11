/* Gruppa Notify — field selects follow the chosen CCT. */
jQuery(function ($) {
	var cfg = window.gruppaNotifyAdmin || { fields: {} };
	var $cct = $('#gn-cct');
	if (!$cct.length) return;

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	function fill() {
		$('.gn-field-row').toggle($cct.val() !== '');
		var list = cfg.fields[$cct.val()] || [];
		$('.gn-field').each(function () {
			var $s = $(this), cur = $s.val() || $s.data('current'), html = '', found = false;
			list.forEach(function (col) {
				if (col === cur) found = true;
				html += '<option value="' + esc(col) + '"' + (col === cur ? ' selected' : '') + '>' + esc(col) + '</option>';
			});
			if (!found && cur) html = '<option value="' + esc(cur) + '" selected>' + esc(cur) + ' (missing)</option>' + html;
			$s.html(html);
		});
	}

	$cct.on('change', fill);
	fill();
});
