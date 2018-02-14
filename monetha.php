<?php

/*
  Plugin Name: WooCommerce Payment Gateway - Monetha
  Plugin URI: Monetha
  Description: Accepts Monetha
  Version: 2.0.3
  Author: Monetha
  Author URI: Monetha
  License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

  @package WordPress
  @author Monetha
  @since 2.0.0
 */

add_action('plugins_loaded', 'monetha_init');

function monetha_init()
{
    
	if(!class_exists('WC_Payment_Gateway'))
	{
		return;
	};

	define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

	class WC_Gateway_Monetha extends WC_Payment_Gateway
	{
		/**
		 * @var WC_Logger
		 */
		private $log;

		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return \WC_Gateway_Monetha
		 */
		public function __construct()
		{
			global $woocommerce;

			$this->id			= 'monetha';
			$this->has_fields	= false;
			$this->method_title	= __('Monetha', 'woocommerce');

			// Load icon
			$this->icon = apply_filters('woocommerce_monetha_icon', PLUGIN_DIR . '/monetha_icon.png');

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();


			// Define user set variables
			$this->title		= $this->settings['title'];
			$this->merchantKey	= $this->settings['merchantKey'];
            $this->merchantSecret = $this->settings['merchantSecret'];
            $this->testMode = $this->settings['testMode'];

			$acc = get_query_var( 'page_id', 0 );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			{
				$info = 'Pay with Ether via Monetha'; // custom

				$this->description = $info;
			}

			// Actions
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_monetha', array($this, 'thankyou'));
			add_action('monetha_callback', array($this, 'payment_callback'));

			// New HOOK for callback ?wc-api=wc_gateway_monetha
			add_action('woocommerce_api_wc_gateway_monetha', array($this, 'check_callback_request'));
		}

		public function admin_options()
		{
			?>
			<h3><?php _e('Monetha', 'woothemes'); ?></h3>
			<p><?php _e('Monetha payment', 'woothemes'); ?></p>
			<table class="form-table">
				<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				?>
			</table>
		<?php
		} // End admin_options()

		function init_form_fields()
		{
			$this->form_fields = array
			(
				'enabled'     => array
				(
					'title'       => __('Enable Monetha', 'woocommerce'),
					'label'       => __('Enable Monetha payment', 'woocommerce'),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
                ),
                'testMode'     => array
				(
					'title'       => __('Test Mode', 'woocommerce'),
					'label'       => __('If checked all payments will be executed on Ropsten testnet', 'woocommerce'),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'description' => array
				(
					'title'       => __('Description', 'woocommerce'),
					'type'        => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
					'default'     => __('Pay via Monetha')
				),
				'title'       => array
				(
					'title'       => __('Title', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Payment method title that the customer will see on your website.', 'woocommerce'),
					'default'     => __('Monetha', 'woocommerce')
				),
				'merchantKey'   => array
				(
					'title'       => __('Merchant Key', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Merchant Key', 'woocommerce'),
					'default'     => __('', 'woocommerce')
				),
				'merchantSecret'    => array
				(
					'title'       => __('Merchant Secret', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Merchant Secret', 'woocommerce'),
					'default'     => __('', 'woocommerce')
				),
			);
		}

		function thankyou()
		{
			if ($description = $this->get_description())
			{
				echo wpautop(wptexturize($description));
			}
		}

        /* 
         *
         * Execute monetha integration calls and redirect to payment page on success
         * 
         */ 
		function process_payment($order_id)
		{
            $mthApi = "https://api.monetha.io/";
            if ($this->testMode === "yes") {
                $mthApi = "https://api-sandbox.monetha.io/";
            }   
            
			global $woocommerce;
            
            if ($this->merchantKey === '' || $this->merchantSecret === '') {
                $message = 'payment gateway miss-configured. Please check instructions and validate Woocommerce checkout settings';
                wc_add_notice( __('Payment error: ', 'woothemes') . $message, 'error' );
                if ($this->log) {
                    $this->log->add('monetha', $message);
                }
                return;
            }


            $order = new WC_Order($order_id);

			if ($this->log) {
				$this->log->add('monetha', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . trailingslashit(home_url()) . '?monethaListener=monetha_callback');
			}

			$items = array();

			// collect line items
			foreach($order->get_items() as $item_id => $item)
			{
				$items[] = array
				(
					'name'			=> $item->get_name(),
					'quantity'		=> $item->get_quantity(),
					'amount_fiat'	=> wc_format_decimal( $item->get_total()/$item->get_quantity(), 2 )
				);
            }

            // collect shipping amount if any
            $shippingCost = $order->get_shipping_total();
            if ($shippingCost > 0) {
                $items[] = array
				(
					'name'			=> 'Shipping costs',
					'quantity'		=> 1,
					'amount_fiat'	=> wc_format_decimal( $shippingCost, 2 )
				);
            }

            // collect tax amount if any
            $taxes = $order->get_total_tax();
            if ($taxes > 0) {
                $items[] = array
				(
					'name'			=> 'Taxes',
					'quantity'		=> 1,
					'amount_fiat'	=> wc_format_decimal( $taxes, 2 )
				);
            }


            $hash = hash('sha256',$this->merchantSecret + $order->get_id());

            $req = array(
                'deal' => array(
                    'amount_fiat' => $order->get_total(),
                    'currency_fiat' => get_woocommerce_currency(),
                    'line_items' => $items,
                    'client' => array(
                        'contact_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'contact_email' => $order->get_billing_email(),
                        'contact_phone_number' => $order->get_billing_phone()
                    )
                ),
                'return_url' => $this->get_return_url($order),
                'callback_url' => trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_monetha&order=' . $hash,
                'cancel_url' => $order->get_cancel_order_url(),
                'external_order_id' => $order->get_id()." ",
            );

            // Verify order information with Monetha
            $chSign = curl_init();
            curl_setopt_array($chSign, array(
                CURLOPT_URL => $mthApi . "v1/merchants/offer",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($req, JSON_NUMERIC_CHECK),
                CURLOPT_HTTPHEADER => array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json",
                    "MTH-Deal-Signature: " . $this->merchantKey . ":" . $this->merchantSecret
                )
            ));

            $res = curl_exec($chSign);
            $resStatus = curl_getinfo($chSign, CURLINFO_HTTP_CODE);
            $resJson = json_decode($res);

            error_log($res);
            
            if ($resStatus && $resStatus != 200 ) {
                $this->handleError($resStatus, $resJson);
                curl_close($chSign);
                return;
            } else {
                $resJson = json_decode($res);
                curl_close($chSign);
                
                // Execute deal and create order at Monetha
                if ($resJson && $resJson->token !== '') {
                    $chExec = curl_init();
                    curl_setopt_array($chExec, array(
                        CURLOPT_URL => $mthApi . "v1/deals/execute?token=" . $resJson->token,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POSTFIELDS => $res,
                        CURLOPT_CUSTOMREQUEST => "GET",
                        CURLOPT_HTTPHEADER => array(
                            "Cache-Control: no-cache",
                            "Content-Type: application/json"
                        )
                    ));

                    $res = curl_exec($chExec);
                    $resStatus = curl_getinfo($chExec, CURLINFO_HTTP_CODE);
                    $resJson = json_decode($res);

                    if ($resStatus && $resStatus !== 201) {
                        $this->handleError($resStatus, $resJson);
                        curl_close($chExec);
                        return;
                    } else {
                        
                        if ($resJson->order && $resJson->order->payment_url !== "") {
                            
                            // Mark as on-hold (we're awaiting the cheque)
                            $order->update_status('pending', __( 'Monetha processing payment', 'woocommerce' ));

                            // Remove cart
                            $woocommerce->cart->empty_cart();

                            // Redirect to payment page
                            return array(
                                'result'   => 'success',
                                'redirect' => $resJson->order->payment_url
                            );
                        } else {
                            $message = 'can not create an order - order information is invalid or service is temporary unavailable. Please consult php error logs for more information';
                            wc_add_notice( __('Payment error: ', 'woothemes') . $message, 'error' );
                            if ($this->log) { 
                                $this->log->add('monetha', 'Order #' . $order->id . ' ' . $message);
                            }
                            return;
                        }
                    }

                } else {
                    wc_add_notice( __('Payment error: ', 'woothemes') . $resJson->error, 'error' );
                    if ($this->log) { 
                        $this->log->add('monetha', 'Order #' . $order->id . ' ' . $message);
					}
                    return;
                }
            }
			
		}

		// Check callback
		function check_callback_request()
		{
			@ob_clean();
			do_action('monetha_callback', $_REQUEST);
		}

		/**
		 * Callback function used to complete the order in Woocommerce
		 *
		 * @param array $request
		 *
		 */
		function payment_callback($request)
		{
			global $woocommerce;

			try
			{
                $response = $_REQUEST;
                
                if (hash('sha256',$this->merchantSecret + $response['oid']) === $response['order']) {

                    $order = new WC_Order($response['oid']);

                    if (round($order->get_total(), 10) != $response['amount'])
                    {
                        if ($this->log)
                        {
                            $this->log->add('monetha', 'Order #' . $order->id . ' Amounts do no match. ' . (round($order->get_total(), 10)) . '!=' . $response['amount']);
                        }

                        throw new Exception('Amounts do not match');
                    }

                    if (strtolower(get_woocommerce_currency()) != strtolower($response['currency']))
                    {
                        if ($this->log)
                        {
                            $this->log->add('monetha', 'Order #' . $order->id . ' Currencies do not match. ' . get_woocommerce_currency() . '!=' . $response['currency']);
                        }

                        throw new Exception('Currencies do not match');
                    }

                    if ($order->status !== 'completed') {
                        if ($this->log) {
                            $this->log->add('monetha', 'Order #' . $order->id . ' Callback payment completed.');
                        }

                        $order->add_order_note(__('Payment completed. Check order #'. $response['mthoid'] . ' in your ethereum Merchant wallet provided by Monetha', 'woocomerce'));
                        $order->payment_complete();
                    }

                    
                    echo 'OK';
                } else {
                    $msg = 'invalid payment callback. Order hash does not match';
                    if ($this->log)
					{
						$this->log->add('monetha', $msg);
                    }
                    throw new Exception($msg);
                }
				
			}
			catch (Exception $e)
			{
                $msg = get_class($e) . ': ' . $e->getMessage();
				if ($this->log)
				{
					$this->log->add('monetha', $msg);
				}
				echo $msg;
			}

			exit();
        }

        // Method to handle error messages
        function handleError($status, $response) {
            $message = '';
            switch($status){
                case 400:
                    $message = $response->error;
                    break;
                case 502:
                    $message = 'something wrong have happened please contact Monetha support at support@monetha.io';
                    break;
                case 503:
                    $message = 'service temporary unavailable please try to refresh the page and try again';
                    break;
                default:
                    $message = 'can not create an order';
                    break;
            }

            wc_add_notice( __('Payment error: ', 'woothemes') . 'status ' . $resStatus . ' ' . $message, 'error' );
            if ($this->log) { 
                $this->log->add('monetha', 'Order #' . $order->id . ' ' . $message);
            }
        }
	}

	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package WooCommerce/Classes/Payment
	 * @return array $methods
	 */
	function add_monetha_gateway($methods)
	{
		$methods[] = 'WC_Gateway_Monetha';
		return $methods;
    }
    
    

	add_filter('woocommerce_payment_gateways', 'add_monetha_gateway');
}
