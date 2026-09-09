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
	initCreateAccountToggle();
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

	function initCreateAccountToggle() {
		const checkbox = document.getElementById('createaccount');
		const note = document.getElementById('createaccount_note');
		if (!checkbox || !note) return;
		checkbox.addEventListener('change', () => {
			note.style.display = checkbox.checked ? 'block' : 'none';
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
				syncShippingMethod(config, radio.value);
			});
		});
	}

	function activePostalCode() {
		const shipDifferent = document.getElementById('ship-to-different-address-checkbox')?.checked;
		if (shipDifferent) {
			return document.getElementById('shipping_postcode')?.value
				|| document.getElementById('billing_postcode')?.value
				|| '';
		}
		return document.getElementById('billing_postcode')?.value || '';
	}

	function syncShippingMethod(config, method) {
		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('shippingMethod', method);
		body.append('postalCode', activePostalCode());
		body.append('country', 'ZA');
		body.append('zipCode', activePostalCode());

		fetch(config.calculateShippingUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then(r => r.json())
			.then(data => {
				if (!data.success) return;
				applyServerTotals(config, data);
			});
	}

	function applyServerTotals(config, data) {
		if (typeof data.shipping === 'number' || typeof data.cost === 'number') {
			const shipping = typeof data.shipping === 'number' ? data.shipping : data.cost;
			config.shippingRate = shipping;
			config.courierRate = shipping;
			const costEl = document.getElementById('localroots-shipping-cost');
			if (costEl && data.formatted) costEl.textContent = data.formatted;
		}
		if (typeof data.total === 'number') {
			config.total = data.total;
			updateOrderTotal(data.total);
		} else if (data.totalFormatted) {
			const totalEl = document.getElementById('localroots-order-total');
			if (totalEl) totalEl.textContent = data.totalFormatted.replace(/^R\s?/, 'R');
		}
		if (typeof data.tax === 'number') config.tax = data.tax;
		if (typeof data.discount === 'number') config.discount = data.discount;
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
		body.append('shippingMethod', document.querySelector('.localroots-shipping-method:checked')?.value || 'courier_guy');

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
					updateDiscountRow(data.totalDiscount, data.couponCode, data.totalDiscountFormatted);
					applyServerTotals(config, data);
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
		const zipCode = activePostalCode();
		const country = document.getElementById('billing_country')?.value || 'ZA';
		if (country !== 'ZA' || !zipCode) return;

		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('country', country);
		body.append('zipCode', zipCode);
		body.append('postalCode', zipCode);
		body.append('shippingMethod', document.querySelector('.localroots-shipping-method:checked')?.value || 'courier_guy');

		fetch(config.calculateShippingUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then(r => r.json())
			.then(data => {
				if (!data.success) return;
				applyServerTotals(config, data);
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
			clearCheckoutError();

			const updateBody = new FormData(form);
			updateBody.set('action', 'commerce/cart/update-cart');
			updateBody.delete('terms');

			const createAccount = document.getElementById('createaccount');
			if (createAccount) {
				updateBody.set('registerUserOnOrderComplete', createAccount.checked ? '1' : '0');
			}

			const shipDifferent = document.getElementById('ship-to-different-address-checkbox')?.checked;
			if (!shipDifferent) {
				['firstName', 'lastName', 'addressLine1', 'addressLine2', 'locality', 'postalCode', 'countryCode', 'phone'].forEach(field => {
					const billingVal = updateBody.get('billingAddress[' + field + ']');
					if (billingVal !== null) updateBody.set('shippingAddress[' + field + ']', billingVal);
				});
			}

			try {
				const updateResponse = await fetch(config.cartPostUrl, {
					method: 'POST',
					body: updateBody,
					credentials: 'same-origin',
					headers: { Accept: 'application/json' },
				});
				const updateData = await updateResponse.json().catch(() => null);
				if (!updateResponse.ok || !updateData?.cart) {
					const message = updateData?.message
						|| (updateData?.errors ? Object.values(updateData.errors).flat().join(' ') : '')
						|| 'Could not save your checkout details. Please check your information and try again.';
					showCheckoutError(message);
					btn.disabled = false;
					btn.textContent = 'Place order';
					return;
				}

				const metaBody = new FormData();
				metaBody.append(config.csrfName, config.csrfToken);
				metaBody.append('message', document.getElementById('order_comments')?.value || '');
				const shippingMethod = document.querySelector('.localroots-shipping-method:checked')?.value || 'courier_guy';
				metaBody.append('shippingMethod', shippingMethod);
				metaBody.append('postalCode', activePostalCode());
				await fetch(config.saveCartMetaUrl, { method: 'POST', body: metaBody, credentials: 'same-origin' });

				if (createAccount && !createAccount.checked) {
					const hidden = document.createElement('input');
					hidden.type = 'hidden';
					hidden.name = 'registerUserOnOrderComplete';
					hidden.value = '0';
					form.appendChild(hidden);
					form.submit();
					hidden.remove();
					return;
				}

				form.submit();
			} catch (err) {
				showCheckoutError('Something went wrong while placing your order. Please try again.');
				btn.disabled = false;
				btn.textContent = 'Place order';
			}
		});
	}

	function showCheckoutError(message) {
		const wrapper = document.querySelector('.woocommerce-notices-wrapper');
		if (!wrapper) return;
		wrapper.innerHTML = '<div class="woocommerce-error" role="alert">' + message + '</div>';
		wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	function clearCheckoutError() {
		const wrapper = document.querySelector('.woocommerce-notices-wrapper');
		if (wrapper) wrapper.innerHTML = '';
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
