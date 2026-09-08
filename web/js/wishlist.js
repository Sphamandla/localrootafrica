(function() {
	const configEl = document.getElementById('localroots-wishlist-config');
	const config = configEl ? JSON.parse(configEl.textContent) : {};
	const table = document.querySelector('.elementor-element-3991d2fd .woosw-items');
	const emptyNotice = document.querySelector('.elementor-element-3991d2fd .vamtam-empty-wishlist-notice');
	const listEl = document.querySelector('.elementor-element-3991d2fd .woosw-list');
	const widget = document.querySelector('.elementor-element-3991d2fd');

	if (!table) return;

	function updateEmptyState() {
		const rows = table.querySelectorAll('tr.woosw-item');
		const isEmpty = rows.length === 0;

		if (emptyNotice) {
			emptyNotice.style.display = isEmpty ? 'block' : 'none';
		}

		table.style.display = isEmpty ? 'none' : '';

		if (listEl) {
			listEl.classList.toggle('woosw-list-empty', isEmpty);
		}
	}

	function getCsrf() {
		return {
			name: config.csrfName || 'CRAFT_CSRF_TOKEN',
			token: config.csrfToken || ''
		};
	}

	function removeWishlistItem(row) {
		const productId = row.getAttribute('data-id');
		const siteId = row.getAttribute('data-element-site-id');
		const removeBtn = row.querySelector('.woosw-item--remove span');

		if (!productId || !removeBtn) {
			return Promise.reject(new Error('Missing wishlist item data'));
		}

		removeBtn.classList.add('woosw-item--removing');

		const csrf = getCsrf();
		const body = new FormData();
		body.append(csrf.name, csrf.token);
		body.append('elementId', productId);
		if (siteId) {
			body.append('elementSiteId', siteId);
		}
		if (config.listId) {
			body.append('listId', String(config.listId));
		}

		return fetch('/actions/wishlist/items/remove', {
			method: 'POST',
			headers: {
				Accept: 'application/json',
				'X-CSRF-Token': csrf.token
			},
			body: body
		})
			.then(function(response) {
				return response.json();
			})
			.then(function(data) {
				if (!data.success) {
					throw new Error(data.error || 'Unable to remove item from wishlist.');
				}

				row.remove();
				updateEmptyState();
			})
			.catch(function(error) {
				window.alert(error.message || 'Unable to remove item from wishlist.');
			})
			.finally(function() {
				removeBtn.classList.remove('woosw-item--removing');
			});
	}

	function initRemoveHandlers() {
		if (!widget) return;

		widget.addEventListener('click', function(event) {
			const removeBtn = event.target.closest('.woosw-item--remove span');
			if (!removeBtn) return;

			event.preventDefault();

			const row = removeBtn.closest('tr.woosw-item');
			if (!row) return;

			const message = config.confirmMessage || 'This action cannot be undone. Are you sure?';
			if (!window.confirm(message)) {
				return;
			}

			removeWishlistItem(row);
		});

		widget.addEventListener('keydown', function(event) {
			if (event.key !== 'Enter' && event.key !== ' ') return;

			const removeBtn = event.target.closest('.woosw-item--remove span');
			if (!removeBtn) return;

			event.preventDefault();
			removeBtn.click();
		});
	}

	function initStartShopping() {
		document.querySelectorAll('.elementor-element-3991d2fd .vamtam-start-shopping').forEach(function(btn) {
			btn.setAttribute('type', 'button');
			btn.addEventListener('click', function(event) {
				event.preventDefault();
				window.location.href = '/shop';
			});
		});
	}

	const source = document.getElementById('localroots-wishlist-source');
	const rows = source ? source.querySelectorAll('tr.woosw-item') : [];
	rows.forEach(function(row) {
		table.appendChild(row);
	});

	if (source) {
		source.remove();
	}

	updateEmptyState();
	initRemoveHandlers();
	initStartShopping();

	if (configEl) {
		configEl.remove();
	}
})();
