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

	woocommerce.innerHTML = '<div class="woocommerce-notices-wrapper"></div><div class="woocommerce-notices-wrapper"></div>' + cloned.innerHTML;

	const form = document.querySelector('.localroots-checkout-form');
	if (!form) return;

	woocommerce.classList.add('localroots-checkout-ready');

	fixBreadcrumbLinks();
	initShippingToggle();
	initShippingMethods(config);
	initPaymentMethods();
	initShippingCalculator(config);
	initCouponForm(config);
	initCheckoutTracking(form, config);
	initPlaceOrder(form, config);

	if (config.couponCode) {
		const couponAnchor = document.querySelector('.e-coupon-anchor');
		const couponNudge = document.querySelector('.e-show-coupon-form');
		if (couponAnchor) couponAnchor.style.display = 'block';
		if (couponNudge) couponNudge.closest('.e-coupon-box')?.querySelector('.e-woocommerce-coupon-nudge')?.classList.add('coupon-visible');
	}

	function fixBreadcrumbLinks() {
		document.querySelectorAll('.elementor-element-364ab03 a, .elementor-element-93197dd a').forEach(a => {
			const text = a.textContent.trim().toLowerCase();
			if (text === 'cart') a.href = '/cart';
			if (text === 'checkout') a.href = '/checkout';
		});
	}

	function initShippingToggle() {
		const checkbox = document.getElementById('ship-to-different-address-checkbox');
		const fields = document.querySelector('.shipping_address');
		if (!checkbox || !fields) return;
		checkbox.addEventListener('change', () => {
			fields.style.display = checkbox.checked ? 'block' : 'none';
		});
	}

	function initPaymentMethods() {
		const radios = document.querySelectorAll('.localroots-gateway-radio');
		if (!radios.length) return;

		const showBox = (handle) => {
			document.querySelectorAll('.payment_box').forEach(box => {
				box.style.display = 'none';
			});
			if (handle) {
				const active = document.querySelector('.payment_box.payment_method_' + handle);
				if (active) active.style.display = 'block';
			}
		};

		radios.forEach(radio => {
			radio.addEventListener('change', () => {
				if (!radio.checked) return;
				const handle = radio.id.replace('payment_method_', '');
				showBox(handle);
			});
		});
	}

	function initShippingMethods(config) {
		config.courierRate = config.shippingRate;
		document.querySelectorAll('.localroots-shipping-method').forEach(radio => {
			radio.addEventListener('change', () => {
				if (!radio.checked) return;
				config.shippingRate = radio.value === 'local_pickup' ? 0 : config.courierRate;
				updateOrderTotal(config.subtotal + config.tax - (config.discount || 0) + config.shippingRate);
			});
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

	function initCouponForm(config) {
		const toggle = document.querySelector('.e-show-coupon-form');
		const anchor = document.querySelector('.e-coupon-anchor');
		const applyBtn = document.querySelector('.localroots-apply-coupon');
		const messageEl = document.querySelector('.localroots-coupon-message');
		const couponInput = document.getElementById('coupon_code');

		if (toggle && anchor) {
			toggle.addEventListener('click', (e) => {
				e.preventDefault();
				anchor.style.display = anchor.style.display === 'none' ? 'block' : 'none';
			});
		}

		if (!applyBtn || !couponInput) return;

		applyBtn.addEventListener('click', () => applyCoupon(config, couponInput.value, messageEl));
		couponInput.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') {
				e.preventDefault();
				applyCoupon(config, couponInput.value, messageEl);
			}
		});
	}

	function applyCoupon(config, code, messageEl) {
		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('couponCode', code.trim());

		applyCouponRequest(config, body, messageEl);
	}

	function applyCouponRequest(config, body, messageEl) {
		fetch(config.applyCouponUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then(r => r.json())
			.then(data => {
				if (messageEl) {
					messageEl.style.display = 'block';
					messageEl.textContent = data.success
						? 'Coupon applied successfully.'
						: (data.message || 'Could not apply coupon.');
					messageEl.style.color = data.success ? '#2e7d32' : '#c62828';
				}

				if (data.success) {
					config.discount = data.totalDiscount;
					config.total = data.total + config.shippingRate;
					updateDiscountRow(data.totalDiscount, data.couponCode, data.totalDiscountFormatted);
					updateOrderTotal(config.total);
				} else {
					updateDiscountRow(0, '', '');
				}
			});
	}

	function updateDiscountRow(amount, code, formatted) {
		let row = document.querySelector('.cart-discount');
		if (amount > 0) {
			if (!row) {
				const taxRow = document.querySelector('.tax-total');
				const subtotalRow = document.querySelector('.cart-subtotal');
				row = document.createElement('tr');
				row.className = 'cart-discount';
				row.innerHTML = '<th>Discount</th><td></td>';
				(taxRow || subtotalRow)?.after(row);
			}
			row.querySelector('th').textContent = code ? 'Discount (' + code + ')' : 'Discount';
			row.querySelector('td').innerHTML = '-<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol" translate="no">R</span>' + Math.round(amount).toLocaleString() + '</bdi></span>';
			row.style.display = '';
		} else if (row) {
			row.remove();
		}
	}

	function updateOrderTotal(total) {
		const totalEl = document.getElementById('localroots-order-total');
		if (totalEl) {
			totalEl.innerHTML = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol" translate="no">R</span>' + Math.round(total).toLocaleString() + '</bdi></span>';
		}
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
				if (costEl) costEl.textContent = data.formatted;
				config.courierRate = data.cost;
				if (document.getElementById('shipping_method_0_courier_guy')?.checked) {
					config.shippingRate = data.cost;
					updateOrderTotal(config.subtotal + config.tax - (config.discount || 0) + data.cost);
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

				const metaBody = new FormData();
				metaBody.append(config.csrfName, config.csrfToken);
				metaBody.append('message', document.getElementById('order_comments')?.value || '');
				const shippingMethod = document.querySelector('.localroots-shipping-method:checked')?.value || 'courier_guy';
				metaBody.append('shippingMethod', shippingMethod);
				await fetch(config.saveCartMetaUrl, { method: 'POST', body: metaBody, credentials: 'same-origin' });

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
