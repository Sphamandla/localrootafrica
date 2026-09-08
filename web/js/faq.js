(function() {
	const sources = document.getElementById('localroots-faq-sources');
	if (!sources) return;

	sources.querySelectorAll('[data-widget-id]').forEach(function(source) {
		const widgetId = source.getAttribute('data-widget-id');
		const toggle = document.querySelector('.elementor-element-' + widgetId + ' .elementor-toggle');
		if (!toggle || !source.innerHTML.trim()) return;
		toggle.innerHTML = source.innerHTML;
	});

	sources.remove();

	if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
		document.querySelectorAll('.elementor-widget-toggle').forEach(function(widget) {
			elementorFrontend.elementsHandler.runReadyTrigger(widget);
		});
	}

	const tocWidget = document.querySelector('.elementor-widget-table-of-contents');
	if (tocWidget && typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
		elementorFrontend.elementsHandler.runReadyTrigger(tocWidget);
	}
})();
