(function () {
	function closestTree(element) {
		while (element && !element.classList.contains('drevo-genealogy-tree')) {
			element = element.parentElement;
		}

		return element;
	}

	function updateLabels() {
		var isFullscreen = document.fullscreenElement !== null || document.querySelector('.drevo-genealogy-tree.is-expanded') !== null;
		document.querySelectorAll('[data-drevo-fullscreen]').forEach(function (button) {
			button.textContent = isFullscreen ? 'Выйти из полного экрана' : 'На весь экран';
		});
	}

	function collapseExpandedTrees() {
		document.querySelectorAll('.drevo-genealogy-tree.is-expanded').forEach(function (tree) {
			tree.classList.remove('is-expanded');
		});
	}

	function isInteractiveElement(element) {
		return element && element.closest('button, input, select, textarea, summary, label, [contenteditable="true"]');
	}

	function enableDragScroll(frame) {
		var frameDocument;
		var frameWindow;
		var drag = null;
		var suppressClick = false;

		try {
			frameDocument = frame.contentDocument;
			frameWindow = frame.contentWindow;
		} catch (error) {
			return;
		}

		if (!frameDocument || !frameWindow || frameDocument.documentElement.dataset.drevoDragScroll) {
			return;
		}

		frameDocument.documentElement.dataset.drevoDragScroll = 'enabled';

		frameDocument.addEventListener('pointerdown', function (event) {
			if (event.button !== 0 || isInteractiveElement(event.target)) {
				return;
			}

			drag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				scrollX: frameWindow.scrollX,
				scrollY: frameWindow.scrollY,
				moved: false
			};

			if (event.target.setPointerCapture) {
				event.target.setPointerCapture(event.pointerId);
			}
		});

		frameDocument.addEventListener('pointermove', function (event) {
			var deltaX;
			var deltaY;

			if (!drag || drag.pointerId !== event.pointerId) {
				return;
			}

			deltaX = event.clientX - drag.startX;
			deltaY = event.clientY - drag.startY;
			if (!drag.moved && Math.abs(deltaX) < 4 && Math.abs(deltaY) < 4) {
				return;
			}

			drag.moved = true;
			frameWindow.scrollTo(drag.scrollX - deltaX, drag.scrollY - deltaY);
			event.preventDefault();
		});

		frameDocument.addEventListener('pointerup', function (event) {
			if (!drag || drag.pointerId !== event.pointerId) {
				return;
			}

			suppressClick = drag.moved;
			drag = null;
		});

		frameDocument.addEventListener('pointercancel', function () {
			drag = null;
		});

		frameDocument.addEventListener('click', function (event) {
			if (!suppressClick) {
				return;
			}

			suppressClick = false;
			event.preventDefault();
			event.stopPropagation();
		}, true);
	}

	function enableDragScrollForFrames() {
		document.querySelectorAll('.drevo-genealogy-frame').forEach(function (frame) {
			frame.addEventListener('load', function () {
				enableDragScroll(frame);
			});

			try {
				if (frame.contentDocument && frame.contentDocument.readyState === 'complete') {
					enableDragScroll(frame);
				}
			} catch (error) {
				// A tree served from another origin cannot be controlled by the parent page.
			}
		});
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-drevo-fullscreen]');
		if (!button) {
			return;
		}

		var tree = closestTree(button);
		if (!tree) {
			return;
		}

		if (document.fullscreenElement) {
			document.exitFullscreen();
		} else if (tree.classList.contains('is-expanded')) {
			tree.classList.remove('is-expanded');
			updateLabels();
		} else if (tree.requestFullscreen) {
			tree.requestFullscreen().catch(function () {
				tree.classList.add('is-expanded');
				updateLabels();
			});
		} else {
			tree.classList.add('is-expanded');
			updateLabels();
		}
	});

	document.addEventListener('fullscreenchange', function () {
		if (document.fullscreenElement) {
			collapseExpandedTrees();
		}

		updateLabels();
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			collapseExpandedTrees();
			updateLabels();
		}
	});

	enableDragScrollForFrames();
})();
