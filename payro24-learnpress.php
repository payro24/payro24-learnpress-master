<?php
/*
Plugin Name: payro24 Payment - LearnPress
Plugin URI: http://payro24.ir/
Description: payro24 payment gateway for LearnPress.
Author:payro24
Version: 1.0.1
Author URI: https://github.com/payro24/
Tags: learnpress, payro24
Text Domain: learnpress-payro24
Domain Path: /languages/
*/

// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

define( 'LP_ADDON_payro24_PAYMENT_FILE', __FILE__ );
define( 'LP_ADDON_payro24_PAYMENT_VER', '1.0.1' );
define( 'LP_ADDON_payro24_PAYMENT_REQUIRE_VER', '1.0.1' );

/**
 * Class LP_Addon_payro24_Payment_Preload
 */
class LP_Addon_payro24_Payment_Preload {

	/**
	 * LP_Addon_payro24_Payment_Preload constructor.
	 */
	public function __construct() {
		load_plugin_textdomain( 'learnpress-payro24', false, basename( dirname(__FILE__) ) . '/languages' );
		add_action( 'learn-press/ready', array( $this, 'load' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Load addon
	 */
	public function load() {
		LP_Addon::load( 'LP_Addon_payro24_Payment', 'inc/load.php', __FILE__ );
		remove_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Admin notice
	 */
	public function admin_notices() {
		?>
        <div class="error">
            <p><?php echo wp_kses(
					sprintf(
						__( '<strong>%s</strong> addon version %s requires %s version %s or higher is <strong>installed</strong> and <strong>activated</strong>.', 'learnpress-payro24' ),
						__( 'LearnPress payro24 Payment', 'learnpress-payro24' ),
						LP_ADDON_payro24_PAYMENT_VER,
						sprintf( '<a href="%s" target="_blank"><strong>%s</strong></a>', admin_url( 'plugin-install.php?tab=search&type=term&s=learnpress' ), __( 'LearnPress', 'learnpress-payro24' ) ),
						LP_ADDON_payro24_PAYMENT_REQUIRE_VER
					),
					array(
						'a'      => array(
							'href'  => array(),
							'blank' => array()
						),
						'strong' => array()
					)
				); ?>
            </p>
        </div>
		<?php
	}
}

new LP_Addon_payro24_Payment_Preload();
