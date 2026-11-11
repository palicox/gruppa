/* Gruppa CMS — SEO Builder editor: counters, Google / Facebook preview, image picker */
jQuery(function ($) {
	/* ---------- Image fields ---------- */
	$(document).on('click', '.gseo-image-pick', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.gseo-image');
		var frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			$box.find('input').val(a.id).trigger('change');
			$box.find('img').attr('src', url);
			$box.addClass('has-image').data('full', a.url);
			update();
		});
		frame.open();
	});
	$(document).on('click', '.gseo-image-remove', function (e) {
		e.preventDefault();
		var $box = $(this).closest('.gseo-image');
		$box.find('input').val('0').trigger('change');
		$box.find('img').attr('src', '');
		$box.removeClass('has-image');
		update();
	});

	var cfg = window.gruppaSEO;
	if (!cfg) return;

	/* ---------- Counters ---------- */
	function count($el) {
		var max = parseInt($el.data('count'), 10), n = ($el.val() || '').length;
		var $c = $('.gseo-count[data-for="' + $el.attr('id') + '"]');
		$c.text(n + ' / ' + max).toggleClass('is-over', n > max).toggleClass('is-good', n >= max * 0.5 && n <= max);
	}

	/* ---------- Previews ---------- */
	function owner() {
		var k = $('#gseo-owner').val();
		return cfg.owners[k] || null;
	}

	function autoTitle(o) {
		if (!o) return '';
		return cfg.template.replace('{title}', o.title).replace('{sep}', cfg.sep).replace('{site}', cfg.site).replace('{type}', o.type).trim();
	}

	function cut(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1).trim() + '…' : s; }

	function update() {
		var o = owner();
		var url = (o && o.url) || cfg.home;
		var canonical = $('#gseo-canonical').val() || url;
		var title = $('#gseo-title').val() || autoTitle(o) || cfg.site;
		var desc = $('#gseo-desc').val();
		$('#gseo-title').attr('placeholder', autoTitle(o) ? 'Empty = ' + autoTitle(o) : '');

		var u;
		try { u = new URL(canonical); } catch (e) { u = null; }
		var crumbs = u ? (u.pathname.split('/').filter(Boolean).join(' › ')) : '';
		$('.gseo-g-name').text(cfg.site);
		$('.gseo-g-url').text(u ? u.origin + (crumbs ? ' › ' + crumbs : '') : canonical);
		$('.gseo-g-title').text(cut(title, 62));
		$('.gseo-g-desc').text(desc ? cut(desc, 158) : 'Google will pick text from the page. Write a description to control it.').toggleClass('is-empty', !desc);
		var noindex = String($('#gseo-robots').val()).indexOf('noindex') === 0;
		$('.gseo-google').toggleClass('is-noindex', noindex);
		$('.gseo-g-noindex').prop('hidden', !noindex);

		var ogT = $('#gseo-og-title').val() || title;
		var ogD = $('#gseo-og-desc').val() || desc;
		var $img = $('input[name="seo_og_image"]').closest('.gseo-image');
		var img = $img.hasClass('has-image') ? $img.find('img').attr('src') : cfg.defaultImg;
		$('.gseo-fb-domain').text(u ? u.host.toUpperCase() : '');
		$('.gseo-fb-title').text(cut(ogT, 88));
		$('.gseo-fb-desc').text(cut(ogD, 120));
		$('.gseo-fb-img').css('background-image', img ? 'url("' + img + '")' : 'none').toggleClass('is-empty', !img).text(img ? '' : 'No image');
	}

	$('[data-count]').each(function () {
		var $el = $(this);
		count($el);
		$el.on('input', function () { count($el); });
	});
	$('.gseo-form').on('input change', 'input, textarea, select', update);
	update();
});
