(function() {
	const source = document.getElementById('localroots-cart-source');
	if (!source) return;

	const widget = document.querySelector('.elementor-element-b3b4143 .woocommerce');
	if (!widget) return;

	const cloned = source.cloneNode(true);
	cloned.removeAttribute('hidden');
	cloned.id = '';
	source.remove();

	const container = widget.querySelector('.e-cart__container');
	if (container) {
		container.replaceWith(cloned.querySelector('.e-cart__container'));
	} else {
		widget.innerHTML = cloned.innerHTML;
	}

	document.querySelectorAll('.woocommerce-breadcrumb a[href*="cart"]').forEach(a => {
		a.href = '/cart';
	});
	document.querySelectorAll('a[href*="checkout"]').forEach(a => {
		if (a.textContent.trim().toLowerCase().includes('checkout') || a.classList.contains('checkout-button')) {
			a.href = '/checkout';
		}
	});
})();
