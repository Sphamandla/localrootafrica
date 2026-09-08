(function() {
	const source = document.getElementById('localroots-account-source');
	const widget = document.querySelector('.elementor-element-9b0b2c2');
	const container = widget ? widget.querySelector('.elementor-widget-container') : null;

	if (!source || !container) return;

	container.innerHTML = source.innerHTML;
	source.remove();

	if (widget) {
		widget.classList.add('localroots-account-ready');
	}

	const configEl = document.getElementById('localroots-account-config');
	if (!configEl) return;

	const config = JSON.parse(configEl.textContent);

	if (config.registerTab) {
		const registerTab = document.getElementById('register-tab');
		if (registerTab) registerTab.checked = true;
	}

	if (config.endpoint && config.endpoint !== 'dashboard') {
		document.body.classList.add('localroots-account-endpoint-' + config.endpoint);
	}
})();
