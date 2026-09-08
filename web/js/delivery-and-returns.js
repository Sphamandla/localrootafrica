(function() {
	const source = document.getElementById('localroots-delivery-source');
	if (!source) return;

	const bodyEl = document.querySelector('.elementor-element-6bd4fae .elementor-widget-container');
	const body = source.querySelector('[data-delivery-body]');

	if (bodyEl && body && body.innerHTML.trim()) {
		bodyEl.innerHTML = body.innerHTML;
	}

	source.remove();
})();
