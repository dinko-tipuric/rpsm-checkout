<?php
defined( 'ABSPATH' ) || exit;

/**
 * Centralised options - constants + defaults + get/set.
 */
final class RPSM_Checkout_Options {

	/* ── Legal ─────────────────────────────────────────────────────── */
	const LEGAL_ENABLED         = 'rpsm_checkout_legal_enabled';
	const LEGAL_CHECKBOX_TEXT   = 'rpsm_checkout_legal_checkbox_text';
	const LEGAL_SUB_NOTICE     = 'rpsm_checkout_legal_sub_notice';
	const LEGAL_SUB_NOTICE_TEXT = 'rpsm_checkout_legal_sub_notice_text';

	/* ── Payment Display ───────────────────────────────────────────── */
	const PAYMENT_LOGOS_ENABLED = 'rpsm_checkout_payment_logos_enabled';
	const PAYMENT_LOGOS_URL     = 'rpsm_checkout_payment_logos_url';
	const PAYMENT_LOGOS_GATEWAY = 'rpsm_checkout_payment_logos_gateway';

	/* ── BACS Control ──────────────────────────────────────────────── */
	const BACS_CONTROL_ENABLED  = 'rpsm_checkout_bacs_control_enabled';
	const BACS_CONTROL_COUPON   = 'rpsm_checkout_bacs_control_coupon';
	const BACS_CONTROL_PRODUCTS = 'rpsm_checkout_bacs_control_products';

	/* ── Coupons ───────────────────────────────────────────────────── */
	const COUPON_HIDE_ENABLED   = 'rpsm_checkout_coupon_hide_enabled';
	const COUPON_URL_ENABLED    = 'rpsm_checkout_coupon_url_enabled';
	const COUPON_MULTI_ENABLED  = 'rpsm_checkout_coupon_multi_enabled'; // ?add-to-cart=X,Y - ⚠️ default OFF dok portal snippet zivi

	/* ── Coupons: Switch (promjena pretplate) ──────────────────────── */
	const COUPON_SWITCH_ENABLED    = 'rpsm_checkout_coupon_switch_enabled';
	const COUPON_SWITCH_PRODUCTS   = 'rpsm_checkout_coupon_switch_products';
	const COUPON_SWITCH_CODE_ONCE  = 'rpsm_checkout_coupon_switch_code_once';
	const COUPON_SWITCH_CODE_RECUR = 'rpsm_checkout_coupon_switch_code_recur';
	const COUPON_SWITCH_SHOW_FIELD = 'rpsm_checkout_coupon_switch_show_field';
	const COUPON_SWITCH_SKIP_ON_SALE = 'rpsm_checkout_coupon_switch_skip_on_sale';

	/* ── Editable Cart ─────────────────────────────────────────────── */
	const EDITABLE_CART_ENABLED = 'rpsm_checkout_editable_cart_enabled';
	const EDITABLE_CART_MODE    = 'rpsm_checkout_editable_cart_mode'; // 'table' | 'summary_x'

	/* ── Scroll Block ──────────────────────────────────────────────── */
	const SCROLL_BLOCK_ENABLED  = 'rpsm_checkout_scroll_block_enabled';

	/* ── Single Purchase (jednokratna kupnja) ──────────────────────── */
	const SINGLE_PURCHASE_ENABLED   = 'rpsm_checkout_single_purchase_enabled';
	const SINGLE_PURCHASE_PRODUCTS  = 'rpsm_checkout_single_purchase_products';
	const SINGLE_PURCHASE_MESSAGE   = 'rpsm_checkout_single_purchase_message';
	const SINGLE_PURCHASE_LINK_TEXT = 'rpsm_checkout_single_purchase_link_text';

	/* ── Buy Now ───────────────────────────────────────────────────── */
	const BUY_NOW_ENABLED       = 'rpsm_checkout_buy_now_enabled';
	const BUY_NOW_TEXT          = 'rpsm_checkout_buy_now_text';

