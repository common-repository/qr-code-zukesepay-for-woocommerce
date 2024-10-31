<?php
/**
 * Plugin's main class
 *
 * @package Woo_ZukesePay
 */

/**
 * WooCommerce bootstrap class.
 */
class WC_ZukesePay {

	/**
	 * Initialize the plugin public actions.
	 */
	public static function init() {
		// Load plugin text domain.
		add_action('init', array(__CLASS__, 'load_plugin_textdomain'));

		// Checks with WooCommerce is installed.
		if(class_exists('WC_Payment_Gateway')) {
			self::includes();
			
			add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
			add_filter('plugin_action_links_' . plugin_basename(WC_ZUKESEPAY_PLUGIN_FILE), array(__CLASS__, 'plugin_action_links'));
		} else {
			add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
		}
	}
	
	/**
	 * Load the plugin text domain for translation.
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain('woo-zukesepay', false, dirname(plugin_basename(WC_ZUKESEPAY_PLUGIN_FILE)) . '/languages/');
	}
	
	/**
	 * Action links.
	 *
	 * @param array $links Action links.
	 *
	 * @return array
	 */
	public static function plugin_action_links($links) {
		$plugin_links   = array();
		$plugin_links[] = '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=zukesepay')) . '">' . __('Settings', 'woo-zukesepay') . '</a>';

		return array_merge($plugin_links, $links);
	}
	
	/**
	 * Includes.
	 */
	private static function includes() {
		include_once dirname(__FILE__) . '/class-wc-zukesepay-api.php';
		include_once dirname(__FILE__) . '/class-wc-zukesepay-gateway.php';
	}
	
	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param  array $methods WooCommerce payment methods.
	 *
	 * @return array Payment methods with ZukesePay.
	 */
	public static function add_gateway($methods) {
		$methods[] = 'WC_ZukesePay_Gateway';

		return $methods;
	}
	
	/**
	 * WooCommerce missing notice.
	 */
	public static function woocommerce_missing_notice() {
		include dirname(__FILE__) . '/admin/views/html-notice-missing-woocommerce.php';
	}
}