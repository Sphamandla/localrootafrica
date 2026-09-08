(function() {
	const source = document.getElementById('localroots-contact-source');
	if (!source) return;

	const formHost = document.querySelector('.elementor-element-26668ab .elementor-widget-container');
	const formSource = source.querySelector('[data-contact-form]');
	if (!formHost || !formSource) return;

	formHost.innerHTML = formSource.innerHTML;
	source.remove();
})();
