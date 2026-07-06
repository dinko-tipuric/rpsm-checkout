/**
 * RPSM Checkout - Frontend JS
 *
 * Consolidated from Code Snippets:
 * - Email typo check (blur + submit)
 * - Checkout scroll block (4 mechanisms)
 * - Stripe payment change trigger
 * - Editable cart auto-update
 *
 * All texts come from rpsmCheckout (wp_localize_script).
 */
(function($) {
	'use strict';

	if (typeof rpsmCheckout === 'undefined') return;

	/* ══════════════════════════════════════════════════════════════ */
	/*  1. EMAIL VALIDATION (typo check)                            */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.emailVal) {
		var ev = rpsmCheckout.emailVal;
		var tldFixes    = parseFixes(ev.tldFixes || '');
		var domainFixes = parseFixes(ev.domainFixes || '');
		var userConfirmedEmail = false;

		function parseFixes(str) {
			var map = {};
			str.split(',').forEach(function(pair) {
				var kv = pair.split(':');
				if (kv.length === 2) map[kv[0].trim().toLowerCase()] = kv[1].trim().toLowerCase();
			});
			return map;
		}

		function checkEmail(emailField) {
			var val = $(emailField).val().trim().toLowerCase();
			if (!val || val.indexOf('@') === -1) return;

			var parts = val.split('@');
			if (parts.length !== 2) return;

			var local  = parts[0];
			var domain = parts[1];
			var corrected = false;
			var suggested = domain;

			/* Step 0: comma in domain → fix to dot */
			if (domain.indexOf(',') !== -1) {
				suggested = domain.replace(/,/g, '.');
				corrected = true;
			}

			/* Step 1: TLD fix */
			var dotPos = suggested.lastIndexOf('.');
			if (dotPos !== -1) {
				var tld = suggested.substring(dotPos + 1);
				if (tldFixes[tld]) {
					suggested = suggested.substring(0, dotPos + 1) + tldFixes[tld];
					corrected = true;
				}
			}

			/* Step 2: Domain name fix */
			dotPos = suggested.lastIndexOf('.');
			if (dotPos !== -1) {
				var dname = suggested.substring(0, dotPos);
				if (domainFixes[dname]) {
					suggested = domainFixes[dname] + suggested.substring(dotPos);
					corrected = true;
				}
			}

			/* Remove old hint */
			$(emailField).siblings('.rpsm-email-hint').remove();

			if (!corrected || userConfirmedEmail) return;

			var fullSuggestion = local + '@' + suggested;

			var $hint = $('<div class="rpsm-email-hint">')
				.html(
					ev.hintText + ' <span class="rpsm-email-hint__suggestion">' + fullSuggestion + '</span>?' +
					'<br>' +
					'<span class="rpsm-email-hint__btn rpsm-email-hint__btn--fix" data-action="fix">' + ev.btnFix + '</span> ' +
					'<span class="rpsm-email-hint__btn" data-action="keep">' + ev.btnKeep + '</span>'
				);

			$(emailField).after($hint);

			$hint.on('click', '.rpsm-email-hint__btn', function() {
				if ($(this).data('action') === 'fix') {
					$(emailField).val(fullSuggestion);
				} else {
					userConfirmedEmail = true;
				}
				$hint.remove();
			});
		}

		/* Bind to email field */
		$(document.body).on('blur', '#billing_email', function() {
			checkEmail(this);
		});

		/* Also check on form submit */
		$(document.body).on('checkout_error', function() {
			userConfirmedEmail = false; // reset on error so they can see hint again
		});
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  2. SCROLL BLOCK                                             */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.scrollBlock) {
		var allowScrollToNotices = false;

		/* 2a: Disable WC scroll_to_notices */
		if ($.scroll_to_notices) {
			$.scroll_to_notices = function() {};
		}

		/* 2b: MutationObserver for error/notice nodes */
		var observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(m) {
				m.addedNodes.forEach(function(node) {
					if (node.nodeType !== 1) return;
					if (node.classList && (
						node.classList.contains('woocommerce-error') ||
						node.classList.contains('woocommerce-message') ||
						node.classList.contains('woocommerce-info') ||
						node.classList.contains('wc-block-components-notice-banner')
					)) {
						allowScrollToNotices = true;
						setTimeout(function() {
							node.scrollIntoView({ behavior: 'smooth', block: 'center' });
						}, 100);
						setTimeout(function() {
							allowScrollToNotices = false;
						}, 2000);
					}
				});
			});
		});

		var checkoutForm = document.querySelector('form.checkout, .woocommerce-checkout');
		if (checkoutForm) {
			observer.observe(checkoutForm.parentNode || document.body, { childList: true, subtree: true });
		}

		/* 2c: Override $.fn.animate for scrollTop */
		var origAnimate = $.fn.animate;
		$.fn.animate = function(props) {
			if (props && 'scrollTop' in props && !allowScrollToNotices) {
				return this;
			}
			return origAnimate.apply(this, arguments);
		};

		/* 2d: Override window.scrollTo */
		var origScrollTo = window.scrollTo;
		window.scrollTo = function() {
			if (!allowScrollToNotices) return;
			origScrollTo.apply(window, arguments);
		};
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  3. PAYMENT LOGOS - trigger checkout update on gateway change */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.paymentLogos) {
		$(document.body).on('change', 'input[name="payment_method"]', function() {
			$(document.body).trigger('update_checkout');
		});

		/* Show/hide card logos based on selected gateway */
		$(document.body).on('updated_checkout', function() {
			var selected = $('input[name="payment_method"]:checked').val() || '';
			var gateway  = rpsmCheckout.paymentLogos.gateway;
			$('.card_notice').toggle(selected === gateway);
		});
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  4. EDITABLE CART - auto-update on quantity change           */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.editableCart) {
		var cartUpdateTimer;
		$(document.body).on('change', '.mv-checkout-cart .qty', function() {
			clearTimeout(cartUpdateTimer);
			var $form = $(this).closest('form');
			cartUpdateTimer = setTimeout(function() {
				$form.find('[name="update_cart"]').prop('disabled', false).trigger('click');
			}, 400);
		});
	}


	/* ══════════════════════════════════════════════════════════════ */
	/*  5. EDITABLE CART (summary_x) - X gumb u sazetku narudzbe      */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.editableCartX) {
		$(document.body).on('click', '.rpsm-review-remove', function (e) {
			e.preventDefault();
			var $btn = $(this);
			if ($btn.prop('disabled')) { return; }
			$btn.prop('disabled', true);
			$.post(rpsmCheckout.editableCartX.endpoint, {
				nonce: $btn.data('nonce'),
				cart_key: $btn.data('cart-key')
			}).done(function (res) {
				if (res && res.success && res.data && res.data.cart_empty) {
					/* Zadnja stavka - reload, server redirecta na shop */
					window.location.reload();
					return;
				}
				$(document.body).trigger('update_checkout');
			}).fail(function () {
				$btn.prop('disabled', false);
				$(document.body).trigger('update_checkout');
			});
		});
	}

})(jQuery);
