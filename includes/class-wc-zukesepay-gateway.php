<?php
/**
 * Gateway class
 *
 * @package Woo_ZukesePay/Classes/Gateway
 * @version 1.1.8
 */

if(!defined('ABSPATH')) {
	exit;
}

/**
 * Gateway.
 */
class WC_ZukesePay_Gateway extends WC_Payment_Gateway {

    public $payment_url;
    public $status_url;
    public $cancellations_url;
    public $zukesepay_token;
    public $seller_token;
    public $invoice_prefix;
    public $debug;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'zukesepay';
		$this->icon               = apply_filters('woo_zukesepay_icon', plugins_url('assets/images/zukesepay.png', plugin_dir_path(__FILE__)));
		$this->method_title       = __('ZukesePay', 'woo-zukesepay');
		$this->method_description = __('Accept payments using the ZukesePay.', 'woo-zukesepay');
		$this->order_button_text  = __('Proceed to payment', 'woo-zukesepay');

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title             = $this->get_option('title');
		$this->description       = $this->get_option('description');
		$this->zukesepay_token      = $this->get_option('zukesepay_token');
		$this->seller_token      = $this->get_option('seller_token');
		$this->invoice_prefix    = $this->get_option('invoice_prefix');
		$this->debug             = $this->get_option('debug');


		$baseUrl = 'https://painel.zukesepay.com/api/v1';
		$this->payment_url = $baseUrl.'/ecommerce/public/payments';
		$this->status_url = $baseUrl.'/ecommerce/public/payments/{{order_id}}/status';
		$this->cancellations_url = $baseUrl.'/ecommerce/public/payments/{{order_id}}/cancellations';

		// Active logs.
		if($this->debug == 'yes') {
			if(function_exists('wc_get_logger')) {
				$this->log = wc_get_logger();
			} else {
				$this->log = new WC_Logger();
			}
		}

		// Set the API.
		$this->api = new WC_ZukesePay_API($this);

