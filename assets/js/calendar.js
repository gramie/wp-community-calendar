/**
 * Community Calendar: public pages.
 * Links with a data-cc-confirm attribute (Delete) ask for confirmation first.
 */
document.addEventListener('click', function (event) {
	var link = event.target.closest ? event.target.closest('a[data-cc-confirm]') : null;
	if (link && !window.confirm(link.getAttribute('data-cc-confirm'))) {
		event.preventDefault();
	}
});
