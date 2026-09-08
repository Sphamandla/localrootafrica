(function() {
	const source = document.getElementById('localroots-cart-source');
	if (!source) return;

	const widget = document.querySelector('.elementor-element-b3b4143');
	const woocommerce = widget && widget.querySelector('.woocommerce');
	if (!widget || !woocommerce) return;

	const isEmpty = !!source.querySelector('.elementor-10928');
	const content = source.querySelector('.woocommerce-notices-wrapper')
		? source.innerHTML
		: source.innerHTML;

	woocommerce.innerHTML = content;
	source.remove();

	widget.classList.toggle('e-cart-empty-template-active', isEmpty);
	document.body.classList.toggle('vamtam-wc-cart-empty', isEmpty);

	document.querySelectorAll('.vamtam-hide-on-empty-cart').forEach(function(el) {
		el.style.display = isEmpty ? 'none' : '';
	});

	document.querySelectorAll('.elementor-element-4938390 a[href*="cart"]').forEach(function(a) {
		a.href = '/cart';
	});
	document.querySelectorAll('.elementor-element-4938390 a[href*="checkout"]').forEach(function(a) {
		a.href = '/checkout';
	});
	document.querySelectorAll('a.checkout-button, a[href*="checkout"].wc-forward').forEach(function(a) {
		a.href = '/checkout';
	});

	if (!isEmpty) {
		initCartInteractions();
	}

	function initCartInteractions() {
		const form = document.querySelector('.localroots-cart-form');
		if (!form) return;

		form.querySelectorAll('.localroots-remove-item').forEach(function(button) {
			button.addEventListener('click', function() {
				const itemId = this.dataset.lineItemId;
				if (!itemId || !window.localrootsUpdateCart) return;

				const body = new FormData();
				body.set('lineItems[' + itemId + '][remove]', '1');
				window.localrootsUpdateCart(body)
					.then(function(data) {
						if (data && data.success !== false) {
							window.location.reload();
						} else if (window.localrootsShowCartNotification) {
							window.localrootsShowCartNotification('Error removing item', 'error');
						}
					})
					.catch(function() {
						if (window.localrootsShowCartNotification) {
							window.localrootsShowCartNotification('Error removing item', 'error');
						}
					});
			});
		});

		let debounceTimer;
		form.querySelectorAll('.localroots-cart-qty').forEach(function(input) {
			input.addEventListener('change', function() {
				clearTimeout(debounceTimer);
				const itemId = this.dataset.lineItemId;
				const qty = Math.max(0, parseInt(this.value, 10) || 0);
				this.value = qty;

				debounceTimer = setTimeout(function() {
					if (!window.localrootsUpdateCart) return;
					const body = new FormData();
					if (qty === 0) {
						body.set('lineItems[' + itemId + '][remove]', '1');
					} else {
						body.set('lineItems[' + itemId + '][qty]', String(qty));
					}
					window.localrootsUpdateCart(body)
						.then(function(data) {
							if (data && data.success !== false) {
								window.location.reload();
							}
						});
				}, 400);
			});
		});

		form.addEventListener('submit', function(e) {
			if (e.submitter && e.submitter.name === 'update') {
				e.preventDefault();
				if (!window.localrootsUpdateCart) return;
				window.localrootsUpdateCart(new FormData(form))
					.then(function(data) {
						if (data && data.success !== false) {
							window.location.reload();
						}
					});
			}
		});
	}
})();
