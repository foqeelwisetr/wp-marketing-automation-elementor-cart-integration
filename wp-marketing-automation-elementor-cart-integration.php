<?php
/**
 * Plugin Name: FunnelKit Automations - Elementor Form Cart Integration
 * Plugin URI: https://funnelkit.com/
 * Description: Captures emails from Elementor forms and links them to abandoned carts for FunnelKit Automations.
 * Version: 1.0.0
 * Author: FunnelKit
 * Author URI: https://funnelkit.com/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-marketing-automations-pro
 *
 * Requires at least: 5.0
 * Tested up to: 6.0
 * WC requires at least: 5.0
 * WC tested up to: 10.3.6
 */

if ( ! class_exists( 'BWFAN_Elementor_Cart' ) ) {

	final class BWFAN_Elementor_Cart {

		private static $_instance = null;

		private function __construct() {
			$this->define_plugin_properties();
			add_action( 'bwfan_loaded', [ $this, 'init' ] );
			add_action( 'before_woocommerce_init', [ $this, 'declare_wc_compatibility' ], 10 );
			add_action( 'init', [ $this, 'load_textdomain' ] );
		}

		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function init() {
			$this->core();
		}

		public function define_plugin_properties() {
			define( 'BWFAN_ELEMENTOR_CART_VERSION', '1.0.0' );
			define( 'BWFAN_ELEMENTOR_CART_FULL_NAME', 'FunnelKit Automations - Elementor Form Cart Integration' );
			define( 'BWFAN_ELEMENTOR_CART_PLUGIN_FILE', __FILE__ );
			define( 'BWFAN_ELEMENTOR_CART_PLUGIN_DIR', __DIR__ );
			define( 'BWFAN_ELEMENTOR_CART_PLUGIN_URL', untrailingslashit( plugin_dir_url( BWFAN_ELEMENTOR_CART_PLUGIN_FILE ) ) );
			define( 'BWFAN_ELEMENTOR_CART_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			define( 'BWFAN_ELEMENTOR_CART_IS_DEV', true );
			define( 'BWFAN_ELEMENTOR_CART_DB_VERSION', '1.0' );
			define( 'BWFAN_ELEMENTOR_CART_ENCODE', sha1( BWFAN_ELEMENTOR_CART_PLUGIN_BASENAME ) );
		}

		private function core() {
			// Load helper functions first
			require BWFAN_ELEMENTOR_CART_PLUGIN_DIR . '/includes/bwfan-elementor-cart-functions.php';

			// Check dependencies before loading
			if ( ! $this->check_dependencies() ) {
				return;
			}

			require BWFAN_ELEMENTOR_CART_PLUGIN_DIR . '/includes/class-bwfan-elementor-cart-common.php';
			BWFAN_Elementor_Cart_Common::init();
		}

		/**
		 * Check if all required plugins are active
		 *
		 * @return bool
		 */
		private function check_dependencies() {
			if ( ! function_exists( 'bwfan_is_woocommerce_active' ) ) {
				require BWFAN_ELEMENTOR_CART_PLUGIN_DIR . '/includes/bwfan-elementor-cart-functions.php';
			}

			return bwfan_is_woocommerce_active() && bwfan_is_automations_active() && bwfan_is_automations_pro_active();
		}

		/**
		 * Declare WooCommerce compatibility (HPOS, etc.)
		 */
		public function declare_wc_compatibility() {
			if ( ! defined( 'BWFAN_ELEMENTOR_CART_PLUGIN_FILE' ) ) {
				$this->define_plugin_properties();
			}

			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BWFAN_ELEMENTOR_CART_PLUGIN_FILE, true );
			}
		}

		/**
		 * Load plugin text domain
		 */
		public function load_textdomain() {
			load_plugin_textdomain(
				'wp-marketing-automations-pro',
				false,
				dirname( BWFAN_ELEMENTOR_CART_PLUGIN_BASENAME ) . '/languages'
			);
		}

		/**
		 * to avoid unserialize of the current class
		 */
		public function __wakeup() {
			// Use simple string to avoid translation loading before init
			throw new ErrorException( 'BWFAN_Elementor_Cart cannot be unserialized' );
		}

		/**
		 * to avoid serialize of the current class
		 */
		public function __sleep() {
			// Use simple string to avoid translation loading before init
			throw new ErrorException( 'BWFAN_Elementor_Cart cannot be serialized' );
		}

		/**
		 * To avoid cloning of current class
		 */
		protected function __clone() {
		}
	}

	BWFAN_Elementor_Cart::get_instance();
}

