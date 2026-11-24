/**
 * Community Calendar: behaviour of the event fields (admin meta box and front-end form).
 *  - shows only the repeat options that apply to the chosen frequency
 *  - the end cannot be before the start
 *  - repeats run at most one year after the start date
 * Strings come from window.ccEventForm (see cc_register_assets()).
 */
(function () {
	'use strict';

	function pad(n) {
		return ('0' + n).slice(-2);
	}

	function init() {
		var freq = document.getElementById('cc_freq');
		if (!freq) {
			return;
		}
		var cfg = window.ccEventForm || { units: {}, badEnd: '' };
		var unit = document.getElementById('cc_unit');
		var start = document.getElementById('cc_start');
		var end = document.getElementById('cc_end');
		var until = document.getElementById('cc_until');

		function toggleRows() {
			var v = freq.value;
			document.querySelectorAll('.cc-rec').forEach(function (row) {
				row.style.display = v ? '' : 'none';
			});
			document.querySelectorAll('.cc-weekly').forEach(function (row) {
				row.style.display = v === 'weekly' ? '' : 'none';
			});
			document.querySelectorAll('.cc-nth').forEach(function (row) {
				row.style.display = v === 'monthly_nth' ? '' : 'none';
			});
			unit.textContent = cfg.units[v] || '';
        }

        // Use the choices.js library for drop-down fields
        const ccTypes = document.querySelector('#cc_types');
        const ccChoices = new Choices(ccTypes, {
          removeItems: true,
          removeItemButton: true
        });

		function checkDates() {
			// The end may not be before the start.
			if (start.value) {
				end.min = start.value;
			} else {
				end.removeAttribute('min');
			}
			end.setCustomValidity(start.value && end.value && end.value < start.value ? cfg.badEnd : '');

			// Repeats run at most one year from the start date.
			if (start.value) {
				var p = start.value.slice(0, 10).split('-');
				var limit = new Date(+p[0] + 1, +p[1] - 1, +p[2]);
				until.min = start.value.slice(0, 10);
				until.max = limit.getFullYear() + '-' + pad(limit.getMonth() + 1) + '-' + pad(limit.getDate());
			} else {
				until.removeAttribute('min');
				until.removeAttribute('max');
			}
		}

		freq.addEventListener('change', toggleRows);
		start.addEventListener('input', checkDates);
		end.addEventListener('input', checkDates);
		toggleRows();
		checkDates();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
