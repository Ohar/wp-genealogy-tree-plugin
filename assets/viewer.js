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
})();
