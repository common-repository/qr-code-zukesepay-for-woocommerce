<?php
/**
 * Plugin Name:          QR Code ZukesePay for WooCommerce
 * Plugin URI:           https://wordpress.org/plugins/woo-zukesepay/
 * Description:          Includes ZukesePay E-Commerce as a payment gateway to WooCommerce.
 * Author:               Zukese Pay
 * Author URI:           https://zukesepay.com
 * Version:              1.0
 * License:              GPLv3 or later
 * Text Domain:          woo-zukesepay
 * Domain Path:          /languages
 * WC requires at least: 3.0.0
 * WC tested up to:      4.2.0
 *
 * QR Code ZukesePay for WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * QR Code ZukesePay for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with QR Code ZukesePay for WooCommerce. If not, see
 * <https://www.gnu.org/licenses/gpl-3.0.txt>.
 *
 * @package Woo_ZukesePay
 */

defined('ABSPATH') || exit;

// Plugin constants.
define('WC_ZUKESEPAY_VERSION', '1.1.8');
define('WC_ZUKESEPAY_PLUGIN_FILE', __FILE__);

if(!class_exists('WC_ZukesePay')) {
	include_once dirname(__FILE__) . '/includes/class-wc-zukesepay.php';
	add_action('plugins_loaded', array('WC_ZukesePay', 'init'));
}
