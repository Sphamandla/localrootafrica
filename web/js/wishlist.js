(function() {
	const source = document.getElementById('localroots-wishlist-source');
	const table = document.querySelector('.elementor-element-3991d2fd .woosw-items');
	const emptyNotice = document.querySelector('.elementor-element-3991d2fd .vamtam-empty-wishlist-notice');

	if (!table) return;

	const rows = source ? source.querySelectorAll('tr.woosw-item') : [];
	rows.forEach(function(row) {
		table.appendChild(row);
	});

	if (source) {
		source.remove();
	}

	if (emptyNotice) {
		emptyNotice.style.display = rows.length ? 'none' : 'block';
	}

	document.querySelectorAll('.elementor-element-3991d2fd .vamtam-start-shopping').forEach(function(btn) {
		btn.setAttribute('formaction', '/shop');
	});
})();
