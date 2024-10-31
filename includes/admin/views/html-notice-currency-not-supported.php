<?php
/**
 * Admin View: Notice - Currency not supported.
 *
 * @package Woo_ZukesePay/Admin/Notices
 */

if(!defined('ABSPATH')) {
	exit;
}
?>

<div class="error inline">
	<p><strong><?php _e('ZukesePay Disabled', 'woo-zukesepay'); ?></strong>: <?php printf(__('Currency <code>%s</code> is not supported. Works only with Brazilian Real.', 'woo-zukesepay'), get_woocommerce_currency()); ?>
	</p>
</div>
