<?php
/**
 * Template for displaying payro24 payment error message.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/payro24-payment/payment-error.php.
 *
 * @author   payro24
 * @package  LearnPress/payro24/Templates
 * @version  1.0.1
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();
?>

<?php $settings = LP()->settings; ?>

<div class="learn-press-message error ">
	<div><?php echo __( 'Transation failed', 'learnpress-payro24' ); ?></div>
</div>
