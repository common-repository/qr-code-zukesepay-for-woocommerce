<?php
/**
 * API class
 *
 * @package Woo_ZukesePay/Classes/API
 * @version 1.1.8
 */

if(!defined('ABSPATH')) {
	exit;
}

/**
 * API.
 */
class WC_ZukesePay_API {

	/**
	 * Gateway class.
	 *
	 * @var WC_ZukesePay_Gateway
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_ZukesePay_Gateway $gateway Payment Gateway instance.
	 */
	public function __construct($gateway = null) {
		$this->gateway = $gateway;
	}
	
	/**
	 * Get the payment URL.
	 *
	 * @return string.
	 */
	protected function get_payment_url() {
		return $this->gateway->payment_url;
	}
	
	/**
	 * Get the status URL.
	 *
	 * @param  string $order_id Order ID.
	 *
	 * @return string.
	 */
	protected function get_status_url($order_id) {
	    return str_replace("{{order_id}}", $order_id, $this->gateway->status_url);
	}
	
	/**
	 * Get the cancellation URL.
	 *
	 * @param  string $order_id Order ID.
	 *
	 * @return string.
	 */
	protected function get_cancellation_url($order_id) {
        return str_replace("{{order_id}}", $order_id, $this->gateway->cancellations_url);
	}
	
	/**
	 * Do requests in the ZukesePay API.
	 *
	 * @param  string $url      URL.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request($url, $method = 'POST', $data = array(), $headers = array()) {
		$params = array(
			'method'  => $method,
			'timeout' => 30,
		);

		if($method == 'POST' && !empty($data)) {
			$params['body'] = $data;
		}

		if(!empty($headers)) {
			$params['headers'] = $headers;
		}

		return wp_safe_remote_post($url, $params);
	}
	
	/**
	 * Get the headers.
	 *
	 * @return array.
	 */
	protected function get_request_headers() {
		return array(
						'X-ZukesePay-Token' => $this->gateway->zukesepay_token,
						'Content-Type' => 'application/json'
					);
	}

    /**
     * Get the checkout json.
     *
     * @param WC_Order $order Order data.
     * @return string
     */
	protected function get_checkout_json($order) {
		$cellphone = $order->get_meta('_billing_cellphone');
		
		if(empty($cellphone)) {
			$cellphone = $order->get_billing_phone();
		}
		
		$payment = array(
            'order_id' => $this->gateway->invoice_prefix . $order->get_id(),
            'callback_url' => WC()->api_request_url('WC_ZukesePay_Gateway'),
            'return_url' => $this->gateway->get_return_url($order),
            'expires_at' => date('c', strtotime(date("Y-m-d", time() + 86400))),
            'value' => $order->get_total(),
            'buyer_first_name' => $order->get_billing_first_name(),
            'buyer_last_name' => $order->get_billing_last_name(),
            'buyer_document' => '999999999',
            'buyer_email' => $order->get_billing_email(),
            'buyer_phone' => $cellphone
        );
		
		return json_encode($payment);
	}
	
