(function() {
	const ADD_LABEL = 'Add to wishlist';
	const ADDED_LABEL = 'Browse wishlist';
	const ADD_ICON = 'woosw-icon-5';
	const ADDED_ICON = 'woosw-icon-8';

	function getConfig() {
		const cartConfigEl = document.getElementById('localroots-cart-config');
		if (cartConfigEl) {
			const cartConfig = JSON.parse(cartConfigEl.textContent);
			return {
				csrfName: cartConfig.csrfName || 'CRAFT_CSRF_TOKEN',
				csrfToken: cartConfig.csrfToken || '',
				wishlistUrl: '/wishlist'
			};
		}

		const meta = document.querySelector('meta[name="csrf-token"]');
		return {
			csrfName: 'CRAFT_CSRF_TOKEN',
			csrfToken: meta ? meta.getAttribute('content') : '',
			wishlistUrl: '/wishlist'
		};
	}

	function setButtonState(btn, added) {
		btn.classList.toggle('woosw-added', added);
		btn.classList.toggle('woosw-btn-added', added);
		btn.setAttribute('aria-label', added ? ADDED_LABEL : ADD_LABEL);

		const icon = btn.querySelector('.woosw-btn-icon');
		const text = btn.querySelector('.woosw-btn-text');

		if (icon) {
			icon.classList.remove(ADD_ICON, ADDED_ICON);
			icon.classList.add(added ? ADDED_ICON : ADD_ICON);
		}

		if (text) {
			text.textContent = added ? ADDED_LABEL : ADD_LABEL;
		}
	}

	function syncProductButtons(productId, added) {
		document.querySelectorAll('.localroots-wishlist-btn[data-id="' + productId + '"]').forEach(function(btn) {
			setButtonState(btn, added);
		});
	}

	function addToWishlist(btn, config) {
		if (btn.classList.contains('woosw-adding')) {
			return Promise.resolve();
		}

		const elementId = btn.getAttribute('data-id');
		const elementSiteId = btn.getAttribute('data-element-site-id');

		if (!elementId || !config.csrfToken) {
			return Promise.reject(new Error('Unable to update wishlist.'));
		}

		btn.classList.add('woosw-adding');

		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('elementId', elementId);
		if (elementSiteId) {
			body.append('elementSiteId', elementSiteId);
		}

		return fetch('/actions/wishlist/items/add', {
			method: 'POST',
			headers: {
				Accept: 'application/json',
				'X-CSRF-Token': config.csrfToken
			},
			body: body
		})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				if (!data.success) {
					const message = data.message || data.error || '';
					if (/already in list/i.test(message)) {
						syncProductButtons(elementId, true);
						return;
					}
					throw new Error(message || 'Unable to add item to wishlist.');
				}

				syncProductButtons(elementId, true);
			})
			.finally(function() {
				btn.classList.remove('woosw-adding');
			});
	}

	function handleClick(event) {
		const btn = event.target.closest('.localroots-wishlist-btn');
		if (!btn) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		event.stopImmediatePropagation();

		const config = getConfig();

		if (btn.classList.contains('woosw-added')) {
			window.location.href = config.wishlistUrl;
			return;
		}

		addToWishlist(btn, config).catch(function(error) {
			window.alert(error.message || 'Unable to update wishlist.');
		});
	}

	function init() {
		if (document.documentElement.dataset.localrootsWishlistInit === '1') {
			return;
		}

		document.documentElement.dataset.localrootsWishlistInit = '1';
		document.addEventListener('click', handleClick, true);
		document.addEventListener('touchstart', handleClick, true);
	}

	window.LocalrootsWishlist = {
		init: init,
		setButtonState: setButtonState
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
