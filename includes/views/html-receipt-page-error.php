<?php
/**
 * Receipt page error template
 *
 * @package Woo_ZukesePay/Templates
 */
if(!defined('ABSPATH')) {
	exit;
}
?>

<ul class="woocommerce-error">
	<li><?php echo __('ZukesePay Payment URL not found.', 'woo-zukesepay'); ?></li>
</ul>

<a class="button cancel" href="<?php echo esc_url($order->get_cancel_order_url()); ?>"><?php esc_html_e('Click to try again', 'woo-zukesepay'); ?></a>
