(function() {
	const source = document.getElementById('localroots-checkout-source');
	const configEl = document.getElementById('localroots-checkout-config');
	if (!source || !configEl) return;

	const config = JSON.parse(configEl.textContent);
	const widget = document.querySelector('.elementor-element-37c4720 .elementor-widget-container');
	if (!widget) return;

	const woocommerce = widget.querySelector('.woocommerce');
	if (!woocommerce) return;

	const cloned = source.cloneNode(true);
	cloned.removeAttribute('hidden');
	cloned.id = '';
	source.remove();

	woocommerce.innerHTML = cloned.innerHTML;

	const form = document.querySelector('.localroots-checkout-form');
	if (!form) return;

	fixBreadcrumbLinks();
	initShippingToggle();
	initShippingCalculator(config);
	initCheckoutTracking(form, config);
	initPlaceOrder(form, config);

	function fixBreadcrumbLinks() {
		document.querySelectorAll('.elementor-element-364ab03 a, .elementor-element-93197dd a').forEach(a => {
			const text = a.textContent.trim().toLowerCase();
			if (text === 'cart') a.href = '/cart';
			if (text === 'checkout') a.href = '/checkout';
		});
	}

	function initShippingToggle() {
		const checkbox = document.getElementById('ship-to-different-address-checkbox');
		const fields = document.getElementById('shipping-address-fields');
		if (!checkbox || !fields) return;
		checkbox.addEventListener('change', () => {
			fields.style.display = checkbox.checked ? 'block' : 'none';
		});
	}

	function initShippingCalculator(config) {
		const fields = ['billing_postcode', 'billing_city', 'shipping_postcode', 'shipping_city'];
		let timer;
		fields.forEach(id => {
			const el = document.getElementById(id);
			if (!el) return;
			el.addEventListener('change', () => {
				clearTimeout(timer);
				timer = setTimeout(() => recalculateShipping(config), 400);
			});
		});
	}

	function recalculateShipping(config) {
		const zipCode = document.getElementById('billing_postcode')?.value || '';
		const city = document.getElementById('billing_city')?.value || '';
		const country = document.getElementById('billing_country')?.value || 'ZA';
		if (country !== 'ZA' || !zipCode) return;

		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('country', country);
		body.append('zipCode', zipCode);
		body.append('city', city);

		fetch(config.calculateShippingUrl, { method: 'POST', body })
			.then(r => r.json())
			.then(data => {
				if (!data.success) return;
				const costEl = document.getElementById('localroots-shipping-cost');
				const totalEl = document.getElementById('localroots-order-total');
				if (costEl) costEl.textContent = data.formatted;
				if (totalEl) {
					const total = config.subtotal + config.tax + data.cost;
					totalEl.innerHTML = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol" translate="no">R</span>' + Math.round(total).toLocaleString() + '</bdi></span>';
				}
			});
	}

	function initCheckoutTracking(form, config) {
		trackProgress(config, { step: 'view', page: 'checkout' });

		let debounceTimer;
		form.querySelectorAll('input, select, textarea').forEach(field => {
			field.addEventListener('change', () => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(() => {
					trackProgress(config, { step: 'checkout_form', data: serializeForm(form) });
				}, 1000);
			});
		});

		document.addEventListener('visibilitychange', () => {
			if (document.hidden) {
				const payload = JSON.stringify({ step: 'page_hidden', data: serializeForm(form) });
				navigator.sendBeacon(config.trackProgressUrl, payload);
			}
		});
	}

	function initPlaceOrder(form, config) {
		const btn = document.getElementById('place_order');
		if (!btn) return;

		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			if (!form.checkValidity()) {
				form.reportValidity();
				return;
			}

			const gateway = document.querySelector('.localroots-gateway-radio:checked')?.value || 'unknown';
			trackProgress(config, { step: 'payment_attempt', gateway, timestamp: new Date().toISOString() }, config.trackAttemptUrl);

			btn.disabled = true;
			btn.textContent = 'Processing...';

			const updateBody = new FormData(form);
			updateBody.set('action', 'commerce/cart/update-cart');
			updateBody.delete('gatewayId');
			updateBody.delete('terms');

			const shipDifferent = document.getElementById('ship-to-different-address-checkbox')?.checked;
			if (!shipDifferent) {
				['firstName', 'lastName', 'address1', 'address2', 'city', 'zipCode', 'countryCode', 'phone'].forEach(field => {
					const billingVal = updateBody.get('billingAddress[' + field + ']');
					if (billingVal !== null) updateBody.set('shippingAddress[' + field + ']', billingVal);
				});
			}

			try {
				await fetch(config.updateCartAction, { method: 'POST', body: updateBody, credentials: 'same-origin' });
				form.submit();
			} catch (err) {
				btn.disabled = false;
				btn.textContent = 'Place order';
			}
		});
	}

	function trackProgress(config, data, url) {
		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		Object.entries(data).forEach(([key, value]) => {
			body.append(key, typeof value === 'object' ? JSON.stringify(value) : value);
		});
		fetch(url || config.trackProgressUrl, { method: 'POST', body }).catch(() => {});
	}

	function serializeForm(form) {
		const data = {};
		new FormData(form).forEach((value, key) => {
			if (key !== 'action' && key !== config.csrfName) data[key] = value;
		});
		return data;
	}
})();
