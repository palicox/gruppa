jQuery(function ($) {
	var cfg = window.gruppaFormBuilder;
	if (!cfg) return;

	var TYPES = { text: 'Text', textarea: 'Textarea', editor: 'Editor', number: 'Number', date: 'Date', radio: 'Radio', select: 'Select', checkbox: 'Checkbox', switcher: 'Switcher', relation: 'Relation', media: 'Media upload' };
	var state = (cfg.layout || []).map(normalize);
	var selected = -1;

	var $type = $('#gfb-type');
	var $canvas = $('#gfb-canvas');
	var $add = $('#gfb-add-select');
	var $insp = $('#gfb-inspector');

	function normalize(e) {
		return $.extend({ key: '', width: 100, label: '', placeholder: '', description: '', hidden: false, value: '', display: '' }, e);
	}

	function defs() {
		return cfg.defs[$type.val()] || {};
	}

	function esc(v) {
		return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
		});
	}

	function renderAdd() {
		var d = defs(), used = {}, html = '', n = 0;
		state.forEach(function (e) { used[e.key] = true; });
		Object.keys(d).forEach(function (k) {
			if (used[k]) return;
			n++;
			html += '<option value="' + esc(k) + '">' + esc(d[k].label) + ' (' + esc(k) + ')</option>';
		});
		$add.html(html || '<option value="">' + ($type.val() ? 'All fields added' : 'Select type first') + '</option>');
		$('#gfb-add, #gfb-add-all, #gfb-add-select').prop('disabled', !n);
	}

	function renderCanvas(withInspector) {
		var d = defs();
		if (!state.length) {
			$canvas.html('<div class="gfb-empty">' + ($type.val() ? 'Add fields to build the form.' : 'Select a type first.') + '</div>');
		} else {
			$canvas.html(state.map(function (e, i) {
				var def = d[e.key] || { label: e.key, type: '?' };
				var label = e.label || def.label;
				return '<div class="gfb-el' + (i === selected ? ' is-selected' : '') + (e.hidden ? ' is-hidden' : '') + '" data-i="' + i + '" style="--gfb-w:' + e.width + '%"><div class="gfb-card">' +
					'<div class="gfb-el__head"><span class="dashicons dashicons-menu gfb-el__handle"></span>' +
					'<span class="gfb-el__label">' + esc(label) + (def.required ? ' <span class="gruppa-req">*</span>' : '') + '</span>' +
					'<span class="gfb-el__width">' + e.width + '%</span></div>' +
					'<div class="gfb-el__meta"><code>' + esc(e.key) + '</code> ' + esc(def.system ? 'System' : (def.note || TYPES[def.type] || def.type)) +
					(e.display && def.displays && def.displays[e.display] && Object.keys(def.displays)[0] !== e.display ? ' → ' + esc(def.displays[e.display]) : '') +
					(e.hidden ? ' · <strong>Hidden</strong>' : '') + '</div>' +
					'<div class="gfb-el__preview gfb-el__preview--' + esc(def.type) + '"></div>' +
					'</div></div>';
			}).join(''));
		}
		$canvas.append('<div class="gfb-submit-preview" data-align="' + esc($('#gfb-align').val()) + '"><span title="Submit button · preview (text and alignment in After Submit)">' + esc($('#gfb-submit').val() || 'Send') + '</span></div>');
		renderAdd();
		if (withInspector !== false) renderInspector();
		sync();
	}

	function renderInspector() {
		var e = state[selected];
		if (!e) { $insp.prop('hidden', true); return; }
		var def = defs()[e.key] || {};
		$insp.prop('hidden', false);
		$('#gfi-key').text(e.key);
		$('#gfi-type').text(def.system ? 'System' : (def.note || TYPES[def.type] || def.type || ''));
		$('#gfi-width').val(String(e.width));
		var ds = def.displays || {}, dk = Object.keys(ds);
		$('#gfi-display-row').toggle(dk.length > 1);
		$('#gfi-display').html(dk.map(function (k) { return '<option value="' + esc(k) + '">' + esc(ds[k]) + '</option>'; }).join(''))
			.val(e.display && ds[e.display] ? e.display : (dk[0] || ''));
		$('#gfi-label').val(e.label).attr('placeholder', def.label || '');
		var media = def.type === 'media';
		$('label[for="gfi-placeholder"]').text(media ? 'Button text' : 'Placeholder');
		$('label[for="gfi-description"]').text(media ? 'Note' : 'Description');
		$('#gfi-placeholder').attr('placeholder', media ? 'Choose files' : '');
		$('#gfi-description').attr('placeholder', media ? 'Automatic: file types · max size · max files' : '');
		$('#gfi-placeholder').val(e.placeholder);
		$('#gfi-description').val(e.description);
		$('#gfi-hidden').val(e.hidden ? '1' : '0');
		$('#gfi-value').val(Array.isArray(e.value) ? e.value.join(', ') : e.value);
		$('#gfi-value-label').text(e.hidden ? 'Value' : 'Default value');
	}

	function sync() {
		$('#gfb-layout-input').val(JSON.stringify(state));
	}

	function select(i) {
		selected = i;
		$canvas.find('.gfb-el').removeClass('is-selected').filter('[data-i="' + i + '"]').addClass('is-selected');
		renderInspector();
	}

	function add(key) {
		if (!key) return;
		state.push(normalize({ key: key }));
		selected = state.length - 1;
		renderCanvas();
	}

	// Canvas interactions.
	$canvas.on('click', '.gfb-el', function () { select(+$(this).data('i')); });
	$canvas.sortable({
		items: '> .gfb-el',
		handle: '.gfb-el__handle',
		tolerance: 'pointer',
		placeholder: 'gfb-el gfb-el--placeholder',
		forcePlaceholderSize: false,
		start: function (ev, ui) {
			ui.placeholder[0].style.setProperty('--gfb-w', ui.item[0].style.getPropertyValue('--gfb-w'));
			ui.placeholder.html('<div class="gfb-card"></div>');
		},
		update: function () {
			var cur = state[selected];
			state = $canvas.find('.gfb-el').map(function () { return state[+$(this).data('i')]; }).get();
			selected = state.indexOf(cur);
			renderCanvas();
		}
	});

	$('#gfb-add').on('click', function () { add($add.val()); });
	$('#gfb-add-all').on('click', function () {
		$add.find('option').each(function () { if (this.value) state.push(normalize({ key: this.value })); });
		renderCanvas();
	});

	// Inspector: text inputs on "input", selects on "change" (blur must not re-render mid-click).
	function bind(sel, prop, ev, fn) {
		$(sel).on(ev, function () {
			if (!state[selected]) return;
			state[selected][prop] = fn ? fn(this.value) : this.value;
			renderCanvas(prop === 'hidden');
		});
	}
	bind('#gfi-width', 'width', 'change', function (v) { return parseInt(v, 10) || 100; });
	bind('#gfi-hidden', 'hidden', 'change', function (v) { return v === '1'; });
	bind('#gfi-display', 'display', 'change', function (v) {
		var ds = (defs()[state[selected].key] || {}).displays || {};
		return v === Object.keys(ds)[0] ? '' : v;
	});
	bind('#gfi-label', 'label', 'input');
	bind('#gfi-placeholder', 'placeholder', 'input');
	bind('#gfi-description', 'description', 'input');
	bind('#gfi-value', 'value', 'input');

	$('#gfi-remove').on('click', function () {
		if (selected < 0) return;
		state.splice(selected, 1);
		selected = -1;
		renderCanvas();
	});

	// Type change resets the layout.
	var prevType = $type.val();
	$type.on('change', function () {
		if (state.length && !window.confirm('Changing the type clears the layout. Continue?')) {
			$type.val(prevType);
			return;
		}
		prevType = $type.val();
		state = [];
		selected = -1;
		renderCanvas();
	});

	// Mode → item row.
	$('#gfb-mode').on('change', function () {
		$('.gfb-update-row').toggle(this.value !== 'create');
		$('.gfb-create-row').toggle(this.value === 'create');
		$('.gfb-delete-row').toggle(this.value === 'delete');
		$('.gfb').closest('form').find('.gfb, .gfb-layout-title').toggle(this.value !== 'delete');
		// "Who can submit": only the options of this mode.
		var mode = this.value, $acc = $('#gfb-access');
		if ($acc.length) {
			var keep = $acc.find('option:selected').attr('data-mode') === mode;
			$acc.find('option').each(function () {
				var on = this.getAttribute('data-mode') === mode;
				this.hidden = !on; this.disabled = !on;
			});
			if (!keep) $acc.find('option[data-mode="' + mode + '"]').first().prop('selected', true);
		}
	}).trigger('change');
	$('#gfb-item-source').on('change', function () {
		$('#gfb-item-param').toggle(this.value === 'param');
	}).trigger('change');

	$('#gfb-submit').on('input', function () { renderCanvas(false); });
	$('#gfb-align').on('change', function () { renderCanvas(false); });

	// Name → slug.
	var $name = $('#g-name'), $slug = $('input#g-slug:not([readonly])');
	if ($slug.length) {
		$slug.on('input', function () { $slug.attr('data-touched', '1'); });
		$name.on('input', function () {
			if ($slug.attr('data-touched')) return;
			$slug.val(String($name.val()).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
				.replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40));
		});
	}

	$('#gruppa-form-form').on('submit', sync);

	renderCanvas();
});
