/**
 * (C) OpenEyes Foundation, 2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

(function (exports) {

	'use strict';

	var container;
	var popup;
	var buttons;
	var helpHint;

	var stuck = false;
	var sticky = false;
	var hideTimer = 0;
	var hoverTimer = 0;

	function update() {
		popup.add(buttons).add(helpHint).trigger('update');
	}

	function init() {

		container = $('#patient-popup-container');
		popup = $('#patient-summary-popup');
		buttons = container.find('.toggle-patient-summary-popup');
		helpHint = popup.find('.help-hint');

		// Popup custom events.
		popup.on({
			update: function() {
				popup.trigger(stuck ? 'show' : 'hide');
			},
			show: function() {
				if (popup.hasClass('show')) return;
				clearTimeout(hideTimer);
				popup.show();
				// Re-define the transitions on the popup to be none.
				popup.addClass('clear-transition');
				// Trigger a re-flow to reset the starting position of the transitions, now
				// existing transitions will be removed.
				popup[0].offsetWidth = popup[0].offsetWidth;
				// Add the initial transition definitions back.
				popup.removeClass('clear-transition');
				// We can now animate in from the initial starting point.
				popup.addClass('show');
			},
			hide: function() {
				clearTimeout(hideTimer);
				popup.removeClass('show');
				// We want the popup to animate out before being hidden.
				hideTimer = setTimeout(popup.hide.bind(popup), 250);
			}
		});

		// Help hint custom events.
		helpHint.on({
			update: function() {
				var text = helpHint.data('text')[ stuck ? 'close' : 'lock' ];
				helpHint.text(text[sticky ? 'short' : 'full']);
			}
		});

		// Button events.
		buttons.on({
			update: function() {
				var button = $(this);
				var showIcon = button.data('show-icon');
				var hideIcon = button.data('hide-icon');
				if (showIcon && hideIcon) {
					button
					.removeClass(showIcon + ' ' + hideIcon)
					.addClass(stuck ? hideIcon : showIcon);
				}
			},
			click: function() {
				stuck = !stuck;
				update();
			}
		});

		// We add these mouse events on the container so that the popup does not
		// hide when hovering over the popup contents.
		container.on({
			mouseenter: function() {
				clearTimeout(hoverTimer);
				// We use a timer to prevent the popup from displaying unintentionally.
				hoverTimer = setTimeout(popup.trigger.bind(popup, 'show'), 200);
			},
			mouseleave: function() {
				clearTimeout(hoverTimer);
				if (!stuck) {
					popup.trigger('hide');
				}
			}
		});
	}

	function refresh(patientId) {
		if (!patientId) {
			throw new Error('Patient id is required')
		}
		$.ajax({
			type: 'GET',
			url: '/patient/summarypopup/' + patientId
		}).done(function(data) {
			$('#patient-popup-container').replaceWith(data);
			init();
			update();
		});
	}

	// Init on page load.
	$(init);

	// Public API
	exports.PatientSummaryPopup = { refresh: refresh };

}(this.OpenEyes.UI.Widgets));