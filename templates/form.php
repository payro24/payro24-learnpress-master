<?php
/**
 * Template for displaying payro24 payment form.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/payro24-payment/form.php.
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

<p><?php echo $this->get_description(); ?></p>

<div id="learn-press-payro24-form" class="<?php if(is_rtl()) echo ' learn-press-form-payro24-rtl'; ?>">
    <p class="learn-press-form-row">
        <label><?php echo wp_kses( __( 'Email', 'learnpress-payro24' ), array( 'span' => array() ) ); ?></label>
        <input type="text" name="learn-press-payro24[email]" id="learn-press-payro24-payment-email"
               maxlength="30" value=""  placeholder="example@gmail.com"/>
		<div class="learn-press-payro24-form-clear"></div>
    </p>
	<div class="learn-press-payro24-form-clear"></div>
    <p class="learn-press-form-row">
        <label><?php echo wp_kses( __( 'Mobile', 'learnpress-payro24' ), array( 'span' => array() ) ); ?></label>
        <input type="text" name="learn-press-payro24[mobile]" id="learn-press-payro24-payment-mobile" value=""
               placeholder="09121234567"/>
		<div class="learn-press-payro24-form-clear"></div>
    </p>
	<div class="learn-press-payro24-form-clear"></div>
</div>
