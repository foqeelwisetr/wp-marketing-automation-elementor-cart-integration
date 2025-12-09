/**
 * Frontend JavaScript for Elementor Form Cart Integration
 * 
 * This provides a client-side fallback for setting the contact UID cookie
 * when server-side cookie setting fails (e.g., headers already sent).
 */

(function($) {
	'use strict';

	/**
	 * Set contact UID cookie via JavaScript
	 */
	function setContactCookie(email) {
		if (!email || !isValidEmail(email)) {
			return;
		}

		// Try to get UID from server via AJAX
		$.ajax({
			url: bwfanElementorCart.ajaxurl,
			type: 'POST',
			data: {
				action: 'bwfan_elementor_set_contact_cookie',
				email: email,
				nonce: bwfanElementorCart.nonce
			},
			success: function(response) {
				if (response.success && response.data && response.data.uid) {
					// Set cookie via JavaScript
					setCookie('_fk_contact_uid', response.data.uid, 3650); // 10 years
					
					if (window.console && window.console.log) {
						console.log('BWFAN Elementor Cart Integration: Contact cookie set', response.data);
					}
				}
			},
			error: function(xhr, status, error) {
				if (window.console && window.console.error) {
					console.error('BWFAN Elementor Cart Integration: Failed to set contact cookie', error);
				}
			}
		});
	}

	/**
	 * Set cookie helper function
	 */
	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
	}

	/**
	 * Validate email address
	 */
	function isValidEmail(email) {
		var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return re.test(email);
	}

	/**
	 * Extract email from Elementor form
	 */
	function extractEmailFromForm($form) {
		// Look for email input fields
		var $emailInputs = $form.find('input[type="email"]');
		
		if ($emailInputs.length > 0) {
			var email = $emailInputs.first().val();
			if (email && isValidEmail(email)) {
				return email;
			}
		}

		// Fallback: look for fields with email-related IDs/names
		var emailPatterns = ['email', 'your-email', 'e-mail', 'mail', 'email_address', 'billing_email'];
		
		for (var i = 0; i < emailPatterns.length; i++) {
			var $field = $form.find('input[id*="' + emailPatterns[i] + '"], input[name*="' + emailPatterns[i] + '"]');
			if ($field.length > 0) {
				var email = $field.first().val();
				if (email && isValidEmail(email)) {
					return email;
				}
			}
		}

		return null;
	}

	/**
	 * Initialize on DOM ready
	 */
	$(document).ready(function() {
		// Listen for Elementor form submissions
		$(document).on('submit_success', '.elementor-form', function(e) {
			var $form = $(this);
			var email = extractEmailFromForm($form);
			
			if (email) {
				// Small delay to ensure server-side processing completes first
				setTimeout(function() {
					setContactCookie(email);
				}, 500);
			}
		});

		// Also listen for Elementor Pro form success event
		$(document).on('elementor/popup/hide', function(e, $popup) {
			var $form = $popup.find('.elementor-form');
			if ($form.length > 0) {
				var email = extractEmailFromForm($form);
				if (email) {
					setTimeout(function() {
						setContactCookie(email);
					}, 500);
				}
			}
		});

		// Monitor email field changes for real-time cookie setting
		$(document).on('change blur', '.elementor-form input[type="email"]', function() {
			var email = $(this).val();
			if (email && isValidEmail(email)) {
				// Only set if cookie doesn't exist or is different
				var currentUid = getCookie('_fk_contact_uid');
				if (!currentUid) {
					setContactCookie(email);
				}
			}
		});
	});

	/**
	 * Get cookie helper function
	 */
	function getCookie(name) {
		var nameEQ = name + '=';
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') {
				c = c.substring(1, c.length);
			}
			if (c.indexOf(nameEQ) === 0) {
				return c.substring(nameEQ.length, c.length);
			}
		}
		return null;
	}

})(jQuery);

