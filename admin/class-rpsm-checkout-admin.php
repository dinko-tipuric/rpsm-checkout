<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page - tabbed interface.
 */
final class RPSM_Checkout_Admin {

	private const NONCE_ACTION = 'rpsm_checkout_save';
	private const NONCE_FIELD  = 'rpsm_checkout_nonce';
	private const SLUG         = 'rpsm-checkout';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
		add_filter( 'admin_body_class', [ __CLASS__, 'admin_body_class' ] );
	}

	/* ── Menu ──────────────────────────────────────────────────────── */

	public static function register_menu(): void {
		$parent = 'rpsm-alati';
		$hook   = null;

		if ( ! empty( $GLOBALS['admin_page_hooks'][ $parent ] ) ) {
			add_submenu_page(
				$parent,
				'RPSM Checkout',
				'Checkout',
				'manage_woocommerce',
				self::SLUG,
				[ __CLASS__, 'render_page' ]
			);
		} else {
			add_menu_page(
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

		/* RPSM Admin UI kit (prije plugin CSS-a) + Poppins za naslove */
		wp_enqueue_style(
			'rpsm-kit-poppins',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap',
			[],
			null
		);
		wp_enqueue_style(
			'rpsm-admin-kit',
			RPSM_CHECKOUT_PLUGIN_URL . 'admin/css/rpsm-admin-kit.css',
			[],
			RPSM_CHECKOUT_VERSION
		);

		wp_enqueue_style(
			'rpsm-checkout-admin',
			RPSM_CHECKOUT_PLUGIN_URL . 'admin/css/rpsm-checkout-admin.css',
			[ 'rpsm-admin-kit' ],
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

	/**
	 * Body klasa 'rpsm-kit-page' samo na stranici ovog plugina (UI kit pozadina).
	 */
	public static function admin_body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::SLUG ) ) {
			$classes .= ' rpsm-kit-page';
		}
		return $classes;
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
			'kupnje'      => 'Jednokratna kupnja',
			'ux'          => 'UX',
			'polja'       => 'Polja',
			'email'       => 'Email validacija',
			'thankyou'    => 'Thank-you',
			'prijevodi'   => 'Prijevodi',
			'atribucija'  => 'Atribucija',
			'express'     => 'Express',
			'sadrzaj'     => 'Sadržaj',
			'debug'       => 'Debug',
		];

		$active = sanitize_key( $_GET['tab'] ?? 'suglasnost' );
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'suglasnost';
		}

		echo '<div class="wrap rpsm-admin"><h1>RPSM Checkout</h1>';

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
		self::row_toggle( 'Multiproduct link (?add-to-cart=X,Y)', $o::COUPON_MULTI_ENABLED, '⚠️ UKLJUČI TEK KAD UGASIŠ portal snippet "add multiple products" - istovremeni rad duplo puni košaricu! Više proizvoda jednim linkom, radi s &coupon=KOD bez duple primjene kupona.' );

		echo '<tr><td colspan="2"><h3>Kupon kod promjene pretplate (switch)</h3></td></tr>';
		self::row_toggle( 'Auto-primijeni kupon na switch', $o::COUPON_SWITCH_ENABLED, 'Automatski primijeni kupon(e) kad korisnik mijenja pretplatu na ciljani proizvod (npr. mjesečna → polugodišnja).' );
		self::row_product_select( 'Ciljani proizvodi', $o::COUPON_SWITCH_PRODUCTS, 'Odaberi proizvode ILI varijante na koje se prelazi (npr. polugodišnji). Obavezno - kupon se primjenjuje samo ako switch sadrži neki od ovih.' );
		self::row_text( 'Kupon za sve obnove (grandfather)', $o::COUPON_SWITCH_CODE_RECUR, 'GLAVNO polje za trajni popust. Kod kupona tipa "Recurring Product Discount" (dolazi s WooCommerce Subscriptions) - skida iznos sa SVAKE obnove i sprema se na pretplatu, pa su obnove trajno snižene (npr. 399 → 299). Ovo grandfathera cijenu switcheru.' );
		self::row_text( 'Jednokratni kupon (samo upfront, NE grandfathera)', $o::COUPON_SWITCH_CODE_ONCE, 'Opcionalno. Kod kupona tipa "Fiksni popust na košaricu" - skida iznos SAMO s upfront plaćanja na switchu, NE s obnova. Obnove ostaju pune cijene. Koristi samo ako želiš dodatni sweetener na prvi iznos; za trajni 299 koristi gornje polje.' );
		self::row_toggle( 'Preskoči kupon ako je proizvod na popustu', $o::COUPON_SWITCH_SKIP_ON_SALE, 'Kad je ciljani proizvod na sniženju (sale price), switch već grandfathera tu sniženu cijenu na pretplatu sam od sebe - pa se kupon NE primjenjuje (da ne bude dvostruki popust). Ako je kupon ostao u košarici iz ranijeg koraka, makne ga IZ KOŠARICE (prije kupnje). Postojeće pretplate se NIKAD ne diraju - njihov grandfather kupon živi na pretplati, ne u košarici.' );
		self::row_toggle( 'Prikaži kupon polje na switchu', $o::COUPON_SWITCH_SHOW_FIELD, 'Prisilno prikaže polje za ručni unos kupona na checkoutu dok traje switch (zaobilazi Elementorov Coupon toggle). Nestaje čim je kupon primijenjen.' );

		echo '<tr><td colspan="2"><h3>Košarica i gumbi</h3></td></tr>';
		self::row_toggle( 'Uređiva košarica na checkoutu', $o::EDITABLE_CART_ENABLED, 'Omogućava kupcu uklanjanje stavki na checkoutu (način prikaza dolje).' );

		$mode = RPSM_Checkout_Options::get( $o::EDITABLE_CART_MODE );
		echo '<tr><th scope="row">Način prikaza</th><td><select name="' . esc_attr( $o::EDITABLE_CART_MODE ) . '">';
		foreach ( [
			'summary_x' => 'X gumb u sažetku "Tvoja narudžba" (preporučeno - nema druge košarice ni sync problema)',
			'table'     => 'Zasebna tablica iznad checkouta (staro)',
		] as $val => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $mode, $val, false ), esc_html( $label ) );
		}
		echo '</select><p class="description">Cilj je dugoročno koristiti X u sažetku i maknuti zasebnu tablicu.</p></td></tr>';
		self::row_toggle( 'Buy Now gumb', $o::BUY_NOW_ENABLED, 'Dodaje "Idi na plaćanje" gumb na stranici proizvoda (simple products).' );
		self::row_text( 'Tekst Buy Now gumba', $o::BUY_NOW_TEXT );
	}

	private static function tab_kupnje(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle( 'Omogući jednokratnu kupnju', $o::SINGLE_PURCHASE_ENABLED, 'Odabrani proizvodi se mogu kupiti samo JEDNOM po kupcu - blokira se dodavanje u košaricu i checkout ako ih je kupac već platio (provjera po korisniku i emailu; neplaćene narudžbe ne blokiraju).' );
		self::row_product_select( 'Zaštićeni proizvodi', $o::SINGLE_PURCHASE_PRODUCTS, 'Samo proizvodi s ove liste se ograničavaju. Pretplate (biz ARENA) i proizvode koji se smiju kupovati više puta NE stavljati na listu.' );
		self::row_textarea( 'Poruka kupcu', $o::SINGLE_PURCHASE_MESSAGE, 'Prikazuje se kao poruka na proizvodu/checkoutu. {proizvod} se zamjenjuje nazivom proizvoda.' );
		self::row_text( 'Tekst linka na Moj račun', $o::SINGLE_PURCHASE_LINK_TEXT, 'Dodaje se iza poruke kao link na Moj račun. Prazno = bez linka.' );
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

		echo '<tr><td colspan="2"><h3>Besplatne narudžbe (0 €)</h3></td></tr>';
		self::row_toggle( 'Redirect za besplatne narudžbe', $o::THANKYOU_FREE_ENABLED, 'Narudžbe bez načina plaćanja (total 0 €) također se preusmjeravaju; na GTM timeout redirect ide automatski.' );
		self::row_text( 'Naslov (besplatne)', $o::THANKYOU_FREE_TITLE, 'Naslov za besplatne narudžbe (nema "plaćanje je uspješno").' );
	}

	private static function tab_prijevodi(): void {
		$o    = RPSM_Checkout_Options::class;
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

	private static function tab_atribucija(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle(
			'Omogući atribuciju',
			$o::ATTR_ENABLED,
			'Čita rpsm_attr kolačić (piše ga rpsm-web na www) i sprema izvor/kampanju/CTA na narudžbu kroz WC sesiju + WCS obnove/switcheve. Bez privole na www nema kolačića - narudžba ostaje "bez privole", nikad se ne trpa u "direct".'
		);
		self::row_text(
			'Retencija sesije (dana)',
			$o::ATTR_RETENTION_DAYS,
			'Ako je atribucija u WC sesiji starija od ovoliko dana (dugotrajna sesija ulogiranog korisnika), pri kreiranju narudžbe se ignorira - sprječava lijepljenje stare atribucije na sasvim novu narudžbu.'
		);
		self::row_toggle(
			'Hvataj izvor i NA portalu',
			$o::ATTR_CAPTURE_ENABLED,
			'Oglasi i mailovi koji vode DIREKTNO na stranicu proizvoda (bez prolaska kroz www) dobiju kolačić ovdje. Ista pravila kao na www: piše se tek po privoli, prvi izvor se nikad ne prepisuje, www→portal prijelaz se ne računa kao novi izvor.'
		);
		self::row_select(
			'Consent kategorija (portal)',
			$o::ATTR_CONSENT_CAT,
			[ 'functional' => 'Funkcionalni', 'marketing' => 'Marketing', 'preferences' => 'Preference', 'statistics' => 'Statistika' ],
			'Complianz kategorija portalovog bannera koja mora biti prihvaćena prije pisanja kolačića.'
		);

		echo '</table>';

		echo '<h3>Zadnjih 50 zapisa (debug log, filtrirano na Atribuciju)</h3>';
		echo '<p class="description">Uključi Debug mod na Debug tabu za detaljno logiranje svakog upisa/kopiranja atribucije.</p>';
		echo '<pre style="background:#1d2327;color:#c3c4c7;padding:16px;max-height:400px;overflow:auto;font-size:12px;border-radius:4px;">';
		$log = self::attribution_log_tail( 50 );
		echo $log ? esc_html( $log ) : '<em>Nema zapisa.</em>';
		echo '</pre>';

		echo '<table class="form-table">';
	}

	/**
	 * Debug log je zajednički za cijeli plugin - filtriramo na retke koje je
	 * upisao modul Atribucije (izvorišna klasa se pojavljuje u svakoj liniji
	 * kroz RPSM_Checkout_Debug::get_caller_info()).
	 */
	private static function attribution_log_tail( int $limit ): string {
		$all = RPSM_Checkout_Debug::read_log( 500 );
		if ( '' === $all ) {
			return '';
		}
		$lines   = explode( "\n", $all );
		$matched = array_values(
			array_filter(
				$lines,
				static function ( $line ) {
					return false !== strpos( $line, 'Attribution' );
				}
			)
		);
		return implode( "\n", array_slice( $matched, 0, $limit ) );
	}

	private static function tab_express(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle(
			'Omogući Express stranice',
			$o::EXPRESS_ENABLED,
			'Stranice sa shortcodeom [rpsm_express product_id=X] postaju checkout: proizvod se auto-doda u košaricu, svi checkout moduli rade, canonical pokazuje na proizvod. Bez shortcodea na stranicama modul ne radi ništa.'
		);
		self::row_toggle(
			'Isprazni košaricu pri ulasku (clobber)',
			$o::EXPRESS_CLOBBER,
			'Express = "kupi ovo sada": postojeći sadržaj košarice se zamijeni express proizvodom. Isključeno = proizvod se dodaje uz postojeće stavke.'
		);
		self::row_text(
			'Gateway prvi na expressu',
			$o::EXPRESS_FIRST_GATEWAY,
			'Gateway ID koji ide na vrh liste plaćanja SAMO na express stranicama (npr. eh_stripe_checkout). Prazno = redoslijed se ne dira. Globalni checkout nikad nije pogođen.'
		);
		self::row_toggle(
			'Sticky mobilna traka',
			$o::EXPRESS_STICKY_CTA,
			'Na mobitelu prikazuje fiksnu donju traku s ukupnim iznosom i gumbom koji skrola na formu. Sakriva se dok je forma u viewportu.'
		);
		self::row_text( 'Tekst gumba u traci', $o::EXPRESS_STICKY_CTA_TEXT );
		self::row_textarea(
			'Poruka vlasniku proizvoda',
			$o::EXPRESS_OWNED_MESSAGE,
			'Prikazuje se umjesto forme kad je kupac proizvod već kupio (Jednokratna kupnja). {proizvod} = naziv proizvoda.'
		);
		self::row_text( 'Tekst linka na Moj račun', $o::EXPRESS_OWNED_LINK_TEXT, 'Gumb ispod poruke vlasniku. Prazno = bez gumba.' );

		echo '</table>';
		echo '<h3>Pronađene express stranice</h3>';
		$pages = self::find_express_pages();
		if ( empty( $pages ) ) {
			echo '<p class="description">Nijedna stranica ne sadrži [rpsm_express] shortcode. Napravi Elementor stranicu i u Shortcode widget stavi npr. [rpsm_express product_id=123].</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:760px"><thead><tr><th>Stranica</th><th>Status</th><th></th></tr></thead><tbody>';
			foreach ( $pages as $page ) {
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td><a href="%s" target="_blank" rel="noopener">Otvori</a></td></tr>',
					esc_url( get_edit_post_link( $page->ID ) ),
					esc_html( get_the_title( $page ) ),
					esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ),
					esc_url( get_permalink( $page ) )
				);
			}
			echo '</tbody></table>';
		}
		echo '<p class="description" style="margin-top:8px">Podsjetnik za slaganje: express URL-ove dodati u cache exclusion; slike optimizirane (hero bez lazy-loada).</p>';
		echo '<table class="form-table">';
	}

	/**
	 * Stranice koje sadrze [rpsm_express - u post_contentu ILI u Elementor
	 * JSON-u (_elementor_data), jer Shortcode widget ne zavrsava nuzno u
	 * post_contentu. Admin-only upit, bez keširanja.
	 */
	private static function find_express_pages(): array {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '[rpsm_express' ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			 WHERE p.post_type = 'page' AND p.post_status IN ('publish','draft','private')
			   AND (p.post_content LIKE %s OR m.meta_value LIKE %s)
			 LIMIT 50",
			$like,
			$like
		) );
		return empty( $ids ) ? [] : array_filter( array_map( 'get_post', array_map( 'intval', $ids ) ) );
	}

	private static function tab_sadrzaj(): void {
		$o = RPSM_Checkout_Options::class;
		self::row_toggle(
			'Omogući Sadržaj proizvoda',
			$o::CONTENT_ENABLED,
			'Meta box "Prodajna stranica" na proizvodu + shortcodovi za prodajne blokove. Prazne sekcije se na stranicama ne prikazuju, pa je modul bezopasan dok proizvodi nemaju podatke.'
		);

		echo '<tr><td colspan="2"><h3>Shortcodovi</h3><p class="description">'
			. esc_html( 'Na stranici proizvoda i express stranici rade bez atributa; bilo gdje drugdje uz product_id="123". Blokovi: [rpsm_product_stats] chipovi, [rpsm_product_za_koga] za/nije liste, [rpsm_product_moduli] accordion s trajanjima, [rpsm_product_faq] pitanja (+ FAQPage schema na proizvodu), [rpsm_product_recenzije] citati, [rpsm_product_video] uvodni video.' )
			. '</p></td></tr>';

		$pairs = json_decode( (string) RPSM_Checkout_Options::get( $o::CONTENT_GLOBAL_FAQ ), true ) ?: [];

		echo '</table>';
		echo '<h3>Globalna FAQ pitanja</h3>';
		echo '<p class="description">Vrijede za SVE proizvode (plaćanje, R1, pristup...). Prikazuju se IZA specifičnih pitanja proizvoda; proizvod ih može sakriti checkboxom u svom meta boxu.</p>';
		echo '<table class="widefat rpsm-gfaq-table" id="rpsm-gfaq" style="max-width:900px">';
		echo '<thead><tr><th style="width:35%">Pitanje</th><th>Odgovor</th><th style="width:40px"></th></tr></thead><tbody>';
		foreach ( $pairs as $i => $pair ) {
			$q = esc_attr( $pair['q'] ?? '' );
			$a = esc_textarea( $pair['a'] ?? '' );
			echo '<tr>';
			echo "<td><input type='text' name='rpsm_gfaq[{$i}][q]' value='{$q}' class='large-text'></td>";
			echo "<td><textarea name='rpsm_gfaq[{$i}][a]' rows='2' class='large-text'>{$a}</textarea></td>";
			echo "<td><button type='button' class='button' onclick='this.closest(\"tr\").remove()'>&times;</button></td>";
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<button type="button" class="button" id="rpsm-add-gfaq" style="margin-top:8px;">+ Dodaj pitanje</button>';
		echo '<script>
			document.getElementById("rpsm-add-gfaq").addEventListener("click", function(){
				var tbody = document.querySelector("#rpsm-gfaq tbody");
				var i = tbody.children.length + "_" + Date.now();
				var tr = document.createElement("tr");
				tr.innerHTML = \'<td><input type="text" name="rpsm_gfaq[\'+i+\'][q]" class="large-text"></td>\'+
					\'<td><textarea name="rpsm_gfaq[\'+i+\'][a]" rows="2" class="large-text"></textarea></td>\'+
					\'<td><button type="button" class="button" onclick="this.closest(\\\'tr\\\').remove()">&times;</button></td>\';
				tbody.appendChild(tr);
			});
		</script>';
		echo '<table class="form-table">';
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

		/* Special: globalna FAQ pitanja (Sadrzaj tab) */
		if ( 'sadrzaj' === $tab ) {
			$pairs = [];
			if ( isset( $_POST['rpsm_gfaq'] ) && is_array( $_POST['rpsm_gfaq'] ) ) {
				foreach ( wp_unslash( $_POST['rpsm_gfaq'] ) as $row ) { // phpcs:ignore
					$q = sanitize_text_field( $row['q'] ?? '' );
					$a = sanitize_textarea_field( $row['a'] ?? '' );
					if ( '' !== $q && '' !== $a ) {
						$pairs[] = [ 'q' => $q, 'a' => $a ];
					}
				}
			}
			RPSM_Checkout_Options::set( $o::CONTENT_GLOBAL_FAQ, wp_json_encode( $pairs, JSON_UNESCAPED_UNICODE ) );
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
			$o::COUPON_MULTI_ENABLED  => 'toggle',
				$o::COUPON_SWITCH_ENABLED    => 'toggle',
				$o::COUPON_SWITCH_PRODUCTS   => 'product_ids',
				$o::COUPON_SWITCH_CODE_ONCE  => 'text',
				$o::COUPON_SWITCH_CODE_RECUR => 'text',
				$o::COUPON_SWITCH_SKIP_ON_SALE => 'toggle',
				$o::COUPON_SWITCH_SHOW_FIELD => 'toggle',
				$o::EDITABLE_CART_ENABLED => 'toggle',
				$o::EDITABLE_CART_MODE    => 'text',
				$o::BUY_NOW_ENABLED       => 'toggle',
				$o::BUY_NOW_TEXT          => 'text',
			],
			'kupnje' => [
				$o::SINGLE_PURCHASE_ENABLED   => 'toggle',
				$o::SINGLE_PURCHASE_PRODUCTS  => 'product_ids',
				$o::SINGLE_PURCHASE_MESSAGE   => 'textarea',
				$o::SINGLE_PURCHASE_LINK_TEXT => 'text',
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
				$o::THANKYOU_FREE_ENABLED => 'toggle',
				$o::THANKYOU_FREE_TITLE   => 'text',
			],
			'prijevodi' => [
				$o::TRANSLATIONS_ENABLED => 'toggle',
			],
			'atribucija' => [
				$o::ATTR_ENABLED         => 'toggle',
				$o::ATTR_RETENTION_DAYS  => 'text',
				$o::ATTR_CAPTURE_ENABLED => 'toggle',
				$o::ATTR_CONSENT_CAT     => 'text',
			],
			'express' => [
				$o::EXPRESS_ENABLED         => 'toggle',
				$o::EXPRESS_CLOBBER         => 'toggle',
				$o::EXPRESS_FIRST_GATEWAY   => 'text',
				$o::EXPRESS_STICKY_CTA      => 'toggle',
				$o::EXPRESS_STICKY_CTA_TEXT => 'text',
				$o::EXPRESS_OWNED_MESSAGE   => 'textarea',
				$o::EXPRESS_OWNED_LINK_TEXT => 'text',
			],
			'sadrzaj' => [
				$o::CONTENT_ENABLED => 'toggle',
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

	/** Select red - opcije label => prikaz, abecedno slozene od pozivatelja. */
	private static function row_select( string $label, string $key, array $options, string $hint = '' ): void {
		$val = (string) RPSM_Checkout_Options::get( $key );
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td><select name="' . esc_attr( $key ) . '">';
		foreach ( $options as $opt_val => $opt_label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $opt_val ),
				selected( $val, (string) $opt_val, false ),
				esc_html( (string) $opt_label )
			);
		}
		echo '</select>';
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
