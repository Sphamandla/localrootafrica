(function() {
	const configEl = document.getElementById('localroots-product-config');
	if (!configEl) return;

	const productData = JSON.parse(configEl.textContent);

	function whenReady(fn) {
		if (document.readyState === 'complete') {
			fn();
			return;
		}
		window.addEventListener('load', fn, { once: true });
	}

	whenReady(function() {
		initVariableCart(document.querySelector('.elementor-element-48216332 .localroots-commerce-form'));
		hydrateGallery(productData);
		initProductGallery();
		replaceCarousel('.elementor-element-56fb727c .swiper-wrapper');
		replaceCarousel('.elementor-element-35331e6 .swiper-wrapper');
		initStickyColumn();
	});

	document.querySelectorAll('.product_title.entry-title').forEach(function(el) {
		el.textContent = productData.title;
	});

	const breadcrumb = document.querySelector('.woocommerce-breadcrumb');
	if (breadcrumb) {
		const parts = breadcrumb.innerHTML.split('&nbsp;&#47;&nbsp;');
		if (parts.length) {
			parts[parts.length - 1] = productData.title;
			breadcrumb.innerHTML = parts.join('&nbsp;&#47;&nbsp;');
		}
	}

	document.querySelectorAll('.woocommerce-review-link').forEach(function(el) {
		el.href = productData.url + '#reviews';
	});

	const descPanel = document.querySelector('#tab-description .vgblk-rw-wrapper, #tab-description');
	if (descPanel && productData.description) {
		descPanel.innerHTML = productData.description;
	}

	document.querySelectorAll('.woocommerce-Reviews-title span').forEach(function(el) {
		el.textContent = productData.title;
	});

	document.querySelectorAll('#producttitle, input[name="ets_Product_Title"]').forEach(function(el) {
		el.value = productData.title;
	});

	function hydrateGallery(data) {
		const images = data.images && data.images.length ? data.images : [{
			url: data.imageUrl,
			thumb: data.imageThumb,
			alt: data.title
		}];

		const wrapper = document.querySelector('.woocommerce-product-gallery__wrapper');
		if (!wrapper) return;

		wrapper.innerHTML = images.map(function(img, index) {
			const alt = img.alt || data.title;
			const url = img.url || data.imageUrl;
			const thumb = img.thumb || data.imageThumb || url;
			return `
				<div data-thumb="${thumb}" data-thumb-alt="${alt}" class="woocommerce-product-gallery__image${index === 0 ? ' flex-active-slide' : ''}">
					<img fetchpriority="high" width="1124" height="1686" src="${url}" class="wp-post-image" alt="${alt}" data-caption="" data-src="${url}" data-large_image="${url}" data-large_image_width="1124" data-large_image_height="1686" decoding="async" />
					<span class="wpcpv-item wpcpv-item-image" data-src="${url}"><img width="1124" height="1686" src="${url}" alt="${alt}"/></span>
				</div>
			`;
		}).join('');

		const gallery = document.querySelector('.woocommerce-product-gallery');
		if (gallery) {
			gallery.style.opacity = '1';
			gallery.classList.add('woocommerce-product-gallery--with-images');
		}
	}

	function initProductGallery() {
		if (typeof jQuery === 'undefined') return;

		const $widget = jQuery('.elementor-element-12b6582');
		const $galEl = $widget.find('div.woocommerce-product-gallery, div.woocommerce-product-gallery--vamtam');

		if ($widget.hasClass('vamtam-has-full-sized-gallery') && $galEl.length) {
			const mBrNoFsg = $widget.hasClass('vamtam-mbrowser-no-fsg');
			if (window.VAMTAM && window.VAMTAM.isSmallDeviceWidth && window.VAMTAM.isSmallDeviceWidth() && mBrNoFsg) {
				$widget.addClass('vamtam-mobile-gallery');
			} else {
				$galEl.removeClass('woocommerce-product-gallery').addClass('woocommerce-product-gallery--vamtam');
			}
		}

		if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
			const widgetEl = document.querySelector('.elementor-element-12b6582');
			if (widgetEl) {
				elementorFrontend.elementsHandler.runReadyTrigger(widgetEl);
			}

			const stickyEl = document.querySelector('.elementor-element-478d184f');
			if (stickyEl) {
				elementorFrontend.elementsHandler.runReadyTrigger(stickyEl);
			}

			const tabsEl = document.querySelector('.elementor-element-woocommerce-tabs, .elementor-widget-woocommerce-product-data-tabs');
			if (tabsEl) {
				elementorFrontend.elementsHandler.runReadyTrigger(tabsEl);
			}
		}

		setTimeout(function() {
			if (typeof wpcpv_init === 'function') {
				jQuery('.woocommerce-product-gallery, .woocommerce-product-gallery--vamtam').each(function() {
					const $gal = jQuery(this);
					if ($gal.data('lightGallery')) {
						try { $gal.data('lightGallery').destroy(true); } catch (e) {}
						$gal.removeData('lightGallery');
					}
				});
				wpcpv_init();
			}
		}, 300);
	}

	function initStickyColumn() {
		if (typeof jQuery === 'undefined' || !jQuery.fn.sticky) return;
		const $sticky = jQuery('.elementor-element-478d184f');
		if ($sticky.length && typeof elementorFrontend !== 'undefined') {
			elementorFrontend.elementsHandler.runReadyTrigger($sticky[0]);
		}
	}

	function replaceCarousel(selector) {
		const wrapper = document.querySelector(selector);
		const source = document.querySelector('#localroots-related-source');
		if (!wrapper || !source || !source.children.length) return;
		wrapper.innerHTML = '';
		Array.from(source.children).forEach(function(child) {
			wrapper.appendChild(child.cloneNode(true));
		});
		const carousel = wrapper.closest('.elementor-widget-loop-carousel');
		if (carousel && typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
			elementorFrontend.elementsHandler.runReadyTrigger(carousel);
		}
	}

	function initVariableCart(form) {
		if (!form) return;
		const sizeSelect = form.querySelector('.localroots-size-select');
		const purchasableInput = form.querySelector('.localroots-purchasable-id');
		const submitBtn = form.querySelector('.single_add_to_cart_button');
		const resetLink = form.querySelector('.localroots-reset-size');

		function updateCartState() {
			const option = sizeSelect.options[sizeSelect.selectedIndex];
			const variantId = option ? option.getAttribute('data-variant-id') : null;
			if (variantId) {
				purchasableInput.value = variantId;
				submitBtn.disabled = false;
			} else {
				purchasableInput.value = '';
				submitBtn.disabled = true;
			}
		}

		if (sizeSelect) sizeSelect.addEventListener('change', updateCartState);
		if (resetLink) {
			resetLink.addEventListener('click', function(e) {
				e.preventDefault();
				sizeSelect.value = '';
				updateCartState();
			});
		}
		updateCartState();
	}
})();
