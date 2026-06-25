<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page — tabbed interface.
 */
final class RPSM_Checkout_Admin {

	private const NONCE_ACTION = 'rpsm_checkout_save';
	private const NONCE_FIELD  = 'rpsm_checkout_nonce';
	private const SLUG         = 'rpsm-checkout';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
	}

	/* ── Menu ──────────────────────────────────────────────────────── */

	public static function register_menu(): void {
		$parent = 'rpsm-alati';
		$hook   = null;

		if ( ! empty( $GLOBALS['admin_page_hooks'][ $parent ] ) ) {
			$hook = add_submenu_page(
				$parent,
				'RPSM Checkout',
				'Checkout',
				'manage_woocommerce',
				self::SLUG,
				[ __CLASS__, 'render_page' ]
			);
		} else {
			$hook = add_menu_page(
				'RPSM Checkout',
				'RPSM Checkout',
				'manage_woocommerce',
				self::SLUG,
				[ __CLASS__, 'render_page' ],
				'dashicons-cart',
				57
			);
		}
	}

	public static function enqueue_styles( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'rpsm-checkout-admin',
			RPSM_CHECKOUT_PLUGIN_URL . 'admin/css/rpsm-checkout-admin.css',
			[],
			RPSM_CHECKOUT_VERSION
		);

		/* WooCommerce product-search (Select2) for the switch target picker.
		 * wc-enhanced-select wires up select.wc-product-search and is localized
		 * (wc_enhanced_select_params + search nonce) by WC on all admin pages. */
		if ( function_exists( 'WC' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	/* ── Render ────────────────────────────────────────────────────── */

	public static function render_page(): void {
		/* Handle save */
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			self::handle_save();
		}

		$tabs = [
			'suglasnost'  => 'Suglasnost',
			'placanje'    => 'Plaćanje',
			'kuponi'      => 'Kuponi i košarica',
			'ux'          => 'UX',
			'polja'       => 'Polja',
			'email'       => 'Email validacija',
			'thankyou'    => 'Thank-you',
			'prijevodi'   => 'Prijevodi',
			'debug'       => 'Debug',
		];

		$active = sanitize_key( $_GET['tab'] ?? 'suglasnost' );
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'suglasnost';
		}

		echo '<div class="wrap"><h1>RPSM Checkout</h1>';

		/* Notice is shown inline next to save button, not here */

		/* Nav tabs */
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $active ) ? ' nav-tab-active' : '';
			$url   = add_query_arg( [ 'page' => self::SLUG, 'tab' => $slug ], admin_url( 'admin.php' ) );
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( $url ), $class, esc_html( $label ) );
		}
		echo '</nav>';

		/* Tab content */
		echo '<form method="post" class="rpsm-checkout-settings">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<input type="hidden" name="rpsm_checkout_tab" value="' . esc_attr( $active ) . '">';

		$method = 'tab_' . $active;
		if ( method_exists( __CLASS__, $method ) ) {
			echo '<table class="form-table">';
			self::$method();
			echo '</table>';
		}

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">Spremi postavke</button>';
		$just_saved = get_transient( 'rpsm_checkout_notice' );
		$show_class = $just_saved ? ' show' : '';
		echo '<span class="rpsm-save-confirm' . $show_class . '">&#x2713; Spremljeno</span>';
		echo '</p>';
		if ( $just_saved ) {
			delete_transient( 'rpsm_checkout_notice' );
		}
		echo '</form></div>';
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Tab methods                                                  */
	/* ══════════════════════════════════════════════════════════════ */

	private static function tab_suglasnost(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Omogući TnC checkbox', $o::LEGAL_ENABLED, 'Prikazuje obavezni checkbox za suglasnost na checkoutu.' );
		self::row_textarea( 'Tekst checkboxa', $o::LEGAL_CHECKBOX_TEXT, 'HTML nije dozvoljen. Tekst se prikazuje uz checkbox.' );
		self::row_toggle( 'Napomena za pretplate', $o::LEGAL_SUB_NOTICE, 'Prikazuje info box ispod checkboxa kad je pretplatni proizvod u košarici.' );
		self::row_textarea( 'Tekst napomene', $o::LEGAL_SUB_NOTICE_TEXT, 'Prikazan u info boxu. "NAPOMENA:" na početku će biti boldan automatski.' );
	}

	private static function tab_placanje(): void {
		$o = RPSM_Checkout_Options::class;
		echo '<tr><td colspan="2"><h3>Slike kartica (Stripe)</h3></td></tr>';
		self::row_toggle( 'Omogući slike kartica', $o::PAYMENT_LOGOS_ENABLED, 'Prikazuje slike podržanih kartica ispod checkout gumba za Stripe.' );
		self::row_text( 'URL slike', $o::PAYMENT_LOGOS_URL, 'Puni URL do slike kartica.' );
		self::row_text( 'Gateway ID', $o::PAYMENT_LOGOS_GATEWAY, 'WooCommerce payment gateway ID (npr. eh_stripe_checkout).' );

		echo '<tr><td colspan="2"><h3>BACS kontrola</h3></td></tr>';
		self::row_toggle( 'Omogući BACS kontrolu', $o::BACS_CONTROL_ENABLED, 'Skriva BACS gateway za određene proizvode osim ako je primijenjen unlock kupon.' );
		self::row_text( 'Unlock kupon', $o::BACS_CONTROL_COUPON, 'WooCommerce kupon koji otključava BACS (mora postojati u WooCommerce > Kuponi).' );
		self::row_text( 'Ograničeni proizvodi', $o::BACS_CONTROL_PRODUCTS, 'Comma-separated product ID-evi za koje je BACS skriven.' );
	}

	private static function tab_kuponi(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Sakrij kupon ako je primijenjen', $o::COUPON_HIDE_ENABLED, 'Skriva kupon formu na checkoutu kad je kupon već primijenjen.' );
		self::row_toggle( 'Kupon iz URL-a', $o::COUPON_URL_ENABLED, 'Omogućava primjenu kupona putem ?coupon=KOD u URL-u.' );

		echo '<tr><td colspan="2"><h3>Kupon kod promjene pretplate (switch)</h3></td></tr>';
		self::row_toggle( 'Auto-primijeni kupon na switch', $o::COUPON_SWITCH_ENABLED, 'Automatski primijeni kupon(e) kad korisnik mijenja pretplatu na ciljani proizvod (npr. mjesečna → polugodišnja).' );
		self::row_product_select( 'Ciljani proizvodi', $o::COUPON_SWITCH_PRODUCTS, 'Odaberi proizvode ILI varijante na koje se prelazi (npr. polugodišnji). Obavezno - kupon se primjenjuje samo ako switch sadrži neki od ovih.' );
		self::row_text( 'Kupon za sve obnove (grandfather)', $o::COUPON_SWITCH_CODE_RECUR, 'GLAVNO polje za trajni popust. Kod kupona tipa "Recurring Product Discount" (dolazi s WooCommerce Subscriptions) - skida iznos sa SVAKE obnove i sprema se na pretplatu, pa su obnove trajno snižene (npr. 399 → 299). Ovo grandfathera cijenu switcheru.' );
		self::row_text( 'Jednokratni kupon (samo upfront, NE grandfathera)', $o::COUPON_SWITCH_CODE_ONCE, 'Opcionalno. Kod kupona tipa "Fiksni popust na košaricu" - skida iznos SAMO s upfront plaćanja na switchu, NE s obnova. Obnove ostaju pune cijene. Koristi samo ako želiš dodatni sweetener na prvi iznos; za trajni 299 koristi gornje polje.' );
		self::row_toggle( 'Preskoči kupon ako je proizvod na popustu', $o::COUPON_SWITCH_SKIP_ON_SALE, 'Kad je ciljani proizvod na sniženju (sale price), switch već grandfathera tu sniženu cijenu na pretplatu sam od sebe - pa se kupon NE primjenjuje (da ne bude dvostruki popust). Kupon vrijedi samo kad proizvod nije na popustu. NIKAD ne briše već primijenjene kupone ni postojeće pretplate.' );
		self::row_toggle( 'Prikaži kupon polje na switchu', $o::COUPON_SWITCH_SHOW_FIELD, 'Prisilno prikaže polje za ručni unos kupona na checkoutu dok traje switch (zaobilazi Elementorov Coupon toggle). Nestaje čim je kupon primijenjen.' );

		echo '<tr><td colspan="2"><h3>Košarica i gumbi</h3></td></tr>';
		self::row_toggle( 'Uređiva košarica na checkoutu', $o::EDITABLE_CART_ENABLED, 'Prikazuje mini košaricu s mogućnošću promjene količine i uklanjanja stavki.' );
		self::row_toggle( 'Buy Now gumb', $o::BUY_NOW_ENABLED, 'Dodaje "Idi na plaćanje" gumb na stranici proizvoda (simple products).' );
		self::row_text( 'Tekst Buy Now gumba', $o::BUY_NOW_TEXT );
	}

	private static function tab_ux(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Blokada auto-scrolla', $o::SCROLL_BLOCK_ENABLED, 'Blokira WooCommerce automatski scroll na vrh checkouta. Scroll dozvoljen samo na error/notice poruke.' );
	}

	private static function tab_polja(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Shipping telefon', $o::SHIPPING_PHONE_ENABLED, 'Dodaje obavezno polje za telefon u shipping sekciju checkouta.' );
		self::row_toggle( 'Email kao username', $o::EMAIL_AS_USERNAME_ENABLED, 'Koristi puni email kao korisničko ime (umjesto dijela prije @). Konzistentno s MemberPressom.' );
	}

	private static function tab_email(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Omogući email validaciju', $o::EMAIL_VAL_ENABLED, 'JS prijedlog ispravke + PHP hard stop za krive email adrese.' );

		echo '<tr><td colspan="2"><h3>Tekstovi</h3></td></tr>';
		self::row_text( 'Hint tekst', $o::EMAIL_VAL_HINT_TEXT, 'Tekst ispred prijedloga ispravke (npr. "Misliš li možda na").' );
		self::row_text( 'Gumb "Ispravi"', $o::EMAIL_VAL_BTN_FIX );
		self::row_text( 'Gumb "Zadrži"', $o::EMAIL_VAL_BTN_KEEP );
		self::row_text( 'Error: zarez', $o::EMAIL_VAL_ERR_COMMA, 'PHP error poruka za zarez u email domeni.' );
		self::row_text( 'Error: TLD', $o::EMAIL_VAL_ERR_TLD, 'PHP error poruka za neispravan TLD.' );
		self::row_text( 'Error: domena', $o::EMAIL_VAL_ERR_DOMAIN, 'PHP error poruka za neispravnu domenu.' );

		echo '<tr><td colspan="2"><h3>Liste ispravki</h3></td></tr>';
		self::row_textarea( 'TLD ispravke', $o::EMAIL_VAL_TLD_FIXES, 'Format: krivi:ispravni (comma-separated). Npr. con:com,cmo:com' );
		self::row_textarea( 'Domain ispravke', $o::EMAIL_VAL_DOMAIN_FIXES, 'Format: krivi:ispravni (comma-separated). Npr. gnail:gmail,gmali:gmail' );
	}

	private static function tab_thankyou(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Omogući Stripe redirect', $o::THANKYOU_ENABLED, 'Nakon uspješnog Stripe plaćanja redirecta na custom stranicu.' );
		self::row_text( 'Gateway ID', $o::THANKYOU_GATEWAY, 'Stripe gateway ID (npr. eh_stripe_checkout).' );
		self::row_text( 'Naslov', $o::THANKYOU_TITLE );
		self::row_text( 'Tekst gumba', $o::THANKYOU_BTN_TEXT );
		self::row_text( 'Redirect URL', $o::THANKYOU_REDIRECT_URL, 'Relativni ili apsolutni URL (npr. /hq).' );
		self::row_text( 'GTM timeout (ms)', $o::THANKYOU_GTM_TIMEOUT, 'Koliko ms čekati GTM purchase event prije fallbacka.' );
		self::row_text( 'Fallback poruka', $o::THANKYOU_FALLBACK_MSG, 'Prikazano ako GTM ne pošalje purchase event.' );
	}

	private static function tab_prijevodi(): void {
		$o    = RPSM_Checkout_Options::class;
		$raw  = RPSM_Checkout_Options::get( $o::TRANSLATIONS_ENABLED );
		self::row_toggle( 'Omogući prijevode', $o::TRANSLATIONS_ENABLED, 'Gettext override za WooCommerce i Elementor Pro stringove.' );

		$pairs = json_decode( RPSM_Checkout_Options::get( $o::TRANSLATIONS_PAIRS ), true ) ?: [];

		echo '</table>';
		echo '<h3>Parovi prijevoda</h3>';
		echo '<p class="description">Svaki red: originalni tekst → prijevod (domain: woocommerce ili elementor-pro).</p>';
		echo '<table class="widefat rpsm-translations-table" id="rpsm-translations">';
		echo '<thead><tr><th>Original</th><th>Prijevod</th><th>Domain</th><th></th></tr></thead><tbody>';

		foreach ( $pairs as $i => $pair ) {
			self::translation_row( $i, $pair );
		}

		echo '</tbody></table>';
		echo '<button type="button" class="button" id="rpsm-add-translation" style="margin-top:8px;">+ Dodaj prijevod</button>';
		echo '<script>
			document.getElementById("rpsm-add-translation").addEventListener("click", function(){
				var tbody = document.querySelector("#rpsm-translations tbody");
				var i = tbody.children.length;
				var tr = document.createElement("tr");
				tr.innerHTML = \'<td><input type="text" name="rpsm_tr[\'+i+\'][original]" class="large-text"></td>\'+
					\'<td><input type="text" name="rpsm_tr[\'+i+\'][translation]" class="large-text"></td>\'+
					\'<td><select name="rpsm_tr[\'+i+\'][domain]"><option value="woocommerce">woocommerce</option><option value="elementor-pro">elementor-pro</option></select></td>\'+
					\'<td><button type="button" class="button" onclick="this.closest(\\\'tr\\\').remove()">✕</button></td>\';
				tbody.appendChild(tr);
			});
		</script>';
		echo '<table class="form-table">';
	}

	private static function translation_row( int $i, array $pair ): void {
		$orig  = esc_attr( $pair['original'] ?? '' );
		$trans = esc_attr( $pair['translation'] ?? '' );
		$dom   = $pair['domain'] ?? 'woocommerce';
		echo "<tr>";
		echo "<td><input type='text' name='rpsm_tr[{$i}][original]' value='{$orig}' class='large-text'></td>";
		echo "<td><input type='text' name='rpsm_tr[{$i}][translation]' value='{$trans}' class='large-text'></td>";
		echo "<td><select name='rpsm_tr[{$i}][domain]'>";
		echo "<option value='woocommerce'" . selected( $dom, 'woocommerce', false ) . ">woocommerce</option>";
		echo "<option value='elementor-pro'" . selected( $dom, 'elementor-pro', false ) . ">elementor-pro</option>";
		echo "</select></td>";
		echo "<td><button type='button' class='button' onclick='this.closest(\"tr\").remove()'>✕</button></td>";
		echo "</tr>";
	}

	private static function tab_debug(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Debug mod', $o::DEBUG_MODE, 'Zapisuje detaljne logove u wp-content/uploads/rpsm-checkout/.' );

		echo '</table>';

		/* Clear log button */
		if ( isset( $_POST['rpsm_checkout_clear_log'] ) ) {
			RPSM_Checkout_Debug::clear_log();
			echo '<div class="notice notice-success"><p>Log obrisan.</p></div>';
		}

		echo '<p><button type="submit" name="rpsm_checkout_clear_log" value="1" class="button">Obriši log</button></p>';

		$log = RPSM_Checkout_Debug::read_log( 100 );
		echo '<h3>Zadnjih 100 unosa</h3>';
		echo '<pre style="background:#1d2327;color:#c3c4c7;padding:16px;max-height:500px;overflow:auto;font-size:12px;border-radius:4px;">';
		echo $log ? esc_html( $log ) : '<em>Log je prazan.</em>';
		echo '</pre>';
		echo '<table class="form-table">';
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Save handler                                                 */
	/* ══════════════════════════════════════════════════════════════ */

	private static function handle_save(): void {
		$tab = sanitize_key( $_POST['rpsm_checkout_tab'] ?? '' );

		/* Clear log is handled in tab_debug render */
		if ( isset( $_POST['rpsm_checkout_clear_log'] ) ) {
			return;
		}

		$o = RPSM_Checkout_Options::class;

		/* Map of option key → sanitize callback */
		$fields = self::get_saveable_fields( $tab );

		foreach ( $fields as $key => $type ) {
			if ( $type === 'toggle' ) {
				$val = isset( $_POST[ $key ] ) ? '1' : '0';
			} elseif ( $type === 'textarea' ) {
				$val = sanitize_textarea_field( $_POST[ $key ] ?? '' );
			} elseif ( $type === 'product_ids' ) {
				$raw = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : [];
				$val = implode( ',', array_filter( array_map( 'absint', $raw ) ) );
			} else {
				$val = sanitize_text_field( $_POST[ $key ] ?? '' );
			}
			RPSM_Checkout_Options::set( $key, $val );
		}

		/* Special: translation pairs */
		if ( 'prijevodi' === $tab && isset( $_POST['rpsm_tr'] ) ) {
			$pairs = [];
			foreach ( $_POST['rpsm_tr'] as $row ) {
				$orig  = sanitize_text_field( $row['original'] ?? '' );
				$trans = sanitize_text_field( $row['translation'] ?? '' );
				$dom   = sanitize_text_field( $row['domain'] ?? 'woocommerce' );
				if ( '' !== $orig && '' !== $trans ) {
					$pairs[] = [ 'original' => $orig, 'translation' => $trans, 'domain' => $dom ];
				}
			}
			RPSM_Checkout_Options::set( $o::TRANSLATIONS_PAIRS, wp_json_encode( $pairs, JSON_UNESCAPED_UNICODE ) );
		}

		RPSM_Checkout_Options::reset_cache();
		set_transient( 'rpsm_checkout_notice', 'Postavke spremljene.', 30 );
	}

	/**
	 * Return saveable fields for a given tab.
	 */
	private static function get_saveable_fields( string $tab ): array {
		$o = RPSM_Checkout_Options::class;

		$map = [
			'suglasnost' => [
				$o::LEGAL_ENABLED         => 'toggle',
				$o::LEGAL_CHECKBOX_TEXT   => 'textarea',
				$o::LEGAL_SUB_NOTICE     => 'toggle',
				$o::LEGAL_SUB_NOTICE_TEXT => 'textarea',
			],
			'placanje' => [
				$o::PAYMENT_LOGOS_ENABLED => 'toggle',
				$o::PAYMENT_LOGOS_URL     => 'text',
				$o::PAYMENT_LOGOS_GATEWAY => 'text',
				$o::BACS_CONTROL_ENABLED  => 'toggle',
				$o::BACS_CONTROL_COUPON   => 'text',
				$o::BACS_CONTROL_PRODUCTS => 'text',
			],
			'kuponi' => [
				$o::COUPON_HIDE_ENABLED   => 'toggle',
				$o::COUPON_URL_ENABLED    => 'toggle',
				$o::COUPON_SWITCH_ENABLED    => 'toggle',
				$o::COUPON_SWITCH_PRODUCTS   => 'product_ids',
				$o::COUPON_SWITCH_CODE_ONCE  => 'text',
				$o::COUPON_SWITCH_CODE_RECUR => 'text',
				$o::COUPON_SWITCH_SKIP_ON_SALE => 'toggle',
				$o::COUPON_SWITCH_SHOW_FIELD => 'toggle',
				$o::EDITABLE_CART_ENABLED => 'toggle',
				$o::BUY_NOW_ENABLED       => 'toggle',
				$o::BUY_NOW_TEXT          => 'text',
			],
			'ux' => [
				$o::SCROLL_BLOCK_ENABLED => 'toggle',
			],
			'polja' => [
				$o::SHIPPING_PHONE_ENABLED    => 'toggle',
				$o::EMAIL_AS_USERNAME_ENABLED => 'toggle',
			],
			'email' => [
				$o::EMAIL_VAL_ENABLED     => 'toggle',
				$o::EMAIL_VAL_HINT_TEXT   => 'text',
				$o::EMAIL_VAL_BTN_FIX    => 'text',
				$o::EMAIL_VAL_BTN_KEEP   => 'text',
				$o::EMAIL_VAL_ERR_COMMA  => 'text',
				$o::EMAIL_VAL_ERR_TLD    => 'text',
				$o::EMAIL_VAL_ERR_DOMAIN => 'text',
				$o::EMAIL_VAL_TLD_FIXES   => 'textarea',
				$o::EMAIL_VAL_DOMAIN_FIXES => 'textarea',
			],
			'thankyou' => [
				$o::THANKYOU_ENABLED      => 'toggle',
				$o::THANKYOU_GATEWAY      => 'text',
				$o::THANKYOU_TITLE        => 'text',
				$o::THANKYOU_BTN_TEXT     => 'text',
				$o::THANKYOU_REDIRECT_URL => 'text',
				$o::THANKYOU_GTM_TIMEOUT  => 'text',
				$o::THANKYOU_FALLBACK_MSG => 'text',
			],
			'prijevodi' => [
				$o::TRANSLATIONS_ENABLED => 'toggle',
			],
			'debug' => [
				$o::DEBUG_MODE => 'toggle',
			],
		];

		return $map[ $tab ] ?? [];
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Row helpers                                                  */
	/* ══════════════════════════════════════════════════════════════ */

	private static function row_toggle( string $label, string $key, string $hint = '' ): void {
		$val = RPSM_Checkout_Options::get( $key );
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( '1', $val, false ) . '> Omogućeno</label>';
		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function row_text( string $label, string $key, string $hint = '' ): void {
		$val = RPSM_Checkout_Options::get( $key );
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="large-text">';
		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * WooCommerce product/variation search (Select2 multiselect).
	 * Stores selected IDs as a comma-separated string (compatible with
	 * RPSM_Checkout_Options::get_product_ids()).
	 */
	private static function row_product_select( string $label, string $key, string $hint = '' ): void {
		$raw = RPSM_Checkout_Options::get( $key );
		$ids = array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) );

		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<select name="' . esc_attr( $key ) . '[]" multiple="multiple" class="wc-product-search" style="width:100%;max-width:600px;"'
			. ' data-placeholder="Pretraži proizvode..."'
			. ' data-action="woocommerce_json_search_products_and_variations"'
			. ' data-allow_clear="true">';

		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				/* Keep unknown IDs selectable so a stale/deleted product isn't silently dropped on save. */
				echo '<option value="' . esc_attr( $id ) . '" selected="selected">#' . esc_html( $id ) . ' (nepoznat proizvod)</option>';
				continue;
			}
			echo '<option value="' . esc_attr( $id ) . '" selected="selected">'
				. esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
		}

		echo '</select>';
		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function row_textarea( string $label, string $key, string $hint = '' ): void {
		$val = RPSM_Checkout_Options::get( $key );
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td>';
		echo '<textarea name="' . esc_attr( $key ) . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
		if ( $hint ) {
			echo '<p class="description">' . esc_html( $hint ) . '</p>';
		}
		echo '</td></tr>';
	}
}
