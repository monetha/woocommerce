<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-27
 * Time: 10:47
 */

namespace Monetha\WooCommerce\Adapter;


use Monetha\Adapter\InterceptorInterface;

class InterceptorAdapter implements InterceptorInterface
{
    private $name;
    private $price;
    private $quantity;

    public function prepareProductItem(\WC_Order_Item_Product $item) {
        $this->name = $item->get_name();
        $this->price = wc_format_decimal($item->get_total() / $item->get_quantity(), 2);
        $this->quantity = $item->get_quantity();
    }

    public function prepareShippingItem(\WC_Order $order) {
        $shippingCost = $order->get_shipping_total();

        $this->name = 'Shipping costs';
        $this->price = wc_format_decimal($shippingCost, 2);
        $this->quantity = 1;
    }

    public function prepareTaxesItem(\WC_Order $order) {
        $taxes = $order->get_total_tax();

        $this->name = 'Taxes';
        $this->price = wc_format_decimal($taxes, 2);
        $this->quantity = 1;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getQtyOrdered()
    {
        return $this->quantity;
    }

    public function getName()
    {
        return $this->name;
    }
}