		// Main actions.
		add_action('woocommerce_api_wc_zukesepay_gateway', array($this, 'process_callback'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		add_action('woocommerce_order_status_cancelled', array($this, 'cancel_payment'));
		add_action('woocommerce_order_status_refunded', array($this, 'cancel_payment'));
		add_action('woocommerce_thankyou', array($this, 'thankyou_page'));
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
	    return true;
		//return get_woocommerce_currency()  === 'BRL';
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = $this->get_option('enabled') === 'yes' && $this->zukesepay_token !== '' && $this->seller_token !== '' && $this->using_supported_currency();
		return $available;
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if(defined('WC_VERSION') && version_compare(WC_VERSION, '2.2', '>=')) {
			return '<a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.log')) . '">' . __('System Status &gt; Logs', 'woo-zukesepay') . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'              => array(
				'title'   => __('Enable/Disable', 'woo-zukesepay'),
				'type'    => 'checkbox',
				'label'   => __('Enable ZukesePay', 'woo-zukesepay'),
				'default' => 'yes',
			),
			'title'                => array(
				'title'       => __('Title', 'woo-zukesepay'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'woo-zukesepay'),
				'desc_tip'    => true,
				'default'     => __('ZukesePay', 'woo-zukesepay'),
			),
			'description'          => array(
				'title'       => __('Description', 'woo-zukesepay'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'woo-zukesepay'),
				'default'     => __('Pay via ZukesePay', 'woo-zukesepay'),
			),
			'zukesepay_token'         => array(
				'title'       => __('ZukesePay Token', 'woo-zukesepay'),
				'type'        => 'textarea',
				/* translators: %s: link to ZukesePay settings */
				'description' => sprintf(__('Please enter your ZukesePay token. This is needed to process the payments and notifications. Is possible generate a new token %s.', 'woo-zukesepay'), '<a href="https://zukesepay.com/dashboard/ecommerce-token" target="_blank">' . __('here', 'woo-zukesepay') . '</a>'),
				'default'     => '',
			),
			'seller_token'         => array(
				'title'       => __('Seller Token', 'woo-zukesepay'),
				'type'        => 'text',
				'description' => __('Please enter your Seller token.', 'woo-zukesepay'),
				'default'     => '',
			),
			'invoice_prefix'         => array(
				'title'       => __('Invoice Prefix', 'woo-zukesepay'),
				'type'        => 'text',
				'description' => __('Please enter a prefix for your invoice numbers. If you use your ZukesePay account for multiple stores ensure this prefix is unqiue as ZukesePay will not allow orders with the same invoice number.', 'woo-zukesepay'),
				'desc_tip'    => true,
				'default'     => 'ZUKPAY-',
			),
			'debug'                => array(
				'title'       => __('Debug Log', 'woo-zukesepay'),
				'type'        => 'checkbox',
				'label'       => __('Enable logging', 'woo-zukesepay'),
				'default'     => 'no',
				/* translators: %s: log page link */
				'description' => sprintf(__('Log ZukesePay events, such as API requests, inside %s', 'woo-zukesepay'), $this->get_log_view()),
			),
		);
	}
	
	/**
	 * Admin page.
	 */
	public function admin_options() {
		include dirname(__FILE__) . '/admin/views/html-admin-page.php';
	}
	
	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	protected function send_email($subject, $title, $message) {
		$mailer = WC()->mailer();

		$mailer->send(get_option('admin_email'), $subject, $mailer->wrap_message($title, $message));
	}
	
	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment($order_id) {
		$response = array();
		$order = wc_get_order($order_id);
		
		// Check if ZukesePay PaymentURL already exists.
		$response['url'] = $order->get_meta('ZukesePay_PaymentURL');
		if(!$response['url'])
		{
			$response = $this->api->do_checkout_request($order);
			
			if($response['url']) {
				$order->add_meta_data('ZukesePay_PaymentURL', $response['url'], true);
				$order->save();
			}
		}
		
		if($response['url'])
		{
			// Remove cart.
			WC()->cart->empty_cart();
			
			$order->add_order_note(__('ZukesePay: The buyer initiated the transaction, but so far the ZukesePay not received any payment information.', 'woo-zukesepay'));
			
			$url_redirect = $response['url'];
			if(wp_is_mobile())
			{
				$url_redirect = $this->get_return_url($order);
			}

			return array(
				'result'   => 'success',
				'redirect' => $url_redirect,
			);
		}
		else
        {
			foreach($response['error'] as $error) {
				wc_add_notice($error, 'error');
			}
        
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}
	
	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page($order_id) {
		$order = wc_get_order($order_id);
		$payment_url = $order->get_meta('ZukesePay_PaymentURL');
		
		if($order->get_status() == 'pending') {
			if(!empty($payment_url)) {
				wp_redirect($payment_url, 302);
			}
			else {
				include dirname(__FILE__) . '/views/html-receipt-page-error.php';
			}
		}
	}

	/**
	 * Output for the thank you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page($order_id) {
		$order = wc_get_order($order_id);

		if($order->get_status() == 'pending') {
			$payment_url = $order->get_meta('ZukesePay_PaymentURL');
            $order_date = new DateTime($order->date_created);
            $order_date = $order_date->format('d/m/Y H:i:s T');
            $order_billing_email = $order->get_billing_email();
            $order_total = $order->total;
            $order_currency = $order->currency;
            $order_prefix = $this->invoice_prefix;
			if(!empty($payment_url)) {
				@ob_clean();
				include dirname(__FILE__) . '/views/html-open-zukesepay.php';
			}
		}
	}

	/**
	 * Process callback.
	 */
	public function process_callback() {
		@ob_clean();
		$payment = $this->api->process_callback();
		$this->log->add($this->id, print_r($payment, true));
		if(is_array($payment)) {
			$order_id = intval(str_replace($this->invoice_prefix, '', $payment['order_id']));
			$order = wc_get_order($order_id);
			$cancellation_id = $order->get_meta('ZukesePay_cancellation_id');
			
			if(($payment['status'] == 'refunded') && empty($cancellation_id)) {
				$payment['cancellation_id'] = __('Payment refunded directly by ZukesePay.', 'woo-zukesepay');
			}
			
			$this->update_order_status($payment);
		}
		exit;
	}
	
	/**
	 * Save payment meta data.
	 *
	 * @param WC_Order $order Order instance.
	 * @param array $payment Payment Status.
	 */
	protected function save_payment_meta_data($order, $payment) {
		foreach($payment as $key => $value) {
			if(($key != 'order_id') && ($key != 'status')) {
				$order->add_meta_data('ZukesePay_' . $key, $value, true);
			}
		}
		$order->save();
	}
	
	/**
	 * Update order status.
	 *
	 * @param array $payment Payment Status.
	 */
	public function update_order_status($payment) {
		$id = intval(str_replace($this->invoice_prefix, '', $payment['order_id']));
		$order = wc_get_order($id);

		// Check if order exists.
		if(!$order) {
			return;
		}

		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;

		if($this->debug == 'yes') {
			$this->log->add($this->id, 'ZukesePay payment status for order ' . $order->get_order_number() . ' is: ' . $payment['status']);
		}
		
		// Save meta data.
		$this->save_payment_meta_data($order, $payment);
		
		switch($payment['status']) {
			case 'expired':
            case 'Cancelado':
				if(($order->get_status() == 'pending') || ($order->get_status() == 'on-hold')) {
					$order->update_status('cancelled', __('ZukesePay: Payment expired.', 'woo-zukesepay'));
				}

				break;
			case 'analysis':
				$order->update_status('on-hold', __('ZukesePay: Payment under review.', 'woo-zukesepay'));
				wc_reduce_stock_levels($order_id);
				
				break;
			case 'paid':
            case 'Em andamento':
				if($order->get_status() == 'pending') {
					wc_reduce_stock_levels($order_id);
				}
				$order->update_status('processing', __('ZukesePay: Payment approved.', 'woo-zukesepay'));
				break;
            case 'completed':
            case 'Concluida':
                $order->update_status('completed', __('ZukesePay: Payment completed and credited to your account.', 'woo-zukesepay'));
                break;
				
				break;
			case 'refunded':
				if($order->get_status() != 'refunded') { // Prevents repeat refunded.
					$order->update_status('refunded', __('ZukesePay: Payment refunded.', 'woo-zukesepay'));
					wc_increase_stock_levels($order_id);
				}
				else {
					$order->add_order_note(__('ZukesePay: Payment refunded.', 'woo-zukesepay'));
				}
				
				$this->send_email(
					/* translators: %s: order number */
					sprintf(__('Payment for order %s refunded', 'woo-zukesepay'), $order->get_order_number()),
					__('Payment refunded', 'woo-zukesepay'),
					/* translators: %s: order number */
					sprintf(__('Order %s has been marked as refunded by ZukesePay.', 'woo-zukesepay' ), $order->get_order_number())
				);
			
				break;
			case 'chargeback':
				$order->update_status('refunded', __('ZukesePay: Payment chargeback.', 'woo-zukesepay'));
				
				break;
			default:
				break;
		}
	}
	
	/**
	 * Cancel payment.
	 *
	 * @param  int $order_id Order ID.
	 */
	public function cancel_payment($order_id) {
		$order = wc_get_order($order_id);
		
		$cancellation_id = $order->get_meta('ZukesePay_cancellation_id');
		
		if(empty($cancellation_id)) { // Prevents repeat refunded.
			$payment = $this->api->do_payment_cancel($order);
				
			if(is_array($payment)) {
				$this->save_payment_meta_data($order, $payment);
			}
		}
	}
}
