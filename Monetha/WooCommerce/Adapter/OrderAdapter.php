<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-27
 * Time: 10:47
 */

namespace Monetha\WooCommerce\Adapter;


use Monetha\Adapter\CallbackUrlInterface;
use Monetha\Adapter\InterceptorInterface;
use Monetha\Adapter\OrderAdapterInterface;

class OrderAdapter implements OrderAdapterInterface, CallbackUrlInterface
{
    /**
     * @var InterceptorInterface[]
     */
    private $items;

    /**
     * @var \WC_Order
     */
    private $order;

    public function __construct(\WC_Order $order)
    {
        $this->order = $order;
        $this->items = [];

        /** @var \WC_Order_Item_Product[] $orderItems */
        $orderItems = $order->get_items();

        // collect line items
        foreach ($orderItems as $item) {
            $product = new InterceptorAdapter();
            $product->prepareProductItem($item);

            $this->items[] = $product;
        }

        // collect shipping amount if any
        $shipping = new InterceptorAdapter();
        $shipping->prepareShippingItem($order);

        $this->items[] = $shipping;

        // collect tax amount if any
        $taxes = new InterceptorAdapter();
        $taxes->prepareTaxesItem($order);

        $this->items[] = $taxes;
    }

    public function getBaseUrl()
    {
        return $this->order->get_view_order_url();
    }

    public function getCartId()
    {
        return $this->order->get_id();
    }

    public function getCurrencyCode()
    {
        return get_woocommerce_currency();
    }

    public function getGrandTotalAmount()
    {
        return $this->order->get_total();
    }

    public function getItems()
    {
        return $this->items;
    }

    public function getCallbackUrl()
    {
        return trailingslashit(get_bloginfo('wpurl')) . '?rest_route=/v1/monetha/action';
    }
}