	/* ── Email Validation ──────────────────────────────────────────── */
	const EMAIL_VAL_ENABLED     = 'rpsm_checkout_email_val_enabled';
	const EMAIL_VAL_TLD_FIXES   = 'rpsm_checkout_email_val_tld_fixes';
	const EMAIL_VAL_DOMAIN_FIXES = 'rpsm_checkout_email_val_domain_fixes';
	const EMAIL_VAL_HINT_TEXT   = 'rpsm_checkout_email_val_hint_text';
	const EMAIL_VAL_BTN_FIX    = 'rpsm_checkout_email_val_btn_fix';
	const EMAIL_VAL_BTN_KEEP   = 'rpsm_checkout_email_val_btn_keep';
	const EMAIL_VAL_ERR_COMMA  = 'rpsm_checkout_email_val_err_comma';
	const EMAIL_VAL_ERR_TLD    = 'rpsm_checkout_email_val_err_tld';
	const EMAIL_VAL_ERR_DOMAIN = 'rpsm_checkout_email_val_err_domain';

	/* ── Thank-you (Stripe) ────────────────────────────────────────── */
	const THANKYOU_ENABLED      = 'rpsm_checkout_thankyou_enabled';
	const THANKYOU_TITLE        = 'rpsm_checkout_thankyou_title';
	const THANKYOU_BTN_TEXT     = 'rpsm_checkout_thankyou_btn_text';
	const THANKYOU_REDIRECT_URL = 'rpsm_checkout_thankyou_redirect_url';
	const THANKYOU_GTM_TIMEOUT  = 'rpsm_checkout_thankyou_gtm_timeout';
	const THANKYOU_FALLBACK_MSG = 'rpsm_checkout_thankyou_fallback_msg';
	const THANKYOU_GATEWAY      = 'rpsm_checkout_thankyou_gateway';
	const THANKYOU_FREE_ENABLED = 'rpsm_checkout_thankyou_free_enabled';
	const THANKYOU_FREE_TITLE   = 'rpsm_checkout_thankyou_free_title';

	/* ── Translations ──────────────────────────────────────────────── */
	const TRANSLATIONS_ENABLED  = 'rpsm_checkout_translations_enabled';
	const TRANSLATIONS_PAIRS    = 'rpsm_checkout_translations_pairs';

	/* ── Fields ────────────────────────────────────────────────────── */
	const SHIPPING_PHONE_ENABLED    = 'rpsm_checkout_shipping_phone_enabled';
	const EMAIL_AS_USERNAME_ENABLED = 'rpsm_checkout_email_as_username_enabled';

	/* ── Atribucija (SPEC-atribucija.md, Sloj 2) ──────────────────────── */
	const ATTR_ENABLED         = 'rpsm_checkout_attr_enabled';
	const ATTR_RETENTION_DAYS  = 'rpsm_checkout_attr_retention_days';
	const ATTR_CAPTURE_ENABLED = 'rpsm_checkout_attr_capture_enabled'; // pisi kolacic i NA portalu (direktan promet na product page)
	const ATTR_CONSENT_CAT     = 'rpsm_checkout_attr_consent_cat';     // Complianz kategorija (portalov banner)

	/* ── Express stranica (SPEC-express-checkout.md) ───────────────── */
	const EXPRESS_ENABLED         = 'rpsm_checkout_express_enabled';
	const EXPRESS_CLOBBER         = 'rpsm_checkout_express_clobber';
	const EXPRESS_FIRST_GATEWAY   = 'rpsm_checkout_express_first_gateway';
	const EXPRESS_STICKY_CTA      = 'rpsm_checkout_express_sticky_cta';
	const EXPRESS_STICKY_CTA_TEXT = 'rpsm_checkout_express_sticky_cta_text';
	const EXPRESS_OWNED_MESSAGE   = 'rpsm_checkout_express_owned_message';
	const EXPRESS_OWNED_LINK_TEXT = 'rpsm_checkout_express_owned_link_text';

