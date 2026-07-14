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
		var pointers = {};
		var pinch = null;
		var zoom = 1;

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
		frameDocument.documentElement.style.touchAction = 'none';

		function getPointerPair() {
			var pointerIds = Object.keys(pointers);

			if (pointerIds.length < 2) {
				return null;
			}

			return [pointers[pointerIds[0]], pointers[pointerIds[1]]];
		}

		function getDistance(firstPointer, secondPointer) {
			var x = secondPointer.x - firstPointer.x;
			var y = secondPointer.y - firstPointer.y;

			return Math.sqrt(x * x + y * y);
		}

		function clampZoom(value) {
			return Math.max(0.5, Math.min(2.5, value));
		}

		function applyZoom(nextZoom, centerX, centerY) {
			var documentX = (frameWindow.scrollX + centerX) / zoom;
			var documentY = (frameWindow.scrollY + centerY) / zoom;

			zoom = clampZoom(nextZoom);
			frameDocument.documentElement.style.zoom = zoom;
			frameWindow.scrollTo(documentX * zoom - centerX, documentY * zoom - centerY);
		}

		frameDocument.addEventListener('pointerdown', function (event) {
			if (event.button !== 0 || (event.pointerType !== 'touch' && isInteractiveElement(event.target))) {
				return;
			}

			pointers[event.pointerId] = {
				x: event.clientX,
				y: event.clientY
			};

			if (getPointerPair()) {
				drag = null;
				pinch = null;
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
			var pointerPair;
			var distance;
			var centerX;
			var centerY;

			if (pointers[event.pointerId]) {
				pointers[event.pointerId].x = event.clientX;
				pointers[event.pointerId].y = event.clientY;
			}

			pointerPair = getPointerPair();
			if (pointerPair) {
				distance = getDistance(pointerPair[0], pointerPair[1]);
				centerX = (pointerPair[0].x + pointerPair[1].x) / 2;
				centerY = (pointerPair[0].y + pointerPair[1].y) / 2;

				if (!pinch) {
					pinch = {
						distance: distance,
						zoom: zoom
					};
				} else if (pinch.distance > 0) {
					applyZoom(pinch.zoom * distance / pinch.distance, centerX, centerY);
				}

				event.preventDefault();
				return;
			}

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
			delete pointers[event.pointerId];
			pinch = null;

			if (!drag || drag.pointerId !== event.pointerId) {
				return;
			}

			suppressClick = drag.moved;
			drag = null;
		});

		frameDocument.addEventListener('pointercancel', function (event) {
			delete pointers[event.pointerId];
			pinch = null;
			drag = null;
		});

		frameDocument.addEventListener('wheel', function (event) {
			if (!event.ctrlKey && !event.metaKey) {
				return;
			}

			applyZoom(zoom * Math.exp(-event.deltaY * 0.0015), event.clientX, event.clientY);
			event.preventDefault();
		}, { passive: false });

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
