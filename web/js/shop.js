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
		updateCategoryHeader(config);
		initFilterCollapse();
		applyBrandFilterState(config);
	}

	function updateCategoryHeader(config) {
		if (config.categoryTitle) {
			const titleEl = document.querySelector('.elementor-element-68b581d .elementor-heading-title');
			if (titleEl) titleEl.textContent = config.categoryTitle;
		}

		if (!config.categoryBreadcrumb?.length) return;

		const breadcrumbEl = document.querySelector('.woocommerce-breadcrumb');
		if (!breadcrumbEl) return;

		breadcrumbEl.innerHTML = config.categoryBreadcrumb.map((item, index) => {
			const prefix = index > 0 ? '&nbsp;&#47;&nbsp;' : '';
			if (item.url) {
				return prefix + '<a href="' + item.url + '">' + item.title + '</a>';
			}
			return prefix + item.title;
		}).join('');
	}

	function applyBrandFilterState(config) {
		if (!config.brandTitle) return;

		const brandWidget = document.getElementById('bapf_5');
		if (!brandWidget) return;

		brandWidget.classList.add('bapf_ccolaps');
		const body = brandWidget.querySelector('.bapf_body');
		if (body) body.style.display = 'block';

		const typeWidget = document.getElementById('bapf_2');
		if (typeWidget) {
			typeWidget.closest('.berocket_single_filter_widget')?.classList.add('bapf_fhide');
		}
	}

	function buildFilterQueryString(form) {
		const params = new URLSearchParams(new FormData(form));
		params.delete('brands[]');
		const qs = params.toString();
		return qs ? '?' + qs : '';
	}

	function initFilters(form, config) {
		if (!form) return;

		form.querySelectorAll('input[type="checkbox"]').forEach(input => {
			if (input.name === 'brands[]' && input.dataset.brandSlug) {
				input.addEventListener('change', () => {
					const suffix = buildFilterQueryString(form);
					if (input.checked) {
						window.location.href = '/brands/' + input.dataset.brandSlug + suffix;
					} else if (config.brandSlug) {
						window.location.href = '/shop' + suffix;
					} else {
						form.submit();
					}
				});
				return;
			}

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

		const nextPage = config.currentPage + 1;
		const nextUrl = config.pageUrls?.[nextPage - 1] || config.shopUrl;

		if (loadMoreAnchor) {
			loadMoreAnchor.dataset.page = String(config.currentPage);
			loadMoreAnchor.dataset.maxPage = String(config.totalPages);
			loadMoreAnchor.dataset.nextPage = nextUrl;
		}

		if (loadMoreWrap) {
			loadMoreWrap.style.display = '';
			const btn = loadMoreWrap.querySelector('a, .elementor-button');
			if (btn) {
				btn.href = nextUrl;
				btn.onclick = (e) => {
					e.preventDefault();
					loadMoreProducts(config);
				};
			}
		}
	}

	function buildLoadMoreUrl(config, page) {
		const url = new URL(config.loadMoreUrl || '/localroots/shop/load-more', window.location.origin);
		url.searchParams.set('page', String(page));

		if (config.categoryPath) {
			url.searchParams.set('categoryPath', config.categoryPath);
		}
		if (config.brandSlug) {
			url.searchParams.set('brandSlug', config.brandSlug);
		}

		const current = new URLSearchParams(window.location.search);
		['sort', 'minPrice', 'maxPrice', 'inStock'].forEach(key => {
			if (current.has(key)) url.searchParams.set(key, current.get(key));
		});
		['categories', 'sizes', 'colors', 'brands', 'types'].forEach(key => {
			current.getAll(key + '[]').forEach(value => url.searchParams.append(key + '[]', value));
		});

		return url.toString();
	}

	async function loadMoreProducts(config) {
		const nextPage = config.currentPage + 1;
		if (nextPage > config.totalPages) return;

		const loadMoreWrap = document.querySelector('.e-loop__load-more');
		const spinner = document.querySelector('.e-load-more-spinner');
		const btn = loadMoreWrap?.querySelector('a, .elementor-button');

		if (btn) {
			btn.style.pointerEvents = 'none';
			btn.setAttribute('aria-busy', 'true');
		}
		if (spinner) spinner.style.display = '';

		try {
			const response = await fetch(buildLoadMoreUrl(config, nextPage), {
				headers: { 'Accept': 'application/json' },
				credentials: 'same-origin',
			});
			const data = await response.json();
			if (!data.success || !data.html) return;

			const grid = document.querySelector('.elementor-element-e0ad6a8 .elementor-loop-container');
			const insertBefore = grid?.querySelector('.e-load-more-spinner');
			if (!grid || !insertBefore) return;

			const wrapper = document.createElement('div');
			wrapper.innerHTML = data.html.trim();
			Array.from(wrapper.children).forEach(item => {
				grid.insertBefore(item, insertBefore);
			});

			initProductGalleries(grid);

			const loopWidget = document.querySelector('.elementor-element-e0ad6a8');
			if (loopWidget && typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
				elementorFrontend.elementsHandler.runReadyTrigger(loopWidget);
			}

			config.currentPage = data.currentPage;
			config.rangeStart = 1;
			config.rangeEnd = data.rangeEnd;
			updateResultCount(config);
			updatePagination(config);
		} catch (err) {
			console.error('Load more failed', err);
		} finally {
			if (spinner) spinner.style.display = 'none';
			if (btn) {
				btn.style.pointerEvents = '';
				btn.removeAttribute('aria-busy');
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
