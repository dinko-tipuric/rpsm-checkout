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

		/* 2b: MutationObserver for error/notice nodes.
		   Ista poruka se kod Elementora re-renderira pri svakom AJAX updateu -
		   scrollamo samo na PRVU pojavu tog teksta, ne na svaki re-render. */
		var seenNotices = {};
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
						var noticeKey = (node.textContent || '').replace(/\s+/g, ' ').trim();
						if (seenNotices[noticeKey]) return;
						seenNotices[noticeKey] = true;
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

	/* ══════════════════════════════════════════════════════════════ */
	/*  6. ATRIBUCIJA - citanje kolacica rpsm_attr + REST push +      */
	/*     popunjavanje fallback skrivenog polja                     */
	/*                                                                */
	/*  Kolacic pise rpsm-web na www (domain=.radimposvom.com.hr) -   */
	/*  ovdje se SAMO cita, nikad ne pise. Portal na privolu ne ceka  */
	/*  (nije ista privola kao na www) - ako kolacica nema, atribucija */
	/*  ostaje prazna (narudzba ide u "bez privole", ne "direct").    */
	/* ══════════════════════════════════════════════════════════════ */

	if (rpsmCheckout.attribution) {
		var attr = rpsmCheckout.attribution;

		function rpsmReadCookie(name) {
			var match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
			return match ? decodeURIComponent(match.pop()) : '';
		}

		function rpsmGetAttrCookie() {
			var raw = rpsmReadCookie(attr.cookieName);
			if (!raw) { return null; }
			try {
				return JSON.parse(raw);
			} catch (e) {
				return null;
			}
		}

		var rpsmCookieData = rpsmGetAttrCookie();

		/* Fallback skriveno polje - popuni odmah i nakon svakog checkout
		   AJAX refresha (Elementor/WC re-renderiraju order-notes sekciju). */
		function rpsmFillFallbackField() {
			var $field = $('#rpsm_attr_payload_field');
			if ($field.length && rpsmCookieData) {
				$field.val(JSON.stringify(rpsmCookieData));
			}
		}
		rpsmFillFallbackField();
		$(document.body).on('updated_checkout', rpsmFillFallbackField);

		/* REST push - jednom po BROWSING SESIJI (sessionStorage), ne trajno.

		   ⚠️ Ne smije biti localStorage "jednom po pregledniku": kolacic rpsm_attr
		   zivi 180 dana, a WC sesija na portalu istekne za ~48 h. Povratnik koji
		   kupi tjedan dana kasnije ima NOVU (praznu) WC sesiju, pa push mora ici
		   opet - inace bi atribucija tiho nestala bas kod povratnika, a to je
		   vecina kupaca.

		   Kljuc ukljucuje i sadrzaj kolacica, pa se push ponovi i ako se izvor
		   promijeni unutar iste sesije (nova kampanja). Server je zadnja linija
		   obrane (ne prepisuje sesiju ako vec postoji). */
		if (rpsmCookieData && window.sessionStorage) {
			var rpsmRaw     = JSON.stringify(rpsmCookieData);
			var rpsmPushKey = 'rpsm_attr_pushed_' + rpsmRaw.length + '_' + (rpsmCookieData.f ? rpsmCookieData.f.ts : '0');
			var rpsmPushed  = false;

			try {
				rpsmPushed = window.sessionStorage.getItem(rpsmPushKey) === '1';
			} catch (e) { /* privatni mod - push ce ici svaki put, server no-opa */ }

			if (!rpsmPushed) {
				$.ajax({
					url: attr.restUrl,
					method: 'POST',
					contentType: 'application/json',
					data: rpsmRaw
				}).done(function() {
					/* Markiraj SAMO na uspjeh - inace bi jedan neuspjeli zahtjev
					   (403/429/offline) trajno ugasio push za tu sesiju. */
					try {
						window.sessionStorage.setItem(rpsmPushKey, '1');
					} catch (e) {}
				});
			}
		}
	}

})(jQuery);
