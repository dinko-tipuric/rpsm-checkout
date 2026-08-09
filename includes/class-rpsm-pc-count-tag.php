<?php
/**
 * Elementor Dynamic Tag: "RPSM: Broj stavki sadržaja".
 *
 * Vraća broj stavki odabranog polja Sadržaja proizvoda (moduli, FAQ,
 * za koga, stats, recenzije, video) za proizvod iz konteksta (express
 * stranica / product stranica) ili eksplicitni product_id. Prazno polje
 * daje prazan output, pa Elementor Pro Display Conditions ("Dynamic tag
 * is empty / is not empty") mogu sakriti naslov ili cijelu sekciju.
 *
 * File se učitava lazily iz Product_Content::register_dynamic_tag() tek
 * kad Elementor parent klasa sigurno postoji.
 *
 * NAPOMENA (mv-portal-core lekcija, memorija elementor-tag-ctor): tag NEMA
 * vlastiti konstruktor - Elementor manager interno zove `new $class($data)`
 * na cached settings arrayima (CSS render pipeline), pa svaki custom ctor
 * potencijalno ruši render. Sav pristup podacima ide kroz render().
 *
 * @package RpsmCheckout
 * @since   1.10.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'RPSM_PC_Count_Dynamic_Tag' ) ) {
	return;
}

if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
	return;
}

/**
 * Class RPSM_PC_Count_Dynamic_Tag
 */
class RPSM_PC_Count_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name(): string {
		return 'rpsm-pc-broj-stavki';
	}

	public function get_title(): string {
		return 'RPSM: Broj stavki sadržaja';
	}

	/**
	 * Ugrađena grupa "Post" - NE custom grupa: mv-portal-core pattern koji
	 * je dokazano vidljiv u pickerima; custom grupe znaju ispasti iz
	 * Display Conditions dropdowna (Elementor #25418 truncation).
	 */
	public function get_group(): string {
		return 'post';
	}

	/**
	 * TEXT + NUMBER: dostupan u tekstualnim poljima i u Display Conditions.
	 */
	public function get_categories(): array {
		return [
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
		];
	}

	/**
	 * Display Conditions lista SAMO tagove koji u editor configu imaju
	 * 'display_conditions' unose (Pro 4.2.1: provider preskace sve ostale) -
	 * po jedan unos po polju, s unaprijed zapecenim settings (postavke taga
	 * se u conditions UI-ju NE mogu otvarati). 'group' MORA biti jedna od
	 * hardkodiranih grupa providera (archive/featured_image/author) - inace
	 * unos ispada iz liste; 'archive' odabran da budu pri vrhu.
	 */
	public function get_editor_config() {
		$config = parent::get_editor_config();

		$polja = [
			'moduli'    => 'Moduli',
			'faq'       => 'FAQ',
			'za_koga'   => 'Za koga je / nije',
			'stats'     => 'Stats chipovi',
			'recenzije' => 'Recenzije',
			'video'     => 'Video',
		];
		$conditions = [];
		foreach ( $polja as $key => $label ) {
			$conditions[ 'rpsm_pc_' . $key ] = [
				'label'    => 'RPSM Sadržaj: ' . $label,
				'settings' => [ 'polje' => $key ],
				'group'    => 'archive',
			];
		}
		$config['display_conditions'] = $conditions;

		return $config;
	}

	protected function register_controls(): void {
		$this->add_control(
			'polje',
			[
				'label'   => 'Polje sadržaja',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'moduli',
				'options' => [
					'moduli'    => 'Moduli',
					'faq'       => 'FAQ',
					'za_koga'   => 'Za koga je / nije',
					'stats'     => 'Stats chipovi',
					'recenzije' => 'Recenzije',
					'video'     => 'Video',
				],
			]
		);
		$this->add_control(
			'product_id',
			[
				'label'       => 'Product ID (0 = auto iz konteksta)',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'description' => 'Na express/product stranici ostavi 0 - proizvod se čita iz konteksta.',
			]
		);
	}

	/**
	 * Output: broj stavki, ili prazno kad ih nema (okida "is empty").
	 */
	public function render(): void {
		if ( ! class_exists( 'RPSM_Checkout_Module_Product_Content' ) ) {
			return;
		}
		$polje = (string) $this->get_settings( 'polje' );
		$pid   = RPSM_Checkout_Module_Product_Content::resolve_for_display( (int) $this->get_settings( 'product_id' ) );
		$n     = RPSM_Checkout_Module_Product_Content::count_items( $pid, $polje );
		if ( $n > 0 ) {
			echo (int) $n;
		}
	}
}
