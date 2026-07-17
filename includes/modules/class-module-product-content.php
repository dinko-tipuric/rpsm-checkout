<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sadrzaj proizvoda: strukturirani prodajni blokovi koji se ureduju NA
 * PROIZVODU i renderiraju shortcodovima - na express stranici, redizajniranoj
 * product stranici ili bilo gdje drugdje (SPEC-express-checkout.md).
 *
 * Blokovi i shortcodovi:
 *   [rpsm_product_stats]      - chipovi (trajanje, moduli, workbooki...)
 *   [rpsm_product_za_koga]    - "Za tebe je ako" / "Nije za tebe ako"
 *   [rpsm_product_moduli]     - accordion modula s trajanjima
 *   [rpsm_product_faq]        - FAQ: specificna pitanja + globalna iz postavki
 *   [rpsm_product_recenzije]  - citati (tekst / ime i prezime / titula)
 *   [rpsm_product_video]      - uvodni video (oEmbed ili mp4)
 *
 * Pravila:
 *  - shortcode bez product_id cita express kontekst pa queried proizvod;
 *    s product_id=X radi bilo gdje
 *  - prazna sekcija ne renderira NISTA (ni wrapper) - stranica bez rupa
 *  - FAQ na stranici proizvoda emitira FAQPage schema.org (na noindex
 *    express stranici NE)
 *  - podaci se drze u JEDNOJ post meti (_rpsm_product_content, JSON) -
 *    nikad (array) cast na get_meta ([[feedback_php_array_cast_get_meta]])
 */
final class RPSM_Checkout_Module_Product_Content {

	private const META_KEY     = '_rpsm_product_content';
	private const NONCE_ACTION = 'rpsm_pc_save';
	private const NONCE_FIELD  = 'rpsm_pc_nonce';

	/** Per-request memo podataka po proizvodu. */
	private static array $memo = [];

