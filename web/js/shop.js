(function() {
	const configEl = document.getElementById('localroots-shop-config');
	if (!configEl) return;

	const config = JSON.parse(configEl.textContent);
	const filterSource = document.getElementById('localroots-shop-filters-source');
	const gridSource = document.getElementById('localroots-shop-grid-source');

	if (filterSource) {
		const filterWidget = document.querySelector('.elementor-element-51f15b5 .elementor-widget-container');
		if (filterWidget) {
			const linkEl = filterWidget.querySelector('link[data-minify="1"]');
			filterWidget.innerHTML = '';
			if (linkEl) filterWidget.appendChild(linkEl);
			filterWidget.appendChild(filterSource.cloneNode(true));
			filterSource.remove();
			initFilters(filterWidget.querySelector('#localroots-shop-filters'));
		}
	}

	if (gridSource) {
		const grid = document.querySelector('.elementor-element-e0ad6a8 .elementor-loop-container');
		if (grid) {
			grid.innerHTML = '';
			Array.from(gridSource.children).forEach(item => {
				grid.appendChild(item.cloneNode(true));
			});
			gridSource.remove();
		}
	}

	updateResultCount(config);
	updateSortMenu(config);
	updatePagination(config);
	initFilterCollapse();

	function initFilters(form) {
		if (!form) return;

		form.querySelectorAll('input[type="checkbox"]').forEach(input => {
			input.addEventListener('change', () => form.submit());
		});

		form.querySelectorAll('.localroots-price-min, .localroots-price-max').forEach(input => {
			input.addEventListener('change', () => form.submit());
		});

		form.querySelector('.localroots-clear-filters')?.addEventListener('click', (e) => {
			e.preventDefault();
			window.location.href = config.shopUrl;
		});
	}

	function initFilterCollapse() {
		document.querySelectorAll('.bapf_colaps_togl').forEach(toggle => {
			toggle.addEventListener('click', () => {
				const body = toggle.parentElement?.querySelector('.bapf_body');
				if (!body) return;
				const open = body.style.display === 'none' || body.style.display === '';
				body.style.display = open ? 'block' : 'none';
				toggle.querySelector('.bapf_colaps_smb')?.classList.toggle('fa-chevron-up', open);
				toggle.querySelector('.bapf_colaps_smb')?.classList.toggle('fa-chevron-down', !open);
			});
		});

		document.querySelectorAll('.bapf_body').forEach(body => {
			if (body.style.display === 'none') {
				body.style.display = 'block';
			}
		});
	}

	function updateResultCount(config) {
		const countEl = document.querySelector('.woocommerce-result-count');
		if (!countEl || !config.totalProducts) return;
		countEl.innerHTML = 'Showing ' + config.rangeStart + '&ndash;' + config.rangeEnd + ' of ' + config.totalProducts + ' results<span class="screen-reader-text">Sorted by ' + config.sortLabel + '</span>';
	}

	function updateSortMenu(config) {
		const label = document.querySelector('.woocommerce-ordering__button-label span');
		if (label) label.textContent = config.sortLabel;

		const links = {
			'date-desc': 'Default',
			'price-asc': 'Price (low to high)',
			'price-desc': 'Price (high to low)',
			'title-asc': 'Name: A to Z',
			'title-desc': 'Name: Z to A'
		};

		document.querySelectorAll('.woocommerce-ordering__link').forEach(link => {
			const text = link.textContent.trim();
			let sortKey = 'date-desc';
			if (text.includes('Price (low')) sortKey = 'price-asc';
			else if (text.includes('Price (high')) sortKey = 'price-desc';
			else if (text.includes('Newest')) sortKey = 'date-desc';
			else if (text === 'Default') sortKey = 'date-desc';

			if (config.sortUrls[sortKey]) {
				link.href = config.sortUrls[sortKey];
				link.classList.toggle('active', config.sort === sortKey);
			}
		});

		document.querySelectorAll('.woocommerce-ordering__submenu a').forEach(link => {
			const text = link.textContent.trim();
			if (text.includes('Price (low')) link.href = config.sortUrls['price-asc'];
			else if (text.includes('Price (high')) link.href = config.sortUrls['price-desc'];
			else if (text.includes('Newest') || text === 'Default') link.href = config.sortUrls['date-desc'];
		});
	}

	function updatePagination(config) {
		const loadMoreWrap = document.querySelector('.e-loop__load-more');
		const loadMoreAnchor = document.querySelector('.e-load-more-anchor');
		const spinner = document.querySelector('.e-load-more-spinner');

		if (spinner) spinner.style.display = 'none';

		if (config.currentPage >= config.totalPages) {
			if (loadMoreWrap) loadMoreWrap.style.display = 'none';
			return;
		}

		if (loadMoreAnchor) {
			loadMoreAnchor.dataset.page = String(config.currentPage);
			loadMoreAnchor.dataset.maxPage = String(config.totalPages);
			loadMoreAnchor.dataset.nextPage = config.pageUrls[config.currentPage] || config.shopUrl;
		}

		if (loadMoreWrap) {
			const btn = loadMoreWrap.querySelector('a, .elementor-button');
			if (btn) {
				btn.href = config.pageUrls[config.currentPage] || config.shopUrl;
				btn.addEventListener('click', (e) => {
					e.preventDefault();
					window.location.href = config.pageUrls[config.currentPage] || config.shopUrl;
				});
			}
		}
	}
})();
