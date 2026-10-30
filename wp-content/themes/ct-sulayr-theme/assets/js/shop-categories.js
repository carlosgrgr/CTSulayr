(function () {
	function stacked() {
		return document.body.classList.contains('ast-header-break-point');
	}

	function panelOf(widget) {
		return widget.querySelector('.wp-widget-group__inner-blocks, ul.product-categories, .wc-block-product-categories');
	}

	function prepare(widget) {
		var title = widget.querySelector('.widget-title');
		var panel = panelOf(widget);
		if (!title || !panel) {
			return null;
		}
		panel.classList.add('sulayr-cat-panel');
		if (!panel.id) {
			panel.id = (widget.id || 'sulayr-cat') + '-panel';
		}
		return { title: title, panel: panel };
	}

	function sync() {
		var isStacked = stacked();
		document.querySelectorAll('#secondary .widget').forEach(function (widget) {
			var parts = prepare(widget);
			if (!parts) {
				return;
			}
			if (isStacked) {
				parts.title.setAttribute('role', 'button');
				parts.title.tabIndex = 0;
				parts.title.setAttribute('aria-controls', parts.panel.id);
				parts.title.setAttribute('aria-expanded', widget.classList.contains('is-open') ? 'true' : 'false');
			} else {
				widget.classList.remove('is-open');
				parts.title.removeAttribute('role');
				parts.title.removeAttribute('tabindex');
				parts.title.removeAttribute('aria-controls');
				parts.title.removeAttribute('aria-expanded');
			}
		});
	}

	function toggle(title) {
		if (!stacked()) {
			return;
		}
		var widget = title.closest('.widget');
		if (!widget || !prepare(widget)) {
			return;
		}
		var open = widget.classList.toggle('is-open');
		title.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	document.addEventListener('click', function (event) {
		var title = event.target.closest('#secondary .widget-title');
		if (title) {
			toggle(title);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		var title = event.target.closest('#secondary .widget-title');
		if (!title || title.getAttribute('role') !== 'button') {
			return;
		}
		event.preventDefault();
		toggle(title);
	});

	if (window.MutationObserver) {
		new MutationObserver(sync).observe(document.body, { attributes: true, attributeFilter: ['class'] });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', sync);
	} else {
		sync();
	}
})();
