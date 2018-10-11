<?php

add_action('plugins_loaded', 'monetha_init');

require_once dirname(__FILE__) . '/Helpers/GatewayService.php';
require_once dirname(__FILE__) . '/Helpers/OrdersService.php';
require_once dirname(__FILE__) . '/Helpers/HttpService.php';
require_once dirname(__FILE__) . '/Consts/Consts.php';

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

function monetha_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

    class WC_Gateway_Monetha extends WC_Payment_Gateway
    {
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
            $this->merchantSecret = $this->settings['merchantSecret'];
            $this->mthApiKey = $this->settings['mthApiKey'];
            $this->testMode = $this->settings['testMode'];

            $acc = get_query_var('page_id', 0);

            if (defined('DOING_AJAX') && DOING_AJAX) {
                $info = 'Pay with Ether via Monetha'; // custom
                $this->description = $info;
            }

            // Actions

            // Gateway setting save and validation for admin
            if (is_admin()) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            }

            // Thank you page
            add_action('woocommerce_thankyou_monetha', array($this, 'thankyou'));

            // Track status changed
            add_action('woocommerce_order_status_changed', array($this, 'order_status_changed'), 10, 4);
        }

        public function order_status_changed($order_id, $previousStatus, $currentStatus, $instance)
        {
            if ($instance->payment_method == 'monetha') {
                if (($previousStatus != $currentStatus) && $currentStatus == 'cancelled') {
                    $gateway = new WC_Gateway_Monetha();
                    $gatewayService = new GatewayService(
                        $gateway->settings['merchantSecret'],
                        $gateway->settings['mthApiKey'],
                        $gateway->settings['testMode']
                    );

                    $externalOrderId = $instance->get_meta('external_order_id');

                    if (!empty($externalOrderId)) {
                        $gatewayService->cancelOrder($externalOrderId);
                    }
                }
            }
        }

        public function admin_options()
        {
            ?>
                <h3><?php _e('Monetha', 'woothemes'); ?></h3>
                <p><?php _e('Monetha payment', 'woothemes'); ?></p>
                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html(); ?>
                </table>
            <?php
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'       => __('Enable Monetha', 'woocommerce'),
                    'label'       => __('Enable Monetha payment', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'testMode'    => array(
                    'title'       => __('Test Mode', 'woocommerce'),
                    'label'       => __('If checked all payments will be executed on Ropsten testnet', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                    'validate'    => ['v_des_f']
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout. (*Required)', 'woocommerce'),
                    'default'     => __('Pay via Monetha'),
                    'required'    => true
                ),
                'title'       => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Payment method title that the customer will see on your website. (*Required)', 'woocommerce'),
                    'default'     => __('Monetha', 'woocommerce')
                ),
                'merchantSecret'    => array(
                    'title'       => __('Merchant Secret', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Merchant Secret (*Required)', 'woocommerce'),
                    'default'     => __('', 'woocommerce'),
                ),
                'mthApiKey'    => array(
                    'title'       => __('Monetha Api Key', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Monetha Api Key (*Required)', 'woocommerce'),
                    'default'     => __('', 'woocommerce')
                ),
            );
        }

        public function process_admin_options()
        {
            $settings = new WC_Admin_Settings();
            $postData = $this->get_post_data();
            $requiredFieldsFilled = true;

            // Validate fields
            if (empty($postData['woocommerce_monetha_title']) ||
                empty($postData['woocommerce_monetha_description']) ||
                empty($postData['woocommerce_monetha_merchantSecret']) ||
                empty($postData['woocommerce_monetha_mthApiKey'])) {
                $settings->add_error('Please fill in required fields');
            }

            // Validate mth_api_key
            if ($requiredFieldsFilled) {
                $gateway = new WC_Gateway_Monetha();
                $gatewayService = new GatewayService(
                    $postData['woocommerce_monetha_merchantSecret'],
                    $postData['woocommerce_monetha_mthApiKey'],
                    $postData['woocommerce_monetha_testMode']
                );

                try {
                    if (!$gatewayService->validateApiKey()) {
                        $settings->add_error('Merchant secret or Monetha Api Key is not valid.');
                    }
                } catch (\Exception $ex) {
                    $settings->add_error('Merchant secret or Monetha Api Key is not valid.');
                }
            }

            return parent::process_admin_options();
        }

        public function thankyou($order_id)
        {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            if ($this->merchantSecret === '' || $this->mthApiKey === '') {
                $message = 'payment gateway miss-configured. Please check instructions and validate Woocommerce checkout settings';
                wc_add_notice(__('Payment error: ', 'woothemes') . $message, 'error');
                return;
            }

            $gatewayService = new GatewayService(
                $this->merchantSecret,
                $this->mthApiKey,
                $this->testMode
            );

            $apiUrl = $gatewayService->getApiUrl();
            $offerBody = OrdersService::prepareOfferBody($order_id);

            try {
                $offerResponse = HttpService::callApi($apiUrl . 'v1/merchants/offer_auth', 'POST', $offerBody, ["Authorization: Bearer " . $this->mthApiKey]);
                $executionResponse = HttpService::callApi($apiUrl . 'v1/deals/execute?token=' . $offerResponse->token, 'GET', null, []);

                if ($executionResponse->order && $executionResponse->order->payment_url !== "") {

                    // Mark as on-hold (we're awaiting the cheque)
                    $order = new WC_Order($order_id);
                    $order->update_status('pending', __('Monetha processing payment', 'woocommerce'));

                    // Save external order id
                    $order->update_meta_data('external_order_id', $executionResponse->order->id);
                    $order->update_meta_data('payment_url', $executionResponse->order->payment_url);
                    $order->save();

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    // Redirect to payment page
                    return array(
                        'result'   => 'success',
                        'redirect' => $executionResponse->order->payment_url
                    );
                } else {
                    $message = 'can not create an order - order information is invalid or service is temporary unavailable. Please consult php error logs for more information';
                    wc_add_notice(__('Payment error: ', 'woothemes') . $message, 'error');
                    return;
                }
            } catch (\Exception $ex) {
                wc_add_notice(__('Payment error: ', 'woothemes') . ' ' . $ex->getMessage(), 'error');
                return;
            }
        }

        // Method to handle error messages
        public function handleError($status = null, $response = null)
        {
            $message = '';
            switch ($status) {
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

            wc_add_notice(__('Payment error: ', 'woothemes') . ' ' . $message, 'error');
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

    // Init custom rest api to be able monetha core api to access orders
    add_action('rest_api_init', 'register_routes');

    function register_routes()
    {
        register_rest_route('v1/monetha/', 'action', array(
            'methods'  => 'POST',
            'callback' => 'process_action',
        ));
    }

    function process_action(WP_REST_Request $request)
    {
        // Validate signature
        try {
            $gateway = new WC_Gateway_Monetha();
            $gatewayService = new GatewayService(
            $gateway->settings['merchantSecret'],
            $gateway->settings['mthApiKey'],
            $gateway->settings['testMode']
        );
            $signature = $request->get_headers()['mth_signature'][0];
            $body = $request->get_body();
            $data = json_decode($request->get_body());

            // Just simple ping event action
            if ($data->event == EventType::PING) {
                return [
                    'status' => 200,
                    'message' => 'e-shop healthy'
                ];
            }

            if ($gatewayService->validateSignature($signature, $body)) {
                try {
                    $gatewayService->processAction($data);
                } catch (Exception $ex) {
                    return new WP_Error('bad_request', $ex->getMessage(), array( 'status' => 400 ));
                }
            } else {
                return new WP_Error('bad_signature', 'Bad signature', array( 'status' => 401 ));
            }
        } catch (\Exception $ex) {
            return new WP_Error('bad_request', $ex->getMessage(), array( 'status' => 400 ));
        }
    }
}
