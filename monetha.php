<?php

/*
  Plugin Name: WooCommerce Payment Gateway - Monetha
  Plugin URI: Monetha
  Description: Accepts Monetha
  Version: 2.0.x
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
			$this->projectid	= $this->settings['projectid'];
			$this->secret		= $this->settings['secret'];

			// unused
			$this->test			= false;
			$this->debug		= false;

			$acc = get_query_var( 'page_id', 0 );

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			{
				$informacija = 'Pay with Ether via Monetha'; // custom

				$this->description = $informacija;
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
				'projectid'   => array
				(
					'title'       => __('Project ID', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Project id', 'woocommerce'),
					'default'     => __('', 'woocommerce')
				),
				'secret'    => array
				(
					'title'       => __('Secret', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Monetha secret', 'woocommerce'),
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

		//Redirect to payment
		function process_payment($order_id)
		{
			global $woocommerce;

			$order = new WC_Order($order_id);
			$language = explode('-', get_bloginfo( 'language', 'raw' ));
			$lng = array('lt'=>'LIT', 'lv'=>'LAV', 'ee'=>'EST', 'ru'=>'RUS', 'de'=>'GER', 'pl'=>'POL', 'en'=>'ENG');

			if ($this->log) {
				$this->log->add('monetha', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . trailingslashit(home_url()) . '?monethaListener=monetha_callback');
			}

			$lang = get_locale();
			$lang = explode('_', $lang);

			$items = array();

			// add line items
			foreach($order->get_items() as $item_id => $item)
			{
				$items[] = array
				(
					'name'			=> $item->get_name(),
					'quantity'		=> $item->get_quantity(),
					'price'			=> wc_format_decimal( $order->get_item_total( $item ), 2 ),
					'subtotal'		=> wc_format_decimal( $order->get_line_subtotal( $item ), 2 ),
					'total_tax'		=> wc_format_decimal( $order->get_line_tax( $item ), 2 ),
					'total'			=> wc_format_decimal( $order->get_line_total( $item ), 2 ),
					'warranty'		=> date('Y-m-d', strtotime('+24 month')),
				);
			}

			$request = array
			(
				'pid'			=> $this->projectid,
				'secret'		=> $this->secret,
				'oid'			=> $order->id,
				'amount'		=> round($order->get_total(), 10),
				'currency'		=> get_woocommerce_currency(),
				'return'		=> $this->get_return_url( $order ),
				'cancel'		=> $order->get_cancel_order_url(),
				'callback'		=> trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_monetha',

				'i_firstname'	=> $order->billing_first_name,
				'i_lastname'	=> $order->billing_last_name,
				'i_email'		=> $order->billing_email,
				'i_items'		=> json_encode($items),
				'i_delivery'	=> 'post',
			);

			$url = 'http://payment.monetha.io/orders/add?'.http_build_query($request);
			$url = preg_replace('/[\r\n]+/is', '', $url);

			return array(
				'result'   => 'success',
				'redirect' => $url,
			);
		}

		//Check callback
		function check_callback_request()
		{
			@ob_clean();
			do_action('monetha_callback', $_REQUEST);
		}

		/**
		 *
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

				$order = new WC_Order($response['oid']);

				if (round($order->get_total(), 10) > $response['amount'])
				{
					if ($this->log)
					{
						$this->log->add('monetha', 'Order #' . $order->id . ' Amounts do no match. ' . (round($order->get_total(), 10)) . '!=' . $response['amount']);
					}

					throw new Exception('Amounts do not match');
				}

				if (strtolower(get_woocommerce_currency()) != $response['currency'])
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

					$order->add_order_note(__('Callback payment completed', 'woocomerce'));
					$order->payment_complete();
				}

				echo 'OK';
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