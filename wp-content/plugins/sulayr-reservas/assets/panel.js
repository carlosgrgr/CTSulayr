(function () {
	var card = document.querySelector('.sulayr-menu');
	if (card) {
		var toggle = card.querySelector('.sulayr-nav-toggle');
		var menu = card.querySelector('.sulayr-nav');
		var lastWidth = -1;

		function setOpen(open) {
			card.classList.toggle('is-open', open);
			if (!toggle) {
				return;
			}
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
		}

		function menuNeedsFold() {
			var styles = window.getComputedStyle(menu);
			var gap = parseFloat(styles.columnGap || styles.gap) || 0;
			var links = menu.querySelectorAll('.sulayr-nav-link');
			var total = 0;
			var index;
			for (index = 0; index < links.length; index++) {
				total += links[index].getBoundingClientRect().width;
			}
			if (links.length > 1) {
				total += gap * (links.length - 1);
			}
			return total > menu.clientWidth + 1;
		}

		function layoutNav() {
			if (!menu) {
				return;
			}
			var width = card.clientWidth;
			if (width === lastWidth) {
				return;
			}
			lastWidth = width;
			var wasOpen = card.classList.contains('is-open');
			card.classList.add('is-ready');
			card.classList.remove('is-collapsed');
			setOpen(false);
			var overflows = menuNeedsFold();
			if (overflows) {
				card.classList.add('is-collapsed');
				if (wasOpen) {
					setOpen(true);
				}
			}
		}

		if (toggle) {
			toggle.addEventListener('click', function () {
				setOpen(!card.classList.contains('is-open'));
			});
		}

		document.addEventListener('click', function (event) {
			if (!card.classList.contains('is-open') || card.contains(event.target)) {
				return;
			}
			setOpen(false);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && card.classList.contains('is-open')) {
				setOpen(false);
				if (toggle) {
					toggle.focus();
				}
			}
		});

		layoutNav();
		window.addEventListener('resize', layoutNav);
	}

	var root = document.querySelector('.sulayr-mobile-cal');
	if (!root) {
		return;
	}

	var stage = root.querySelector('.sulayr-mobile-stage');
	var list = root.querySelector('.sulayr-week');
	var prev = root.querySelector('.sulayr-day-prev');
	var next = root.querySelector('.sulayr-day-next');
	var days = Array.prototype.slice.call(root.querySelectorAll('.sulayr-mobile-day'));
	var selected = root.getAttribute('data-selected') || '';
	var today = root.getAttribute('data-today') || '';

	function pad(value) {
		return value < 10 ? '0' + value : String(value);
	}

	function shift(iso, amount) {
		var parts = iso.split('-');
		var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
		date.setUTCDate(date.getUTCDate() + amount);
		return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
	}

	function mondayOf(iso) {
		var parts = iso.split('-');
		var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2])));
		var day = date.getUTCDay();
		var distance = day === 0 ? 6 : day - 1;
		return shift(iso, -distance);
	}

	function dayNode(iso) {
		for (var index = 0; index < days.length; index++) {
			if (days[index].getAttribute('data-date') === iso) {
				return days[index];
			}
		}
		return null;
	}

	function urlFor(iso) {
		var url = new URL(window.location.href);
		url.searchParams.set('sulayr_dia', iso);
		return url.toString();
	}

	function renderWeek(iso) {
		if (!list) {
			return;
		}
		var monday = mondayOf(iso);
		list.textContent = '';
		for (var index = 0; index < 7; index++) {
			var current = shift(monday, index);
			var node = dayNode(current);
			var on = current === iso;
			var isToday = current === today;
			var tab = document.createElement('a');
			var name = document.createElement('span');
			var number = document.createElement('span');
			tab.className = 'sulayr-week-day' + (on ? ' is-selected' : '') + (isToday ? ' is-today' : '');
			tab.setAttribute('role', 'tab');
			tab.href = urlFor(current);
			tab.setAttribute('data-date', current);
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			tab.tabIndex = on ? 0 : -1;
			if (isToday) {
				tab.setAttribute('aria-current', 'date');
			}
			name.className = 'sulayr-week-name';
			name.textContent = node ? node.getAttribute('data-weekday') || '' : '';
			number.className = 'sulayr-week-num';
			number.textContent = node ? node.getAttribute('data-day') || '' : String(Number(current.slice(8)));
			tab.appendChild(name);
			tab.appendChild(number);
			if (isToday) {
				var mark = document.createElement('span');
				mark.className = 'sulayr-week-today';
				mark.textContent = 'Hoy';
				tab.appendChild(mark);
			}
			list.appendChild(tab);
		}
	}

	function focusSelected() {
		var tab = list ? list.querySelector('.is-selected') : null;
		if (tab) {
			tab.focus();
		}
	}

	function show(iso, moveFocus) {
		if (!dayNode(iso)) {
			window.location.href = urlFor(iso);
			return;
		}
		days.forEach(function (day) {
			day.hidden = day.getAttribute('data-date') !== iso;
		});
		selected = iso;
		root.setAttribute('data-selected', iso);
		renderWeek(iso);
		if (prev) {
			prev.setAttribute('href', urlFor(shift(iso, -1)));
		}
		if (next) {
			next.setAttribute('href', urlFor(shift(iso, 1)));
		}
		if (moveFocus) {
			focusSelected();
		}
	}

	function interactive(target) {
		return !!(target && target.closest && target.closest('a, button, input, select, textarea, label, form'));
	}

	root.addEventListener('click', function (event) {
		var tab = event.target.closest ? event.target.closest('.sulayr-week-day') : null;
		if (tab && list && list.contains(tab)) {
			event.preventDefault();
			show(tab.getAttribute('data-date'), false);
			return;
		}
		if (prev && (event.target === prev || prev.contains(event.target))) {
			event.preventDefault();
			show(shift(selected, -1), false);
			return;
		}
		if (next && (event.target === next || next.contains(event.target))) {
			event.preventDefault();
			show(shift(selected, 1), false);
		}
	});

	if (list) {
		list.addEventListener('keydown', function (event) {
			if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
				return;
			}
			event.preventDefault();
			show(shift(selected, event.key === 'ArrowRight' ? 1 : -1), true);
		});
	}

	if (stage) {
		var startX = 0;
		var startY = 0;
		var tracking = false;
		stage.addEventListener('touchstart', function (event) {
			if (event.touches.length !== 1 || interactive(event.target)) {
				tracking = false;
				return;
			}
			tracking = true;
			startX = event.touches[0].clientX;
			startY = event.touches[0].clientY;
		}, { passive: true });
		stage.addEventListener('touchend', function (event) {
			if (!tracking || !event.changedTouches.length) {
				return;
			}
			var dx = event.changedTouches[0].clientX - startX;
			var dy = event.changedTouches[0].clientY - startY;
			tracking = false;
			if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.2) {
				return;
			}
			show(shift(selected, dx < 0 ? 1 : -1), false);
		}, { passive: true });
	}

	show(selected, false);
})();
