/* Gruppa CMS — Dynamic Forms (frontend) */
(function () {
	var L = window.gruppaFormL10n || {};
	function t(key, fallback) { return L[key] || fallback; }
	function fmt(str) { var a = Array.prototype.slice.call(arguments, 1), i = 0; return str.replace(/%(\d\$)?[sd]/g, function (m, pos) { return pos ? a[parseInt(pos, 10) - 1] : a[i++]; }); }
	function each(list, fn) { Array.prototype.forEach.call(list, fn); }

	function clearErrors(form) {
		each(form.querySelectorAll('.gruppa-form-field.has-error'), function (el) { el.classList.remove('has-error'); });
		each(form.querySelectorAll('.gruppa-form-error'), function (el) { el.hidden = true; el.textContent = ''; });
	}

	function notice(form, text, type) {
		var n = form.querySelector('.gruppa-form-notice');
		n.className = 'gruppa-form-notice is-' + type;
		n.textContent = text;
		n.hidden = !text;
	}

	function showErrors(form, fields) {
		var first = null, other = [];
		Object.keys(fields || {}).forEach(function (key) {
			var wrap = form.querySelector('.gruppa-form-field[data-key="' + key + '"]');
			if (!wrap) { if (key !== '_form') other.push(key + ': ' + fields[key]); return; }
			wrap.classList.add('has-error');
			var e = wrap.querySelector('.gruppa-form-error');
			e.textContent = fields[key];
			e.hidden = false;
			if (!first) first = wrap;
		});
		if (first) {
			var input = first.querySelector('input, select, textarea');
			if (input) input.focus();
		}
		return other;
	}

	function payload(form) {
		if (window.tinyMCE && tinyMCE.triggerSave) tinyMCE.triggerSave();
		var fd = new FormData(form);
		// Empty checkbox groups / multi selects: send "" so an update can clear them.
		each(form.querySelectorAll('.gruppa-form-field'), function (wrap) {
			var key = wrap.getAttribute('data-key');
			var multi = wrap.querySelectorAll('input[type=checkbox][name$="[]"], select[multiple]');
			if (!multi.length) return;
			var any = false;
			each(multi, function (el) {
				if (el.tagName === 'SELECT') { if (el.selectedOptions.length) any = true; }
				else if (el.checked) any = true;
			});
			if (!any) fd.append('gf[' + key + ']', '');
		});
		return fd;
	}

	/* ---------- Media ---------- */
	function syncMedia(box) {
		var ids = [];
		each(box.querySelectorAll('.gruppa-form-media-list li'), function (li) { ids.push(li.getAttribute('data-id')); });
		box.querySelector('.gruppa-form-media-keep').value = ids.join(',');
	}

	function bytes(n) {
		return n >= 1048576 ? (n / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB' : Math.round(n / 1024) + ' KB';
	}

	// Client-side check of chosen files; returns an error message or ''.
	function checkMedia(box) {
		var input = box.querySelector('.gruppa-form-file');
		var files = input && input.files ? Array.prototype.slice.call(input.files) : [];
		var max = parseInt(box.dataset.max, 10) || 0;
		var limit = parseInt(box.dataset.bytes, 10) || 0;
		var types = (box.dataset.types || '').split(',').filter(Boolean);
		var kept = box.querySelectorAll('.gruppa-form-media-list li').length;
		if (max > 1 && kept + files.length > max) return fmt(t('maxFiles', 'Max %d files.'), max);
		for (var i = 0; i < files.length; i++) {
			var ext = files[i].name.split('.').pop().toLowerCase();
			if (types.length && types.indexOf(ext) === -1) return fmt(t('fileType', '%s: file type not allowed.'), files[i].name);
			if (limit && files[i].size > limit) return fmt(t('fileSize', '%1$s: max %2$s.'), files[i].name, bytes(limit));
		}
		return '';
	}

	function showNames(box) {
		var input = box.querySelector('.gruppa-form-file'), out = box.querySelector('.gruppa-form-file-names');
		if (!out) return;
		var names = input && input.files ? Array.prototype.map.call(input.files, function (f) { return f.name; }) : [];
		out.textContent = names.length ? names.join(', ') : out.getAttribute('data-empty');
	}

	function resetForm(form) {
		form.reset();
		each(form.querySelectorAll('.gruppa-form-media'), showNames);
		if (window.tinyMCE) {
			each(form.querySelectorAll('textarea.wp-editor-area'), function (ta) {
				var ed = tinyMCE.get(ta.id);
				if (ed) ed.setContent('');
			});
		}
	}

	function init(form) {
		if (form.__gruppa) return;
		form.__gruppa = true;
		var btn = form.querySelector('.gruppa-form-button');

		form.addEventListener('click', function (ev) {
			var up = ev.target.closest && ev.target.closest('.gruppa-form-upload');
			if (up) {
				ev.preventDefault();
				var inp = up.closest('.gruppa-form-media').querySelector('.gruppa-form-file');
				if (inp) inp.click();
				return;
			}
			var rm = ev.target.closest && ev.target.closest('.gruppa-form-media-remove');
			if (!rm) return;
			var box = rm.closest('.gruppa-form-media');
			rm.closest('li').remove();
			syncMedia(box);
		});
		form.addEventListener('change', function (ev) {
			if (!ev.target.classList || !ev.target.classList.contains('gruppa-form-file')) return;
			var box = ev.target.closest('.gruppa-form-media');
			var wrap = box.closest('.gruppa-form-field');
			showNames(box);
			var msg = checkMedia(box);
			wrap.classList.toggle('has-error', !!msg);
			var e = wrap.querySelector('.gruppa-form-error');
			e.textContent = msg; e.hidden = !msg;
		});

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
			clearErrors(form);
			notice(form, '', 'info');

			// Native required checks (inputs only; checkbox groups are validated on the server).
			var bad = null;
			each(form.querySelectorAll('[required]'), function (el) {
				if (!bad && !el.checkValidity()) bad = el;
			});
			if (!bad) {
				each(form.querySelectorAll('.gruppa-form-media'), function (box) {
					var msg = checkMedia(box);
					if (!bad && msg) {
						var f = {}; f[box.closest('.gruppa-form-field').getAttribute('data-key')] = msg;
						showErrors(form, f);
						bad = true;
					}
				});
				if (bad) return;
			}
			if (bad) {
				var w = bad.closest('.gruppa-form-field');
				if (w) {
					var f = {}; f[w.getAttribute('data-key')] = bad.validationMessage || t('required', 'Required');
					showErrors(form, f);
				}
				return;
			}

			btn.disabled = true;
			form.classList.add('is-loading');
			var headers = {};
			if (form.dataset.nonce) headers['X-WP-Nonce'] = form.dataset.nonce;

			fetch(form.dataset.endpoint, { method: 'POST', body: payload(form), headers: headers, credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); })
				.then(function (res) {
					var d = res.data || {};
					if (!res.ok) {
						var fe = d.data && d.data.fields ? d.data.fields : null;
						var other = fe ? showErrors(form, fe) : [];
						// Errors of fields that are not shown (hidden elements) go into the notice.
						notice(form, ((fe && fe._form) || d.message || t('error', 'Error.')) + (other.length ? ' (' + other.join(', ') + ')' : ''), 'error');
						return;
					}
					if (d.redirect) { window.location.href = d.redirect; return; }
					var after = form.dataset.after;
					notice(form, d.message || form.dataset.success || '', 'success');
					if (after === 'reset') resetForm(form);
					if (after === 'hide') form.querySelector('.gruppa-form-fields').hidden = true, form.querySelector('.gruppa-form-submit').hidden = true;
					form.dispatchEvent(new CustomEvent('gruppa:submitted', { detail: d, bubbles: true }));
				})
				.catch(function () { notice(form, t('requestErr', 'Request failed. Please try again.'), 'error'); })
				.then(function () { btn.disabled = false; form.classList.remove('is-loading'); });
		});
	}

	// Gruppa Login: switch between "log in" and "lost password".
	document.addEventListener('click', function (ev) {
		var a = ev.target.closest && ev.target.closest('[data-gl-show]');
		if (!a) return;
		var box = a.closest('.gruppa-login');
		if (!box) return;
		ev.preventDefault();
		var view = a.getAttribute('data-gl-show');
		each(box.querySelectorAll('form[data-view]'), function (f) {
			f.hidden = f.getAttribute('data-view') !== view;
			if (!f.hidden) { var i = f.querySelector('input:not([type=hidden])'); if (i) i.focus(); }
		});
	});

	function boot() { each(document.querySelectorAll('form.gruppa-form'), init); }
	if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
	// Elementor editor / popups.
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && elementorFrontend.hooks) {
			elementorFrontend.hooks.addAction('frontend/element_ready/gruppa-form.default', boot);
		}
	});
	document.addEventListener('elementor/popup/show', boot);
})();
