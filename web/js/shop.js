(function() {
	function boot() {
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
				initFilters(filterWidget.querySelector('#localroots-shop-filters'), config);
			}
		}

		if (gridSource) {
			const grid = document.querySelector('.elementor-element-e0ad6a8 .elementor-loop-container');
			if (grid) {
				grid.querySelectorAll('.e-loop-item').forEach(el => el.remove());
				const insertBefore = grid.querySelector('.e-load-more-spinner');
				Array.from(gridSource.children).forEach(item => {
					grid.insertBefore(item.cloneNode(true), insertBefore);
				});
				gridSource.remove();
				initProductGalleries(grid);

				const loopWidget = document.querySelector('.elementor-element-e0ad6a8');
				if (loopWidget && typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
					elementorFrontend.elementsHandler.runReadyTrigger(loopWidget);
				}
			}
		}

		updateResultCount(config);
		updateSortMenu(config);
		updatePagination(config);
		initFilterCollapse();
	}

	function initFilters(form, config) {
		if (!form) return;

		form.querySelectorAll('input[type="checkbox"]').forEach(input => {
			input.addEventListener('change', () => form.submit());
		});

		initPriceSlider(form, config);

		form.querySelector('.localroots-clear-filters')?.addEventListener('click', (e) => {
			e.preventDefault();
			window.location.href = config.shopUrl;
		});
	}

	function initPriceSlider(form, config) {
		const minInput = form.querySelector('.localroots-price-min');
		const maxInput = form.querySelector('.localroots-price-max');
		const sliderHost = form.querySelector('.bapf_slidr_jqrui');
		if (!minInput || !maxInput || !sliderHost || typeof jQuery === 'undefined' || !jQuery.fn.slider) {
			return;
		}

		const minBound = Math.floor(config.priceMin ?? (parseFloat(minInput.value) || 0));
		const maxBound = Math.ceil(config.priceMax ?? (parseFloat(maxInput.value) || 10000));
		let minVal = parseFloat(minInput.value) || minBound;
		let maxVal = parseFloat(maxInput.value) || maxBound;
		let sliderReady = false;

		const fromEl = form.querySelector('.localroots-price-from');
		const toEl = form.querySelector('.localroots-price-to');

		jQuery(sliderHost).slider({
			range: true,
			min: minBound,
			max: maxBound,
			values: [minVal, maxVal],
			slide: function(_event, ui) {
				minVal = ui.values[0];
				maxVal = ui.values[1];
				minInput.value = String(minVal);
				maxInput.value = String(maxVal);
				if (fromEl) fromEl.textContent = String(minVal);
				if (toEl) toEl.textContent = String(maxVal);
			},
			stop: function() {
				if (!sliderReady) return;
				form.submit();
			}
		});
		sliderReady = true;
	}

	function initProductGalleries(scope) {
		if (typeof jQuery === 'undefined') return;

		jQuery(scope).find('.vamtam-product-gallery.swiper').each(function() {
			const el = this;
			if (el.swiper || el.dataset.localrootsGalleryInit) return;
			el.dataset.localrootsGalleryInit = '1';

			if (typeof Swiper !== 'undefined') {
				new Swiper(el, {
					slidesPerView: 1,
					loop: el.querySelectorAll('.swiper-slide').length > 1,
					navigation: {
						nextEl: el.querySelector('.swiper-button-next'),
						prevEl: el.querySelector('.swiper-button-prev')
					}
				});
			} else if (typeof jQuery(el).swiper === 'function') {
				jQuery(el).swiper({
					slidesPerView: 1,
					loop: true
				});
			}
		});

		if (typeof jQuery.fn.vamtam_wc_gallery === 'function') {
			jQuery(scope).find('.vamtam-has-post-gallery').vamtam_wc_gallery();
		}
	}

	function initFilterCollapse() {
		document.querySelectorAll('.bapf_colaps_togl').forEach(toggle => {
			if (toggle.dataset.localrootsBound) return;
			toggle.dataset.localrootsBound = '1';
			toggle.addEventListener('click', () => {
				const body = toggle.parentElement?.querySelector('.bapf_body');
				if (!body) return;
				const open = body.style.display === 'none';
				body.style.display = open ? 'block' : 'none';
				const icon = toggle.querySelector('.bapf_colaps_smb');
				if (icon) {
					icon.classList.toggle('fa-chevron-up', open);
					icon.classList.toggle('fa-chevron-down', !open);
				}
			});
		});
	}

	function updateResultCount(config) {
		const countEl = document.querySelector('.woocommerce-result-count');
		if (!countEl || !config.totalProducts) return;
		countEl.innerHTML = 'Showing ' + config.rangeStart + '&ndash;' + config.rangeEnd + ' of ' + config.totalProducts + ' results<span class="screen-reader-text">Sorted by ' + config.sortLabel + '</span>';
	}

	function updateSortMenu(config) {
		const label = document.querySelector('.woocommerce-ordering__button-label > span:last-child');
		if (label) label.textContent = config.sortLabel;

		const sortMap = {
			'Default': 'date-desc',
			'Popularity': 'date-desc',
			'Average rating': 'date-desc',
			'Newest': 'date-desc',
			'Price (low to high)': 'price-asc',
			'Price (high to low)': 'price-desc'
		};

		document.querySelectorAll('.woocommerce-ordering__link, .woocommerce-ordering__submenu a').forEach(link => {
			const text = link.textContent.trim();
			const sortKey = sortMap[text] || 'date-desc';
			if (config.sortUrls[sortKey]) {
				link.href = config.sortUrls[sortKey];
				link.classList.toggle('active', config.sort === sortKey);
			}
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
			loadMoreWrap.style.display = '';
			const btn = loadMoreWrap.querySelector('a, .elementor-button');
			if (btn) {
				btn.href = config.pageUrls[config.currentPage] || config.shopUrl;
				btn.onclick = (e) => {
					e.preventDefault();
					window.location.href = config.pageUrls[config.currentPage] || config.shopUrl;
				};
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.addEventListener('load', () => {
		const form = document.querySelector('#localroots-shop-filters');
		const configEl = document.getElementById('localroots-shop-config');
		if (form && configEl && !form.dataset.priceSliderInit) {
			form.dataset.priceSliderInit = '1';
			initPriceSlider(form, JSON.parse(configEl.textContent));
		}
	});
})();
