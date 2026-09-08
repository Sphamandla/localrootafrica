(function() {
	function bootGrid() {
		const configEl = document.getElementById('localroots-press-config');
		const gridSource = document.getElementById('localroots-press-grid-source');
		const loopWidget = document.querySelector('.elementor-element-ac21091');

		if (!gridSource || !loopWidget) return;

		const grid = loopWidget.querySelector('.elementor-loop-container');
		if (!grid) return;

		grid.querySelectorAll('.e-loop-item').forEach(el => el.remove());
		const insertBefore = grid.querySelector('.e-load-more-spinner');
		Array.from(gridSource.children).forEach(item => {
			grid.insertBefore(item.cloneNode(true), insertBefore);
		});
		gridSource.remove();

		loopWidget.classList.add('localroots-press-ready');

		if (configEl) {
			const config = JSON.parse(configEl.textContent);
			if (!config.showLoadMore) {
				grid.querySelector('.e-loop__load-more')?.remove();
				grid.querySelector('.e-load-more-anchor')?.remove();
				grid.querySelector('.e-load-more-spinner')?.remove();
				grid.querySelector('.e-load-more-message')?.remove();
			}
		}

		if (typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
			elementorFrontend.elementsHandler.runReadyTrigger(loopWidget);
		}
	}

	function bootEntry() {
		const source = document.getElementById('localroots-press-entry-source');
		if (!source) return;

		const dateEl = document.querySelector('.elementor-element-7f87bc7 time');
		const titleEl = document.querySelector('.elementor-element-31637a7 h1');
		const heroEl = document.querySelector('.elementor-element-02390d0 img');
		const bodyEl = document.querySelector('.elementor-element-7d788b0 .elementor-widget-container');

		const date = source.querySelector('[data-press-date]');
		const title = source.querySelector('[data-press-title]');
		const hero = source.querySelector('[data-press-hero]');
		const body = source.querySelector('[data-press-body]');

		if (dateEl && date) dateEl.textContent = date.textContent;
		if (titleEl && title) titleEl.textContent = title.textContent;
		if (heroEl && hero) {
			heroEl.src = hero.getAttribute('src');
			heroEl.removeAttribute('data-lazy-src');
			heroEl.classList.remove('elementor-invisible');
		}
		if (bodyEl && body) body.innerHTML = body.innerHTML;

		source.remove();

		const relatedSource = document.getElementById('localroots-press-related-source');
		const relatedWidget = document.querySelector('.elementor-element-7a0d939');
		const relatedWrapper = relatedWidget?.querySelector('.swiper-wrapper');
		if (relatedSource && relatedWrapper) {
			relatedWrapper.querySelectorAll('.e-loop-item').forEach(el => el.remove());
			Array.from(relatedSource.children).forEach(item => {
				const slide = item.cloneNode(true);
				slide.classList.add('swiper-slide');
				slide.setAttribute('role', 'group');
				slide.setAttribute('aria-roledescription', 'slide');
				relatedWrapper.appendChild(slide);
			});
			relatedSource.remove();

			if (relatedWidget && typeof elementorFrontend !== 'undefined' && elementorFrontend.elementsHandler) {
				elementorFrontend.elementsHandler.runReadyTrigger(relatedWidget);
			}
		}
	}

	bootGrid();
	bootEntry();
})();