	public static function init(): void {
		add_shortcode( 'rpsm_product_stats', [ __CLASS__, 'sc_stats' ] );
		add_shortcode( 'rpsm_product_za_koga', [ __CLASS__, 'sc_za_koga' ] );
		add_shortcode( 'rpsm_product_moduli', [ __CLASS__, 'sc_moduli' ] );
		add_shortcode( 'rpsm_product_faq', [ __CLASS__, 'sc_faq' ] );
		add_shortcode( 'rpsm_product_recenzije', [ __CLASS__, 'sc_recenzije' ] );
		add_shortcode( 'rpsm_product_video', [ __CLASS__, 'sc_video' ] );

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_style' ] );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', [ __CLASS__, 'register_metabox' ] );
			add_action( 'save_post_product', [ __CLASS__, 'save_metabox' ], 10, 2 );
		}
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Podaci                                                        */
	/* ══════════════════════════════════════════════════════════════ */

	private static function empty_data(): array {
		return [
			'stats'           => '',   // comma-separated chipovi
			'za'              => '',   // natuknice, jedna po retku
			'nije'            => '',
			'moduli'          => [],   // [{naziv,trajanje,opis}]
			'faq'             => [],   // [{q,a}]
			'faq_hide_global' => '0',
			'recenzije'       => [],   // [{tekst,ime,titula}]
			'video'           => '',
		];
	}

	public static function get_data( int $product_id ): array {
		if ( isset( self::$memo[ $product_id ] ) ) {
			return self::$memo[ $product_id ];
		}
		$raw  = get_post_meta( $product_id, self::META_KEY, true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		$data = is_array( $data ) ? array_merge( self::empty_data(), $data ) : self::empty_data();

		self::$memo[ $product_id ] = $data;
		return $data;
	}

	/**
	 * Proizvod za shortcode: atribut > express kontekst > queried proizvod.
	 */
	private static function resolve_product_id( array $atts ): int {
		$pid = (int) ( $atts['product_id'] ?? 0 );
		if ( $pid > 0 ) {
			return $pid;
		}
		if ( function_exists( 'rpsm_checkout_is_express' ) && rpsm_checkout_is_express() ) {
			$express = RPSM_Checkout_Module_Express::product_id();
			if ( null !== $express ) {
				return $express;
			}
		}
		global $product;
		if ( $product instanceof WC_Product ) {
			return (int) $product->get_id();
		}
		$qid = get_queried_object_id();
		if ( $qid > 0 && 'product' === get_post_type( $qid ) ) {
			return $qid;
		}
		return 0;
	}

	/** Globalna FAQ pitanja iz postavki modula. */
	private static function global_faq(): array {
		$raw   = (string) RPSM_Checkout_Options::get( RPSM_Checkout_Options::CONTENT_GLOBAL_FAQ );
		$pairs = json_decode( $raw, true );
		return is_array( $pairs ) ? $pairs : [];
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Frontend style                                                */
	/* ══════════════════════════════════════════════════════════════ */

	public static function register_style(): void {
		wp_register_style(
			'rpsm-product-content',
			RPSM_CHECKOUT_PLUGIN_URL . 'public/css/rpsm-product-content.css',
			[],
			RPSM_CHECKOUT_VERSION
		);
	}

	private static function style(): void {
		wp_enqueue_style( 'rpsm-product-content' );
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Shortcodovi                                                   */
	/* ══════════════════════════════════════════════════════════════ */

	public static function sc_stats( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
		$pid  = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$chips = array_filter( array_map( 'trim', explode( ',', self::get_data( $pid )['stats'] ) ) );
		if ( empty( $chips ) ) {
			return '';
		}
		self::style();

		$out = '<div class="rpsm-pc-stats">';
		foreach ( $chips as $chip ) {
			$out .= '<span class="rpsm-pc-chip">' . esc_html( $chip ) . '</span>';
		}
		return $out . '</div>';
	}

	public static function sc_za_koga( $atts ): string {
		$atts = shortcode_atts(
			[
				'product_id' => 0,
				'naslov_da'  => 'Za tebe je ako...',
				'naslov_ne'  => 'Nije za tebe ako...',
			],
			$atts
		);
		$pid = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$data = self::get_data( $pid );
		$za   = self::lines( $data['za'] );
		$nije = self::lines( $data['nije'] );
		if ( empty( $za ) && empty( $nije ) ) {
			return '';
		}
		self::style();

		$out = '<div class="rpsm-pc-zakoga">';
		if ( ! empty( $za ) ) {
			$out .= self::zakoga_card( $atts['naslov_da'], $za, 'yes' );
		}
		if ( ! empty( $nije ) ) {
			$out .= self::zakoga_card( $atts['naslov_ne'], $nije, 'no' );
		}
		return $out . '</div>';
	}

	private static function lines( string $raw ): array {
		return array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
	}

	private static function zakoga_card( string $title, array $items, string $kind ): string {
		$mark = 'yes' === $kind ? '&#10003;' : '&ndash;';
		$out  = '<div class="rpsm-pc-card rpsm-pc-zakoga-' . esc_attr( $kind ) . '">';
		$out .= '<h3>' . esc_html( $title ) . '</h3><ul>';
		foreach ( $items as $item ) {
			$out .= '<li><span class="rpsm-pc-mark">' . $mark . '</span><span>' . esc_html( $item ) . '</span></li>';
		}
		return $out . '</ul></div>';
	}

	public static function sc_moduli( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
		$pid  = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$moduli = self::get_data( $pid )['moduli'];
		if ( empty( $moduli ) ) {
			return '';
		}
		self::style();

		$out = '<div class="rpsm-pc-moduli">';
		$i   = 0;
		foreach ( $moduli as $mod ) {
			$naziv = trim( (string) ( $mod['naziv'] ?? '' ) );
			if ( '' === $naziv ) {
				continue;
			}
			$i++;
			$trajanje = trim( (string) ( $mod['trajanje'] ?? '' ) );
			$opis     = trim( (string) ( $mod['opis'] ?? '' ) );

			$out .= '<details class="rpsm-pc-acc"' . ( 1 === $i ? ' open' : '' ) . '>';
			$out .= '<summary><span class="rpsm-pc-num">' . $i . '</span>'
				. '<span class="rpsm-pc-acc-title">' . esc_html( $naziv ) . '</span>';
			if ( '' !== $trajanje ) {
				$out .= '<span class="rpsm-pc-mins">' . esc_html( $trajanje ) . '</span>';
			}
			$out .= '</summary>';
			if ( '' !== $opis ) {
				$out .= '<div class="rpsm-pc-acc-body">' . esc_html( $opis ) . '</div>';
			}
			$out .= '</details>';
		}
		return $out . '</div>';
	}

	public static function sc_faq( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0, 'schema' => '1' ], $atts );
		$pid  = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$data  = self::get_data( $pid );
		$items = [];

		foreach ( $data['faq'] as $row ) {
			$q = trim( (string) ( $row['q'] ?? '' ) );
			$a = trim( (string) ( $row['a'] ?? '' ) );
			if ( '' !== $q && '' !== $a ) {
				$items[] = [ 'q' => $q, 'a' => $a ];
			}
		}

		/* Globalna pitanja iza specificnih (osim ako ih proizvod skriva). */
		if ( '1' !== $data['faq_hide_global'] ) {
			foreach ( self::global_faq() as $row ) {
				$q = trim( (string) ( $row['q'] ?? '' ) );
				$a = trim( (string) ( $row['a'] ?? '' ) );
				if ( '' !== $q && '' !== $a ) {
					$items[] = [ 'q' => $q, 'a' => $a ];
				}
			}
		}

		if ( empty( $items ) ) {
			return '';
		}
		self::style();

		$out = '<div class="rpsm-pc-faq">';
		foreach ( $items as $item ) {
			$out .= '<details class="rpsm-pc-acc">';
			$out .= '<summary><span class="rpsm-pc-acc-title">' . esc_html( $item['q'] ) . '</span></summary>';
			$out .= '<div class="rpsm-pc-acc-body">' . esc_html( $item['a'] ) . '</div>';
			$out .= '</details>';
		}
		$out .= '</div>';

		/* FAQPage schema samo na indeksabilnoj stranici proizvoda - express je
		   noindex pa mu schema ne treba (i Google je ionako ne bi koristio). */
		$is_express = function_exists( 'rpsm_checkout_is_express' ) && rpsm_checkout_is_express();
		if ( '1' === $atts['schema'] && ! $is_express && is_product() ) {
			$out .= self::faq_schema( $items );
		}

		return $out;
	}

	private static function faq_schema( array $items ): string {
		$entities = [];
		foreach ( $items as $item ) {
			$entities[] = [
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $item['a'],
				],
			];
		}
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];
		return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
	}

	public static function sc_recenzije( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
		$pid  = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$recenzije = self::get_data( $pid )['recenzije'];
		if ( empty( $recenzije ) ) {
			return '';
		}
		self::style();

		$out = '<div class="rpsm-pc-recenzije">';
		foreach ( $recenzije as $rec ) {
			$tekst = trim( (string) ( $rec['tekst'] ?? '' ) );
			if ( '' === $tekst ) {
				continue;
			}
			$ime    = trim( (string) ( $rec['ime'] ?? '' ) );
			$titula = trim( (string) ( $rec['titula'] ?? '' ) );

			$out .= '<div class="rpsm-pc-quote">';
			$out .= '<span class="rpsm-pc-quote-mark">&ldquo;</span>';
			$out .= '<p>' . esc_html( $tekst ) . '</p>';
			if ( '' !== $ime || '' !== $titula ) {
				$out .= '<div class="rpsm-pc-quote-who">' . esc_html( $ime );
				if ( '' !== $titula ) {
					$out .= '<small>' . esc_html( $titula ) . '</small>';
				}
				$out .= '</div>';
			}
			$out .= '</div>';
		}
		return $out . '</div>';
	}

	public static function sc_video( $atts ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], $atts );
		$pid  = self::resolve_product_id( $atts );
		if ( $pid <= 0 ) {
			return '';
		}
		$url = trim( self::get_data( $pid )['video'] );
		if ( '' === $url ) {
			return '';
		}
		self::style();

		$embed = wp_oembed_get( $url );
		if ( false === $embed || '' === $embed ) {
			/* Direktni mp4 (upload) - oEmbed ga ne zna */
			$embed = '<video controls preload="metadata" src="' . esc_url( $url ) . '"></video>';
		}
		return '<div class="rpsm-pc-video">' . $embed . '</div>';
	}

	/* ══════════════════════════════════════════════════════════════ */
	/*  Admin metabox                                                 */
	/* ══════════════════════════════════════════════════════════════ */

	public static function register_metabox(): void {
		add_meta_box(
			'rpsm-product-content',
			'Prodajna stranica (RPSM Checkout)',
			[ __CLASS__, 'render_metabox' ],
			'product',
			'normal',
			'default'
		);
	}

	public static function render_metabox( WP_Post $post ): void {
		$data = self::get_data( $post->ID );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<style>
			.rpsm-pc-box h4{margin:18px 0 6px;font-size:14px}
			.rpsm-pc-box .description{margin:2px 0 8px}
			.rpsm-pc-box table.rpsm-pc-rep{width:100%;border-collapse:collapse;margin-bottom:6px}
			.rpsm-pc-box table.rpsm-pc-rep td{padding:3px 4px 3px 0;vertical-align:top}
			.rpsm-pc-box table.rpsm-pc-rep input[type=text],.rpsm-pc-box table.rpsm-pc-rep textarea{width:100%}
			.rpsm-pc-box .rpsm-pc-rep-del{color:#b32d2e;cursor:pointer;background:none;border:none;font-size:16px;line-height:1}
		</style>';

		echo '<div class="rpsm-pc-box">';
		echo '<p class="description">Blokovi za express i prodajne stranice. Prazna sekcija se na stranici uopće ne prikazuje. Shortcodovi: [rpsm_product_stats], [rpsm_product_za_koga], [rpsm_product_moduli], [rpsm_product_faq], [rpsm_product_recenzije], [rpsm_product_video] - na stranici proizvoda i express stranici rade bez atributa, drugdje uz product_id="' . (int) $post->ID . '".</p>';

		/* Stats */
		echo '<h4>Brojke (chipovi)</h4>';
		echo '<p class="description">Odvojeno zarezom, npr: 2 sata, 4 video modula, 3 workbooka, Trajni pristup</p>';
		echo '<input type="text" class="large-text" name="rpsm_pc[stats]" value="' . esc_attr( $data['stats'] ) . '">';

		/* Za koga */
		echo '<h4>Za tebe je ako... (jedna natuknica po retku)</h4>';
		echo '<textarea name="rpsm_pc[za]" rows="3" class="large-text">' . esc_textarea( $data['za'] ) . '</textarea>';
		echo '<h4>Nije za tebe ako... (jedna natuknica po retku)</h4>';
		echo '<textarea name="rpsm_pc[nije]" rows="3" class="large-text">' . esc_textarea( $data['nije'] ) . '</textarea>';

		/* Moduli repeater */
		echo '<h4>Moduli / sadržaj programa</h4>';
		self::repeater(
			'moduli',
			[ 'naziv' => 'Naziv', 'trajanje' => 'Trajanje (npr. 24 min)', 'opis' => 'Kratki opis' ],
			$data['moduli']
		);

		/* FAQ repeater */
		echo '<h4>Česta pitanja (specifična za ovaj proizvod)</h4>';
		self::repeater(
			'faq',
			[ 'q' => 'Pitanje', 'a' => 'Odgovor' ],
			$data['faq'],
			[ 'a' => 'textarea' ]
		);
		echo '<label><input type="checkbox" name="rpsm_pc[faq_hide_global]" value="1"' . checked( '1', $data['faq_hide_global'], false ) . '> Sakrij globalna pitanja na ovom proizvodu (definirana u RPSM Checkout &gt; Sadržaj)</label>';

		/* Recenzije repeater */
		echo '<h4>Recenzije</h4>';
		self::repeater(
			'recenzije',
			[ 'tekst' => 'Tekst recenzije', 'ime' => 'Ime i prezime', 'titula' => 'Titula (npr. vlasnica servisa)' ],
			$data['recenzije'],
			[ 'tekst' => 'textarea' ]
		);

		/* Video */
		echo '<h4>Uvodni video (URL)</h4>';
		echo '<p class="description">YouTube/Vimeo link ili direktni .mp4 iz Medija. Prazno = bez videa.</p>';
		echo '<input type="text" class="large-text" name="rpsm_pc[video]" value="' . esc_attr( $data['video'] ) . '">';

		echo '</div>';
	}

	/**
	 * Generic repeater: tablica redova s poljima + "dodaj red" gumb.
	 * Redoslijed = redoslijed u tablici (novi red ide na dno).
	 */
	private static function repeater( string $key, array $fields, array $rows, array $types = [] ): void {
		$id = 'rpsm-pc-rep-' . $key;

		echo '<table class="rpsm-pc-rep" id="' . esc_attr( $id ) . '"><thead><tr>';
		foreach ( $fields as $label ) {
			echo '<th style="text-align:left;font-weight:500;padding:0 4px 2px 0">' . esc_html( $label ) . '</th>';
		}
		echo '<th></th></tr></thead><tbody>';

		foreach ( array_values( $rows ) as $i => $row ) {
			echo '<tr>';
			foreach ( $fields as $fkey => $label ) {
				$val  = (string) ( $row[ $fkey ] ?? '' );
				$name = "rpsm_pc[{$key}][{$i}][{$fkey}]";
				echo '<td>';
				if ( 'textarea' === ( $types[ $fkey ] ?? '' ) ) {
					echo '<textarea rows="2" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea>';
				} else {
					echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
				}
				echo '</td>';
			}
			echo '<td><button type="button" class="rpsm-pc-rep-del" onclick="this.closest(\'tr\').remove()">&times;</button></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<button type="button" class="button" data-rpsm-rep-add="' . esc_attr( $id ) . '">+ Dodaj red</button>';

		/* Template reda za JS (bez indeksa - ubacuje se pri kliku) */
		$tpl_cells = '';
		foreach ( $fields as $fkey => $label ) {
			if ( 'textarea' === ( $types[ $fkey ] ?? '' ) ) {
				$tpl_cells .= '<td><textarea rows="2" name="rpsm_pc[' . $key . '][__i__][' . $fkey . ']"></textarea></td>';
			} else {
				$tpl_cells .= '<td><input type="text" name="rpsm_pc[' . $key . '][__i__][' . $fkey . ']"></td>';
			}
		}
		$tpl_cells .= '<td><button type="button" class="rpsm-pc-rep-del" onclick="this.closest(\'tr\').remove()">&times;</button></td>';

		echo '<script type="text/template" id="' . esc_attr( $id ) . '-tpl">' . $tpl_cells . '</script>';
		echo '<script>
			(function(){
				var btn = document.querySelector(\'[data-rpsm-rep-add="' . esc_js( $id ) . '"]\');
				if (!btn || btn.dataset.bound) return;
				btn.dataset.bound = "1";
				btn.addEventListener("click", function(){
					var tbody = document.querySelector("#' . esc_js( $id ) . ' tbody");
					var tpl   = document.getElementById("' . esc_js( $id ) . '-tpl").textContent;
					var tr    = document.createElement("tr");
					tr.innerHTML = tpl.split("__i__").join(String(tbody.children.length) + "_" + Date.now());
					tbody.appendChild(tr);
				});
			})();
		</script>';
	}

	public static function save_metabox( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in = isset( $_POST['rpsm_pc'] ) && is_array( $_POST['rpsm_pc'] ) ? wp_unslash( $_POST['rpsm_pc'] ) : []; // phpcs:ignore

		$data = self::empty_data();

		$data['stats']           = sanitize_text_field( (string) ( $in['stats'] ?? '' ) );
		$data['za']              = sanitize_textarea_field( (string) ( $in['za'] ?? '' ) );
		$data['nije']            = sanitize_textarea_field( (string) ( $in['nije'] ?? '' ) );
		$data['faq_hide_global'] = isset( $in['faq_hide_global'] ) ? '1' : '0';
		$data['video']           = esc_url_raw( (string) ( $in['video'] ?? '' ) );

		$data['moduli']    = self::clean_rows( $in['moduli'] ?? [], [ 'naziv', 'trajanje', 'opis' ], 'naziv' );
		$data['faq']       = self::clean_rows( $in['faq'] ?? [], [ 'q', 'a' ], 'q' );
		$data['recenzije'] = self::clean_rows( $in['recenzije'] ?? [], [ 'tekst', 'ime', 'titula' ], 'tekst' );

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
		unset( self::$memo[ $post_id ] );
	}

	/** Repeater sanitizacija: samo poznata polja, redovi bez obaveznog polja se preskacu. */
	private static function clean_rows( $rows, array $fields, string $required ): array {
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$clean = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = [];
			foreach ( $fields as $fkey ) {
				$item[ $fkey ] = sanitize_textarea_field( (string) ( $row[ $fkey ] ?? '' ) );
			}
			if ( '' !== trim( $item[ $required ] ) ) {
				$clean[] = $item;
			}
		}
		return $clean;
	}
}