	/* ── Sadrzaj proizvoda (prodajni blokovi, shortcodovi) ─────────── */
	const CONTENT_ENABLED    = 'rpsm_checkout_content_enabled';
	const CONTENT_GLOBAL_FAQ = 'rpsm_checkout_content_global_faq';

	/* ── Debug ─────────────────────────────────────────────────────── */
	const DEBUG_MODE             = 'rpsm_checkout_debug_mode';

	/* ── Defaults ──────────────────────────────────────────────────── */
	private static array $defaults = [
		/* Legal */
		self::LEGAL_ENABLED         => '1',
		self::LEGAL_CHECKBOX_TEXT   => 'Dajem svoju izričitu suglasnost da izvršenje ugovora započne prije isteka roka za odustajanje. Time potvrđujem da gubim svoje pravo na raskid.',
		self::LEGAL_SUB_NOTICE     => '1',
		self::LEGAL_SUB_NOTICE_TEXT => 'NAPOMENA: Pretplatu u biz ARENI je moguće otkazati u svakom trenutku za buduća razdoblja.',

		/* Payment Display */
		self::PAYMENT_LOGOS_ENABLED => '1',
		self::PAYMENT_LOGOS_URL     => 'https://portal.radimposvom.com.hr/wp-content/uploads/2024/09/Card-payment.webp',
		self::PAYMENT_LOGOS_GATEWAY => 'eh_stripe_checkout',

		/* BACS Control */
		self::BACS_CONTROL_ENABLED  => '1',
		self::BACS_CONTROL_COUPON   => 'BACS',
		self::BACS_CONTROL_PRODUCTS => '5443,5447',

		/* Coupons */
		self::COUPON_HIDE_ENABLED   => '1',
		self::COUPON_URL_ENABLED    => '1',
		self::COUPON_MULTI_ENABLED  => '0',

		/* Coupons: Switch */
		self::COUPON_SWITCH_ENABLED    => '0',
		self::COUPON_SWITCH_PRODUCTS   => '',
		self::COUPON_SWITCH_CODE_ONCE  => '',
		self::COUPON_SWITCH_CODE_RECUR => '',
		self::COUPON_SWITCH_SHOW_FIELD => '0',
		self::COUPON_SWITCH_SKIP_ON_SALE => '1',

		/* Editable Cart - default summary_x od v1.4.0.0 (portal ga koristi;
		   'table' je legacy, deprecated, brisanje u v1.5) */
		self::EDITABLE_CART_ENABLED => '1',
		self::EDITABLE_CART_MODE    => 'summary_x',

		/* Scroll Block */
		self::SCROLL_BLOCK_ENABLED  => '1',

		/* Single Purchase - Dinkova lista 2026-07-12 (digitalni programi;
		   pretplate/MASTERMIND/grupe NISU na listi) */
		self::SINGLE_PURCHASE_ENABLED   => '1',
		self::SINGLE_PURCHASE_PRODUCTS  => '1539,1154,8144,4798,1312,7450,1500,4806,10363,1482,425',
		self::SINGLE_PURCHASE_MESSAGE   => 'Program "{proizvod}" već imaš - nalazi se u tvom računu i ne može se kupiti dvaput.',
		self::SINGLE_PURCHASE_LINK_TEXT => 'Otvori Moj račun',

		/* Buy Now */
		self::BUY_NOW_ENABLED       => '1',
		self::BUY_NOW_TEXT          => 'Idi na plaćanje',

		/* Email Validation */
		self::EMAIL_VAL_ENABLED     => '1',
		self::EMAIL_VAL_TLD_FIXES   => 'con:com,cmo:com,cpm:com,ocm:com,comn:com,vom:com,xom:com,nte:net,nett:net,orgg:org',
		self::EMAIL_VAL_DOMAIN_FIXES => 'gnail:gmail,gmali:gmail,gamil:gmail,gmal:gmail,gmaill:gmail,gmai:gmail,gmail.co:gmail,yahooo:yahoo,yaho:yahoo,yhoo:yahoo,yahou:yahoo,hotmial:hotmail,hotmaill:hotmail,hotmai:hotmail,htomail:hotmail,hotmal:hotmail,outloook:outlook,outlok:outlook,otulook:outlook',
		self::EMAIL_VAL_HINT_TEXT   => 'Misliš li možda na',
		self::EMAIL_VAL_BTN_FIX    => 'Ispravi',
		self::EMAIL_VAL_BTN_KEEP   => 'Zadrži kako je',
		self::EMAIL_VAL_ERR_COMMA  => 'E-mail adresa sadrži zarez u domeni. Ispravi adresu.',
		self::EMAIL_VAL_ERR_TLD    => 'E-mail adresa ima neispravan završetak domene. Provjeri adresu.',
		self::EMAIL_VAL_ERR_DOMAIN => 'E-mail adresa sadrži neispravnu domenu. Provjeri adresu.',

		/* Thank-you */
		self::THANKYOU_ENABLED      => '1',
		self::THANKYOU_TITLE        => 'Hvala! Plaćanje je uspješno.',
		self::THANKYOU_BTN_TEXT     => 'Uđi u HQ',
		self::THANKYOU_REDIRECT_URL => '/hq',
		self::THANKYOU_GTM_TIMEOUT  => '5000',
		self::THANKYOU_FALLBACK_MSG => 'Klikni gumb ispod za nastavak.',
		self::THANKYOU_GATEWAY      => 'eh_stripe_checkout',
		self::THANKYOU_FREE_ENABLED => '1',
		self::THANKYOU_FREE_TITLE   => 'Hvala! Narudžba je uspješna.',

		/* Translations */
		self::TRANSLATIONS_ENABLED  => '1',
		self::TRANSLATIONS_PAIRS    => '', // populated by activator

		/* Fields */
		self::SHIPPING_PHONE_ENABLED    => '1',
		self::EMAIL_AS_USERNAME_ENABLED => '1',

		/* Atribucija - primarni put je WC sesija (ne skriveno polje), vidi
		   includes/modules/class-module-attribution.php za arhitekturu */
		self::ATTR_ENABLED        => '1',
		self::ATTR_RETENTION_DAYS => '30',
		self::ATTR_CAPTURE_ENABLED => '1',
		self::ATTR_CONSENT_CAT     => 'statistics',

		/* Express - default OFF: pali se tek kad express stranice postoje */
		self::EXPRESS_ENABLED         => '0',
		self::EXPRESS_CLOBBER         => '1',
		self::EXPRESS_FIRST_GATEWAY   => 'eh_stripe_checkout',
		self::EXPRESS_STICKY_CTA      => '1',
		self::EXPRESS_STICKY_CTA_TEXT => 'Naruči',
		self::EXPRESS_OWNED_MESSAGE   => 'Program "{proizvod}" već imaš - nalazi se u tvom računu.',
		self::EXPRESS_OWNED_LINK_TEXT => 'Otvori Moj račun',

		/* Sadrzaj proizvoda - ON, ali inertno dok proizvodi nemaju podatke */
		self::CONTENT_ENABLED    => '1',
		self::CONTENT_GLOBAL_FAQ => '',

		/* Debug */
		self::DEBUG_MODE             => '0',
	];

	/* ── Getter / Setter ───────────────────────────────────────────── */

	public static function get( string $key, $override_default = null ) {
		$default = $override_default ?? ( self::$defaults[ $key ] ?? '' );
		return get_option( $key, $default );
	}

	public static function set( string $key, $value ): void {
		update_option( $key, $value );
	}

	public static function get_default( string $key ) {
		return self::$defaults[ $key ] ?? '';
	}

	public static function get_all_keys(): array {
		return array_keys( self::$defaults );
	}

	/**
	 * Reset cached values after save (debug class etc.).
	 */
	public static function reset_cache(): void {
		RPSM_Checkout_Debug::reset_cache();
	}

	/**
	 * Parse comma-separated product IDs into int array.
	 */
	public static function get_product_ids( string $key ): array {
		$raw = self::get( $key );
		return array_filter( array_map( 'intval', explode( ',', $raw ) ) );
	}
}