	/**
	 * Do checkout request.
	 *
	 * @param  WC_Order $order  Order data.
	 *
	 * @return array
	 */
	public function do_checkout_request($order)
    {
        $checkout_response = array(
            'url'   => '',
            'data'  => '',
            'error' => '',
        );

		$json = $this->get_checkout_json($order);
		$body = '';
		
		if($this->gateway->debug == 'yes')
		{
		    $messages = array(
		        'Payment request for: '.$order->get_order_number(),
		        'Destination: '.$this->get_payment_url(),
                'Headers: '.print_r($this->get_request_headers(), true),
                'Payload: '.$json
            );
			$this->gateway->log->add($this->gateway->id, print_r($messages, true));
		}

        if($this->gateway->debug == 'yes')
        {
            $params = array(
                'method' => 'POST',
                'timeout' => 30,
                'body' => $json,
                'headers' => $this->get_request_headers()
            );
            $http = _wp_http_get_object();
            $response = $http->post($this->get_payment_url(), $params);
        }
        else
        {
            $response = $this->do_request($this->get_payment_url(), 'POST', $json, $this->get_request_headers());
        }

		if(is_wp_error($response))
		{
			if($this->gateway->debug == 'yes')
			{
				$this->gateway->log->add($this->gateway->id, 'WP_Error in generate payment request: ' . $response->get_error_message());
			}

            $checkout_response['error'] = array(__('An error occurred trying to get checkout URL', 'woo-zukesepay'));

			return $checkout_response;
		}
		else
        {
            $body = json_decode($response['body'], true);

            if($this->gateway->debug == 'yes')
            {
                $messages = array(
                    'Payment request for: '.$order->get_order_number(),
                    'Response body: '.print_r($body, true)
                );
                $this->gateway->log->add($this->gateway->id, print_r($messages, true));
            }

			if(json_last_error() != JSON_ERROR_NONE)
			{
				if($this->gateway->debug == 'yes')
				{
					$this->gateway->log->add(
					    $this->gateway->id,
                        'Error while parsing the ZukesePay response: ' . print_r($response, true));
				}

                $checkout_response['error'] = array(__('An error occurred trying to process the checkout', 'woo-zukesepay'));

				return $checkout_response;
			}
		}
		
		if($response['response']['code'] === 200)
		{
			if($this->gateway->debug == 'yes') {
				$this->gateway->log->add($this->gateway->id, 'ZukesePay Payment URL created with success! The return is: '
					. print_r($body, true));
			}

            $checkout_response['url'] = $body['payment_url'];

            return $checkout_response;
		}
		else if($response['response']['code'] === 401)
		{
			if($this->gateway->debug == 'yes')
			{
				$this->gateway->log->add($this->gateway->id, 'Invalid token settings!');
			}

            $checkout_response['error'] = array(__('Too bad! The token from the ZukesePay are invalids my little friend!', 'woo-zukesepay'));

			return $checkout_response;
		}
		else if(($response['response']['code'] === 422) || ($response['response']['code'] === 500))
		{
			if(isset($body['message']))
			{
				$errors = array();

				if($this->gateway->debug == 'yes')
				{
					$this->gateway->log->add($this->gateway->id, 'Failed to generate the ZukesePay Payment URL: ' . print_r( $response, true ) );
				}

                $checkout_response['error'] = array($body['message']);

				return $checkout_response;
			}
		}
		
		if($this->gateway->debug == 'yes')
		{
			$this->gateway->log->add($this->gateway->id, 'Error generating the ZukesePay Payment URL: ' . print_r($response, true));
		}

        $checkout_response['error'] = array('<strong>' . __('ZukesePay', 'woo-zukesepay') . '</strong>: ' . __('An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'woo-zukesepay'));

		return $checkout_response;
	}
	
	/**
	 * Process callback.
	 * TODO: Souza
	 * @return array | boolean
	 */
	public function process_callback() {
		$payment = array();
		
		if($this->gateway->debug == 'yes') {
			$this->gateway->log->add($this->gateway->id, 'Received CALLBACK request, starting...');
		}
		
		// Checks the Seller Token.
		if($_SERVER['HTTP_X_ZUKESEPAY_SELLER_TOKEN'] != $this->gateway->seller_token) {
			if($this->gateway->debug == 'yes') {
				$this->gateway->log->add($this->gateway->id, 'Invalid CALLBACK request, invalid Seller Token.');
			}
			
			return false;
		}

        $this->gateway->log->add($this->gateway->id, 'Callback request has valid seller/shop token, trying to decode response body');

		$payment = file_get_contents("php://input");
		$payment = json_decode($payment, true);

        $this->gateway->log->add($this->gateway->id, print_r($payment, true));

		if(json_last_error() != JSON_ERROR_NONE) {
			if($this->gateway->debug == 'yes')
			{
                $messages = array(
                    'Invalid CALLBACK request body, it is not a valid JSON string',
                    'Error: '.json_last_error_msg(),
                    'Payload: '.print_r($payment, true)
                );
                $this->gateway->log->add($this->gateway->id, print_r($messages, true));
			}
			
			return false;
		}

		if(array_key_exists('status', $payment))
		{
			if($this->gateway->debug == 'yes') {
				$messages = array(
					'ZukesePay status response is valid!',
					'Order ID: '.$payment['order_id'],
					'Order status: '.$payment['status'],
				);
				$this->gateway->log->add($this->gateway->id, print_r($messages, true));
			}

			return $payment;
		}

		if($this->gateway->debug == 'yes') {
			$messages = array(
				'Failed to update order status. Response is a valid JSON, but does not contains a status key',
				'Order ID: '.$payment['order_id'],
			);
			$this->gateway->log->add($this->gateway->id, print_r($messages, true));
		}
		
		return false;
	}
	
	/**
	 * Do payment cancel.
	 *
	 * @param  WC_Order $order  Order data.
	 *
	 * @return array | boolean
	 */
	public function do_payment_cancel($order) {
		$json = '';
		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
		$order_id = $this->gateway->invoice_prefix . strval($order_id);
		$authorization_id = $order->get_meta('ZukesePay_authorization_id');
		
		if(!empty($authorization_id)) {
			$json = json_encode(array('authorization_id' => $authorization_id));
			
			if($this->gateway->debug == 'yes') {
				$this->gateway->log->add($this->gateway->id, 'Get payment cancel for order ' . $order->get_order_number() . ' and refund with the authorization_id: ' . $authorization_id);
			}
		}
		else {
			if($this->gateway->debug == 'yes') {
				$this->gateway->log->add($this->gateway->id, 'Get payment cancel for order ' . $order->get_order_number());
			}
		}
		
		$payment = $this->do_request($this->get_cancellation_url($order_id), 'POST', $json, $this->get_request_headers());
		
		if($payment['response']['code'] === 200) {
				if($this->gateway->debug == 'yes') {
					$this->gateway->log->add($this->gateway->id, 'ZukesePay payment cancel response OK.');
				}
		}

        if($this->gateway->debug == 'yes') {
            $this->gateway->log->add($this->gateway->id, $payment['body']);
        }
		
		$payment = json_decode($payment['body'], true);
			
		if(json_last_error() != JSON_ERROR_NONE) {
			if($this->gateway->debug == 'yes') {
				$this->gateway->log->add($this->gateway->id, 'Error while parsing the ZukesePay payment cancel response: ' . print_r($payment, true));
			}
			
			return false;
		}
		
		return $payment;
	}
}

