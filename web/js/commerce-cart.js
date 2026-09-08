(function() {
	const configEl = document.getElementById('localroots-cart-config');
	const config = configEl ? JSON.parse(configEl.textContent) : { totalQty: 0, totalPrice: 0 };

	function getCsrf() {
		const meta = document.querySelector('meta[name="csrf-token"]');
		return {
			name: config.csrfName || (meta && meta.dataset.name) || 'CRAFT_CSRF_TOKEN',
			token: config.csrfToken || (meta && meta.content) || ''
		};
	}

	function formatMoney(amount) {
		const value = Math.round(parseFloat(amount) || 0);
		return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function updateCartBadge(qty, total) {
		const count = parseInt(qty, 10) || 0;
		const price = parseFloat(total) || 0;

		document.querySelectorAll('.elementor-button-icon-qty, .cart-count, .cart-badge, .header-cart .count').forEach(function(el) {
			el.textContent = String(count);
			if (el.hasAttribute('data-counter')) {
				el.setAttribute('data-counter', String(count));
			}
		});

		document.querySelectorAll('.vamtam-elementor-menu-cart__header .item-count').forEach(function(el) {
			el.textContent = '(' + count + ')';
		});

		document.querySelectorAll('.elementor-menu-cart__toggle_button .elementor-button-text .woocommerce-Price-amount bdi').forEach(function(el) {
			el.innerHTML = '<span class="woocommerce-Price-currencySymbol" translate="no">R</span>' + formatMoney(price);
		});

		document.body.classList.toggle('vamtam-wc-cart-empty', count === 0);
	}

	function hydrateMiniCart(html) {
		if (!html) return;
		document.querySelectorAll('.widget_shopping_cart_content').forEach(function(el) {
			el.innerHTML = html;
		});
		const source = document.getElementById('localroots-mini-cart-source');
		if (source) {
			source.innerHTML = html;
		}
		initMiniCartInteractions();
	}

	function refreshMiniCart() {
		const url = config.miniCartUrl || '/actions/localroots/cart/mini-cart';
		return fetch(url, {
			credentials: 'same-origin',
			headers: {
				'Accept': 'text/html',
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function(response) {
				if (!response.ok) throw new Error('Unable to refresh cart.');
				return response.text();
			})
			.then(function(html) {
				hydrateMiniCart(html);
			})
			.catch(function() {
				/* keep existing mini cart content on refresh failure */
			});
	}

	function initMiniCartInteractions() {
		document.querySelectorAll('.localroots-mini-cart-remove').forEach(function(button) {
			if (button.dataset.localrootsBound) return;
			button.dataset.localrootsBound = '1';
			button.addEventListener('click', function(event) {
				event.preventDefault();
				const itemId = this.dataset.lineItemId;
				if (!itemId || !window.localrootsUpdateCart) return;

				const body = new FormData();
				body.set('lineItems[' + itemId + '][remove]', '1');
				window.localrootsUpdateCart(body).then(function(data) {
					if (data && data.success !== false) {
						refreshMiniCart();
					}
				});
			});
		});
	}

	function showNotification(message, type) {
		const notif = document.createElement('div');
		notif.className = 'localroots-notification localroots-notification-' + (type || 'success');
		notif.textContent = message;
		notif.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:#111;color:#fff;border-radius:4px;font-size:14px;';
		if (type === 'error') notif.style.background = '#b00020';
		document.body.appendChild(notif);
		setTimeout(function() { notif.remove(); }, 3000);
	}

	window.localrootsUpdateCartBadge = updateCartBadge;
	window.localrootsShowCartNotification = showNotification;
	window.localrootsRefreshMiniCart = refreshMiniCart;
	updateCartBadge(config.totalQty, config.totalPrice);

	function initMiniCartFromSource() {
		const source = document.getElementById('localroots-mini-cart-source');
		if (source && source.innerHTML.trim()) {
			hydrateMiniCart(source.innerHTML);
			return true;
		}
		return false;
	}

	if (!initMiniCartFromSource()) {
		document.addEventListener('DOMContentLoaded', function() {
			if (!initMiniCartFromSource()) {
				refreshMiniCart();
			}
		});
	}

	window.localrootsAddToCart = async function(form) {
		const formData = new FormData(form);
		const csrf = getCsrf();
		const url = config.updateCartUrl || form.getAttribute('action') || '/actions/commerce/cart/update-cart';

		if (!formData.get('action')) {
			formData.set('action', 'commerce/cart/update-cart');
		}
		if (csrf.name && csrf.token && !formData.get(csrf.name)) {
			formData.set(csrf.name, csrf.token);
		}

		const response = await fetch(url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-Token': csrf.token
			}
		});

		let data = null;
		try {
			data = await response.json();
		} catch (e) {
			if (response.redirected || response.ok) {
				window.location.reload();
				return null;
			}
			throw new Error('Unable to add item to cart.');
		}

		const cart = data && (data.cart || data[config.cartVariable || 'cart']);
		if (data && data.success !== false && cart) {
			updateCartBadge(cart.totalQty ?? cart.totalQuantity ?? 0, cart.totalPrice ?? 0);
			refreshMiniCart();
			return { success: true, cart: cart };
		}

		if (data && data.success === false) {
			const message = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Unable to add item to cart.');
			throw new Error(message);
		}

		return data;
	};

	window.localrootsUpdateCart = async function(body) {
		const csrf = getCsrf();
		const formData = body instanceof FormData ? body : new FormData();
		if (!(body instanceof FormData)) {
			Object.entries(body).forEach(function(entry) {
				formData.set(entry[0], entry[1]);
			});
		}
		formData.set('action', 'commerce/cart/update-cart');
		if (csrf.name && csrf.token) {
			formData.set(csrf.name, csrf.token);
		}

		const response = await fetch(config.updateCartUrl || '/actions/commerce/cart/update-cart', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-Token': csrf.token
			}
		});

		const data = await response.json();
		const cart = data && (data.cart || data[config.cartVariable || 'cart']);
		if (data && data.success !== false && cart) {
			updateCartBadge(cart.totalQty ?? cart.totalQuantity ?? 0, cart.totalPrice ?? 0);
			refreshMiniCart();
		}
		return data;
	};

	document.addEventListener('submit', function(event) {
		const form = event.target;
		if (!(form instanceof HTMLFormElement)) return;
		if (!form.matches('.e-loop-add-to-cart-form, .localroots-commerce-form')) return;

		if (form.classList.contains('localroots-commerce-form')) {
			const purchasable = form.querySelector('.localroots-purchasable-id');
			if (purchasable && !purchasable.value) {
				event.preventDefault();
				showNotification('Please select a size.', 'error');
				return;
			}
		}

		event.preventDefault();
		event.stopPropagation();
		event.stopImmediatePropagation();

		const button = form.querySelector('[type="submit"]');
		const originalText = button ? button.textContent : '';
		if (button) {
			button.disabled = true;
			button.textContent = 'Adding...';
		}

		window.localrootsAddToCart(form)
			.then(function(data) {
				if (!data || (data.success === false)) {
					throw new Error('Unable to add item to cart.');
				}
				showNotification('Product added to cart!');
				if (button) {
					button.textContent = 'Added!';
					setTimeout(function() {
						button.textContent = originalText || 'Add to cart';
						button.disabled = false;
						button.classList.add('added');
					}, 1500);
				}
			})
			.catch(function(err) {
				showNotification(err.message || 'Error adding to cart', 'error');
				if (button) {
					button.textContent = originalText || 'Add to cart';
					button.disabled = false;
				}
			});
	}, true);

	document.addEventListener('click', function(event) {
		const button = event.target.closest('.localroots-commerce-form .single_add_to_cart_button');
		if (!button || button.disabled) return;
		const form = button.closest('form');
		if (!form) return;
		event.preventDefault();
		form.requestSubmit ? form.requestSubmit(button) : form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
	}, true);
})();
