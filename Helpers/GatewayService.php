<?php

require_once dirname(__FILE__) . '/../Consts/Consts.php';
require_once dirname(__FILE__) . '/HttpService.php';
require_once dirname(__FILE__) . '/OrdersService.php';
require_once dirname(__FILE__) . '/JWT.php';

class GatewayService
{
    private $merchantSecret;
    private $mthApiKey;
    private $testMode;

    public function __construct(string $merchantSecret, string $mthApiKey, string $testMode)
    {
        $this->merchantSecret = $merchantSecret;
        $this->mthApiKey = $mthApiKey;
        $this->testMode = $testMode == 'no' ? "0" : $testMode;
    }

    /**
     * Validate is current signature matches hmac encrypted with merchant secret and payload data
     *
     * @param string $data
     * @return boolean
     */
    public function validateSignature(string $signature, string $data) : bool
    {
        return $signature == base64_encode(hash_hmac('sha256', $data, $this->merchantSecret, true));
    }

    /**
     * Undocumented function
     *
     * @param EventType $event
     * @param ActionType $action
     * @param [stdClass] $data
     * @return object
     */
    public function processAction(stdClass $data)
    {
        $order = OrdersService::getOrder((int)$data->payload->external_order_id);

        switch ($data->resource) {
            case Resource::ORDER:
                switch ($data->event) {
                    case EventType::CANCELLED:
                        OrdersService::cancelOrder($order, $data->payload->note);
                        break;
                    case EventType::FINALIZED:
                        OrdersService::setOrderPaid($order);
                        break;
                    default:
                        return new WP_Error('bad_action', 'Bad action type', array( 'status' => 400 ));
                        break;
                }
                break;
            default:
            return new WP_Error('bad_event', 'Bad event type', array( 'status' => 400 ));
            break;
        }
    }

    public function validateApiKey() : bool
    {
        $apiUrl = $this->getApiUrl();
        $merchantId = $this->getMerchantId();

        if ($merchantId == null) {
            return false;
        }

        $apiUrl = $apiUrl . 'v1/merchants/' . $merchantId .'/secret';

        $response = HttpService::callApi($apiUrl, 'GET', null, ["Authorization: Bearer " . $this->mthApiKey]);
        return ($response && $response->integration_secret && $response->integration_secret == $this->merchantSecret);
    }

    public function getMerchantId()
    {
        $tks = explode('.', $this->mthApiKey);
        if (count($tks) != 3) {
            return null;
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($bodyb64));

        if (isset($payload->mid)) {
            return $payload->mid;
        }

        return null;
    }

    public function getApiUrl()
    {
        $apiUrl = ApiType::PROD;

        if ((bool)$this->testMode) {
            $apiUrl = ApiType::TEST;
        }

        return $apiUrl;
    }

    /**
     * Send cancel action to monetha api
     *
     * @param [type] $orderId
     * @return boolean
     */
    public function cancelOrder($orderId)
    {
        $apiUrl = $this->getApiUrl();
        $apiUrl = $apiUrl . 'v1/orders/' . $orderId .'/cancel';
        $body = ['cancel_reason' => 'Order was cancelled from e-shop.'];
        return HttpService::callApi($apiUrl, 'POST', $body, ["Authorization: Bearer " . $this->mthApiKey]);
    }
}
