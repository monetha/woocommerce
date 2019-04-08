<?php

namespace Monetha\WooCommerce\Adapter;

use Monetha\Adapter\WebHookAdapterAbstract;
use stdClass;
use WC_Order;

class WebHookAdapter extends WebHookAdapterAbstract
{
    /**
     * @var WC_Order
     */
    private $order;

    public function __construct(stdClass $data)
    {
        $this->order = new WC_Order((int) $data->payload->external_order_id);
    }

    public function authorize()
    {
        return $this->order->update_status('processing', 'Order has been successfully paid by your card.', true);
    }

    public function cancel($note)
    {
        if (empty($note)) {
            $note = 'Order has been canceled by Monetha.';
        }

        return $this->order->update_status('cancelled', $note, true);
    }

    public function finalize()
    {
        return $this->order->update_status('processing', 'Order has been successfully paid.', true);
    }
}
