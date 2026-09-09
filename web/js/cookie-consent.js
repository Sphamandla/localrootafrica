(function () {
	const key = 'localroots_cookie_consent';
	const banner = document.getElementById('localroots-cookie-consent');
	if (!banner || localStorage.getItem(key) === '1') return;

	banner.hidden = false;
	banner.querySelector('.localroots-cookie-consent__accept')?.addEventListener('click', () => {
		localStorage.setItem(key, '1');
		banner.remove();
	});
})();
