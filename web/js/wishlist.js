(function() {
	const configEl = document.getElementById('localroots-wishlist-config');
	const config = configEl ? JSON.parse(configEl.textContent) : {};
	const table = document.querySelector('.elementor-element-3991d2fd .woosw-items');
	const emptyNotice = document.querySelector('.elementor-element-3991d2fd .vamtam-empty-wishlist-notice');
	const listEl = document.querySelector('.elementor-element-3991d2fd .woosw-list');
	const widget = document.querySelector('.elementor-element-3991d2fd');
	const undoDelay = Number(config.undoDelay) || 5000;

	if (!table) return;

	let pendingRemoval = null;
	let activeToast = null;

	function updateEmptyState() {
		const rows = table.querySelectorAll('tr.woosw-item:not(.localroots-wishlist-item--removed)');
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

	function dismissToast(toast) {
		if (!toast) return;

		toast.classList.remove('is-visible');
		window.setTimeout(function() {
			toast.remove();
		}, 280);

		if (activeToast === toast) {
			activeToast = null;
		}
	}

	function showToast(message, options) {
		const opts = options || {};

		if (activeToast) {
			dismissToast(activeToast);
		}

		const toast = document.createElement('div');
		toast.className = 'localroots-wishlist-toast' + (opts.error ? ' localroots-wishlist-toast--error' : '');
		toast.setAttribute('role', opts.error ? 'alert' : 'status');
		toast.setAttribute('aria-live', opts.error ? 'assertive' : 'polite');

		const text = document.createElement('span');
		text.className = 'localroots-wishlist-toast__message';
		text.textContent = message;
		toast.appendChild(text);

		if (typeof opts.onUndo === 'function') {
			const undoBtn = document.createElement('button');
			undoBtn.type = 'button';
			undoBtn.className = 'localroots-wishlist-toast__undo';
			undoBtn.textContent = config.undoLabel || 'Undo';
			undoBtn.addEventListener('click', function() {
				opts.onUndo();
			});
			toast.appendChild(undoBtn);
		}

		document.body.appendChild(toast);
		activeToast = toast;

		requestAnimationFrame(function() {
			toast.classList.add('is-visible');
		});

		if (!opts.onUndo && opts.duration !== 0) {
			window.setTimeout(function() {
				dismissToast(toast);
			}, opts.duration || 3200);
		}

		return toast;
	}

	function clearPendingRemoval(options) {
		const opts = options || {};
		if (!pendingRemoval) return;

		window.clearTimeout(pendingRemoval.timerId);
		if (opts.dismissToast !== false && pendingRemoval.toastEl) {
			dismissToast(pendingRemoval.toastEl);
		}
		pendingRemoval = null;
	}

	function restorePendingRow() {
		if (!pendingRemoval) return;

		const row = pendingRemoval.row;
		row.classList.remove('localroots-wishlist-item--removing', 'localroots-wishlist-item--removed');
		clearPendingRemoval();
		updateEmptyState();
	}

	function commitPendingRemoval() {
		if (!pendingRemoval) {
			return Promise.resolve();
		}

		const row = pendingRemoval.row;
		const toastEl = pendingRemoval.toastEl;
		window.clearTimeout(pendingRemoval.timerId);
		pendingRemoval = null;
		dismissToast(toastEl);

		row.classList.add('localroots-wishlist-item--removed');
		return removeWishlistItem(row, { silent: true });
	}

	function removeWishlistItem(row, options) {
		const opts = options || {};
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
				row.classList.remove('localroots-wishlist-item--removing', 'localroots-wishlist-item--removed');
				updateEmptyState();
				if (!opts.silent) {
					showToast(error.message || 'Unable to remove item from wishlist.', { error: true });
				}
				throw error;
			})
			.finally(function() {
				removeBtn.classList.remove('woosw-item--removing');
			});
	}

	function scheduleRemoval(row) {
		if (pendingRemoval && pendingRemoval.row !== row) {
			commitPendingRemoval();
		}

		if (pendingRemoval && pendingRemoval.row === row) {
			return;
		}

		const productName = row.getAttribute('data-name') || 'Item';
		const message = (config.removedMessage || 'Removed from wishlist') +
			(productName ? ': ' + productName : '');

		row.classList.add('localroots-wishlist-item--removing');

		const toastEl = showToast(message, {
			onUndo: function() {
				restorePendingRow();
			}
		});

		pendingRemoval = {
			row: row,
			toastEl: toastEl,
			timerId: window.setTimeout(function() {
				const pendingRow = pendingRemoval ? pendingRemoval.row : null;
				clearPendingRemoval({ dismissToast: true });
				if (!pendingRow) return;

				pendingRow.classList.add('localroots-wishlist-item--removed');
				removeWishlistItem(pendingRow, { silent: true }).catch(function() {
					showToast('Unable to remove item from wishlist.', { error: true });
				});
			}, undoDelay)
		};
	}

	function initRemoveHandlers() {
		if (!widget) return;

		widget.addEventListener('click', function(event) {
			const removeBtn = event.target.closest('.woosw-item--remove span');
			if (!removeBtn) return;

			event.preventDefault();

			const row = removeBtn.closest('tr.woosw-item');
			if (!row || row.classList.contains('localroots-wishlist-item--removed')) return;

			scheduleRemoval(row);
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
