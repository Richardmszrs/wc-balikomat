<?php
/**
 * Plugin Name: Balikomat Shipping
 * Description: Balikomat Shipping Method for WooCommerce plugin is forked from České služby pro WordPress by Pavel Hejn
 * Version: 0.1
 * Author: Richard Meszaros
 * Author URI: http://www.upweb.sk
 * Text Domain: upweb
 * License: GPL
 */


if ( ! defined( 'WPINC' ) ) {

	die;

}

/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function tutsplus_shipping_method() {
		if ( ! class_exists( 'Balikomat_Shipping_Method' ) ) {
			class Balikomat_Shipping_Method extends WC_Shipping_Method {
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {

					$this->id = 'balikomat';
					$this->method_title = 'Balikomat';
					$this->title = $this->get_option( 'balikomat_nazov' );
					$this->enabled = $this->get_option( 'enabled' );
					$this->init();

					// Availability & Countries
					$this->availability = 'including';
					$this->countries = array(
						'SK', // Slovakia
						'CZ',
					);

					$this->init();

					/*$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
					$this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'TutsPlus Shipping', 'tutsplus' );
			*/	}

				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					// Load the settings API
					$this->init_form_fields();
					$this->init_settings();

					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}

				/**
				 * Define settings field for this shipping
				 * @return void
				 */
				public function init_form_fields() {
					$zakladni = array(
						'enabled' => array(
							'title'   => 'Povolit',
							'type'    => 'checkbox',
							'label'   => 'Aktivovat a zobrazit v nabídce dostupných možností dopravy.',
							'default' => 'no'
						),
						'balikomat_nazov' => array(
							'title'       => 'Nazov',
							'type'        => 'text',
							'description' => 'Názov pre zobrazenie v eshopu.',
							'default'     => 'Balikomat',
							'css'         => 'width: 300px;'
						),
					);

					$slovensko = array(
						'zakladna_sadzba' => array(
							'title'       => 'Základna cena',
							'type'        => 'price',
							'description' => 'Pokud nebude cena vyplněna, tak bude nulová.',
							'default'     => '',
							'css'         => 'width: 100px;',
							'placeholder' => wc_format_localized_price( 0 )
						),
					);

					$zvolene_zeme = WC()->countries->get_shipping_countries();
					if ( array_key_exists( 'SK', $zvolene_zeme ) ) {
						$this->form_fields = array_merge($zakladni, $slovensko) ;
					} /*else {
			$this->form_fields = $zakladni;
		}*/
				}

				/**
				 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = array() ) {
					$zeme = WC()->customer->get_shipping_country();
					if ( $zeme == "SK" ) { $cena = $this->get_option( 'zakladna_sadzba' ); }
					$rate = array(
						'id' => $this->id,
						'label' => $this->title,
						'cost' => $cena
					);
					$this->add_rate( $rate );
				}
			}
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-balikomat-json-loader.php';
		}
	}

	add_action( 'woocommerce_shipping_init', 'tutsplus_shipping_method' );
	add_filter( 'woocommerce_shipping_methods', 'add_tutsplus_shipping_method' );

	add_action( 'woocommerce_review_order_after_shipping', 'balikomat_zobrazenie_polohy' );
	add_action( 'woocommerce_add_shipping_order_item', 'balikomat_ulozeni_pobocky', 10, 2 );
	add_action( 'woocommerce_checkout_process', 'balikomat_overit_pobocku' );
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'balikomat_objednavka_zobrazit_pobocku' );
	add_action( 'woocommerce_email_after_order_table', 'balikomat_pobocka_email' );
	add_action( 'woocommerce_order_details_after_order_table', 'balikomat_pobocka_email' );


	function add_tutsplus_shipping_method( $methods ) {
		$methods[] = 'Balikomat_Shipping_Method';
		return $methods;
	}

	/*_____________________________________________ZOBRAZENIE PObOCIEK_____________________________*/



	function balikomat_zobrazenie_polohy() {
		if ( is_ajax() ) {
			parse_str( $_POST['post_data'] );
			$available_shipping = WC()->shipping->load_shipping_methods();
			$chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
			$settings = array();

			if ( $chosen_shipping_method[0] == "balikomat" ) {
				$settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;

				if ( $settings['enabled'] == "yes" ) {?>
					<?php

					$pobocky = new Balikomat_Json_Loader();


					$zeme = WC()->customer->get_shipping_country();
					if ( $zeme == "SK" ) { $zeme_code = "SK"; }

					$parametry = array( 'country' => $zeme_code );

					?>
					<tr class="balikomat">
						<td>
							Balíkomat
						</td>
						<td>
								<font size="2">Balíkomat - výber pobočky:</font><br>
							<div id="balikomat-branch-select-options">
								<select name="balikomat_branches">
									<option>Vyberte pobočku</option>

									<?php
									foreach ( $pobocky->load( $parametry )->intime->machines_SK as $pobocka ) {
										if ( ! empty ( $balikomat_branches ) && $balikomat_branches == $pobocka->location_description ) {
											$selected = ' selected="selected"';
										} else {
											$selected = '';
										}
										echo '<option value="' . $pobocka->location_description . '"' . $selected . '>' . $pobocka->location_description . '</option>';
									} ?>

							</div>
						</td>
					</tr>

				<?php }
			}
		}
	}

/*_____________________________________________ULOZENIE POBOCIEK_____________________________*/
	function balikomat_ulozeni_pobocky( $order_id, $item_id ) {

		if ( isset( $_POST["balikomat_branches"] ) ) {
			if ( $_POST["balikomat_branches"] && $_POST["shipping_method"][0] == "balikomat" ) {
				wc_add_order_item_meta( $item_id, 'balikomat_pobocka_nazov', esc_attr($_POST["balikomat_branches"]), true );
			}
		}
	}

	function balikomat_overit_pobocku() {
		if ( isset( $_POST["balikomat_branches"] ) ) {
			if ( $_POST["balikomat_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "balikomat" ) {
				wc_add_notice( 'Ak chcete doručit objednávku prostrdníctvom Balíkomatu, zvolte prosím pobočku.', 'error' );
			}
		}
	}

	function balikomat_objednavka_zobrazit_pobocku( $order ) {
		if ( $order->has_shipping_method( 'balikomat' ) ) {
			foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
				echo "<p id='balikomat_pobocky'><strong>Balikomat: </strong> " . $order->get_item_meta( $shipping_item_id, 'balikomat_pobocka_nazov', true ) . "</p>";
			}
		}
	}

	function balikomat_pobocka_email( $order ) {
		if ( $order->has_shipping_method( 'balikomat' ) ) {
			foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
				echo "<p><strong>Balikomat: </strong> " . $order->get_item_meta( $shipping_item_id, 'balikomat_pobocka_nazov', true ) . "</p>";
			}
		}
	}


}
