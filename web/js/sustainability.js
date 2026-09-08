(function() {
	const source = document.getElementById('localroots-sustainability-source');
	if (!source) return;

	const bodyEl = document.querySelector('.elementor-element-95016e6 .elementor-widget-container');
	const body = source.querySelector('[data-sustainability-body]');

	if (bodyEl && body && body.innerHTML.trim()) {
		bodyEl.innerHTML = body.innerHTML;
	}

	source.remove();

	if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
		document.querySelectorAll('.elementor-element-95016e6, .elementor-element-3c862e5').forEach(function(widget) {
			elementorFrontend.elementsHandler.runReadyTrigger(widget);
		});
	}
})();
