(function() {
	const configEl = document.getElementById('localroots-product-config');
	if (!configEl) return;

	const productData = JSON.parse(configEl.textContent);
	const cartWidgetSel = '.elementor-element-' + (productData.cartWidgetId || '48216332') + ' .elementor-widget-container';
	const galleryWidgetSel = '.elementor-element-' + (productData.galleryWidgetId || '12b6582');
	const stickyWidgetSel = '.elementor-element-' + (productData.stickyWidgetId || '478d184f');
	const priceWidgetSel = '.elementor-element-' + (productData.priceWidgetId || '7aef695f') + ' .elementor-widget-container';

	hydratePrice();

	function hydratePrice() {
		const source = document.getElementById('localroots-product-price-source');
		const title = document.querySelector('.elementor-location-single .product_title');
		if (!source || !title) return;

		const priceWidget = document.querySelector(priceWidgetSel);
		if (priceWidget) {
			priceWidget.innerHTML = source.innerHTML;
		}
		source.remove();
	}

	function hydrateAddToCart() {
		const source = document.getElementById('localroots-product-cart-source');
		const widget = document.querySelector(cartWidgetSel);
		if (!source || !widget) return false;

		const formHtml = source.querySelector('form.localroots-commerce-form');
		if (productData.isVariable) {
			const addToCartDiv = widget.querySelector('.elementor-add-to-cart');
			if (!addToCartDiv) return false;
			addToCartDiv.innerHTML = formHtml ? formHtml.outerHTML : source.innerHTML;
		} else {
			widget.innerHTML = formHtml ? formHtml.outerHTML : source.innerHTML;
		}
		source.remove();
		return true;
	}

	function hydrateProductMeta(data) {
		if (data.sku) {
			document.querySelectorAll('.sku_wrapper .sku, .product_meta .sku').forEach(function(el) {
				el.textContent = data.sku;
			});
		}

		if (data.descriptionHtml) {
			const descTab = document.querySelector('#tab-description .vgblk-rw-wrapper, #tab-description, #elementor-tab-content-1981');
			if (descTab) descTab.innerHTML = data.descriptionHtml;
		}

		if (data.additionalInfoHtml) {
			const infoTab = document.querySelector('#tab-additional_information, #elementor-tab-content-1982');
			if (infoTab) infoTab.innerHTML = data.additionalInfoHtml;
		}

		if (data.categories && data.categories.length) {
			const postedIn = document.querySelector('.posted_in, .product_meta .posted_in');
			if (postedIn) {
				postedIn.innerHTML = 'Category: ' + data.categories.map(function(c) {
					return '<a href="/shop">' + c + '</a>';
				}).join(', ');
			}
		}

		if (data.tags && data.tags.length) {
			const taggedAs = document.querySelector('.tagged_as, .product_meta .tagged_as');
			if (taggedAs) {
				taggedAs.innerHTML = 'Tag: ' + data.tags.map(function(t) {
					return '<a href="/shop">' + t + '</a>';
				}).join(', ');
			}
		}
	}

	function bootProductPage() {
		if (hydrateAddToCart()) {
			const form = document.querySelector(cartWidgetSel + ' .localroots-commerce-form');
			if (productData.isVariable) {
				initVariableCart(form);
			}
		}
		hydrateGallery(productData);
		hydrateProductMeta(productData);
		initProductGallery();
		replaceCarousel('.elementor-element-56fb727c .swiper-wrapper');
		replaceCarousel('.elementor-element-35331e6 .swiper-wrapper');
		initStickyColumn();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootProductPage, { once: true });
	} else {
		bootProductPage();
	}

	window.addEventListener('load', function() {
		if (!document.querySelector(cartWidgetSel + ' .localroots-commerce-form')) {
			if (hydrateAddToCart()) {
				const form = document.querySelector(cartWidgetSel + ' .localroots-commerce-form');
				if (productData.isVariable) {
					initVariableCart(form);
				}
			}
		}
	}, { once: true });

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

		const $widget = jQuery(galleryWidgetSel);
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
			const widgetEl = document.querySelector(galleryWidgetSel);
			if (widgetEl) {
				elementorFrontend.elementsHandler.runReadyTrigger(widgetEl);
			}

			const stickyEl = document.querySelector(stickyWidgetSel);
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
		const $sticky = jQuery(stickyWidgetSel);
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
