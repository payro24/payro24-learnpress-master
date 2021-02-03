<?php
/**
 * Plugin load class.
 *
 * @author   payro24
 * @package  LearnPress/payro24/Classes
 * @version  1.0.1
 */

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'LP_Addon_payro24_Payment' ) ) {
	/**
	 * Class LP_Addon_payro24_Payment
	 */
	class LP_Addon_payro24_Payment extends LP_Addon {

		/**
		 * @var string
		 */
		public $version = LP_ADDON_payro24_PAYMENT_VER;

		/**
		 * @var string
		 */
		public $require_version = LP_ADDON_payro24_PAYMENT_REQUIRE_VER;

		/**
		 * LP_Addon_payro24_Payment constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Define Learnpress payro24 payment constants.
		 *
		 */
		protected function _define_constants() {
			define( 'LP_ADDON_payro24_PAYMENT_PATH', dirname( LP_ADDON_payro24_PAYMENT_FILE ) );
			define( 'LP_ADDON_payro24_PAYMENT_INC', LP_ADDON_payro24_PAYMENT_PATH . '/inc/' );
			define( 'LP_ADDON_payro24_PAYMENT_URL', plugin_dir_url( LP_ADDON_payro24_PAYMENT_FILE ) );
			define( 'LP_ADDON_payro24_PAYMENT_TEMPLATE', LP_ADDON_payro24_PAYMENT_PATH . '/templates/' );
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 */
		protected function _includes() {
			include_once LP_ADDON_payro24_PAYMENT_INC . 'class-lp-gateway-payro24.php';
		}

		/**
		 * Init hooks.
		 */
		protected function _init_hooks() {
			// add payment gateway class
			add_filter( 'learn_press_payment_method', array( $this, 'add_payment' ) );
			add_filter( 'learn-press/payment-methods', array( $this, 'add_payment' ) );
		}

		/**
		 * Enqueue assets.
		 *
		 */
		protected function _enqueue_assets() {
			return;
			
			if (LP()->settings->get( 'learn_press_payro24.enable' ) == 'yes' ) {
				$user = learn_press_get_current_user();

				learn_press_assets()->enqueue_script( 'learn-press-payro24-payment', $this->get_plugin_url( 'assets/js/script.js' ), array() );
				learn_press_assets()->enqueue_style( 'learn-press-payro24', $this->get_plugin_url( 'assets/css/style.css' ), array() );

				$data = array(
					'plugin_url'  => plugins_url( '', LP_ADDON_payro24_PAYMENT_FILE )
				);
				wp_localize_script( 'learn-press-payro24', 'learn_press_payro24_info', $data );
			}
		}

		/**
		 * Add payro24 to payment system.
		 *
		 * @param $methods
		 *
		 * @return mixed
		 */
		public function add_payment( $methods ) {
			$methods['payro24'] = 'LP_Gateway_payro24';

			return $methods;
		}

		/**
		 * Plugin links.
		 *
		 * @return array
		 */
		public function plugin_links() {
			$links[] = '<a href="' . admin_url( 'admin.php?page=learn-press-settings&tab=payments&section=payro24' ) . '">' . __( 'Settings', 'learnpress-payro24' ) . '</a>';

			return $links;
		}
	}
}