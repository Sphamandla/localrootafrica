(function() {
	const source = document.getElementById('localroots-order-status-source');
	if (!source) return;

	const formHost = document.querySelector('.elementor-element-0508bce .elementor-widget-container .woocommerce');
	const formSource = source.querySelector('[data-order-status-form]');
	const resultsSource = source.querySelector('[data-order-status-results]');
	const bodyEl = document.querySelector('.elementor-element-6bd4fae .elementor-widget-container');
	const body = source.querySelector('[data-order-status-body]');

	if (formHost && formSource) {
		formHost.innerHTML = formSource.innerHTML;
	}

	if (bodyEl && body && body.innerHTML.trim()) {
		bodyEl.innerHTML = body.innerHTML;
	}

	if (resultsSource && formHost) {
		formHost.insertAdjacentHTML('afterend', resultsSource.innerHTML);
	}

	source.remove();
})();
