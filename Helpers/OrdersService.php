<?php

require_once dirname(__FILE__) . '/../Consts/Consts.php';

class OrdersService
{
    public static function prepareOfferBody($orderId)
    {
        global $woocommerce;
        $order = new WC_Order($orderId);
        $items = array();

        // collect line items
        foreach ($order->get_items() as $item) {
            $items[] = array(
                    'name'			=> $item->get_name(),
                    'quantity'		=> $item->get_quantity(),
                    'amount_fiat'	=> wc_format_decimal($item->get_total()/$item->get_quantity(), 2)
                );
        }

        // collect shipping amount if any
        $shippingCost = $order->get_shipping_total();
        if ($shippingCost > 0) {
            $items[] = array(
                    'name'			=> 'Shipping costs',
                    'quantity'		=> 1,
                    'amount_fiat'	=> wc_format_decimal($shippingCost, 2)
                );
        }

        // collect tax amount if any
        $taxes = $order->get_total_tax();
        if ($taxes > 0) {
            $items[] = array(
                    'name'			=> 'Taxes',
                    'quantity'		=> 1,
                    'amount_fiat'	=> wc_format_decimal($taxes, 2)
                );
        }


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
                'return_url' => $order->get_view_order_url(),
                'callback_url' => trailingslashit(get_bloginfo('wpurl')) . '?rest_route=/v1/monetha/action',
                'external_order_id' => $order->get_id()." ",
            );

        return $req;
    }

    public static function setOrderPaid($order)
    {
        $order->update_status('processing', 'Order has been successfully paid.', true);
    }

    public static function cancelOrder($order, $note)
    {
        if (empty($note)) {
            $note = 'Order has been canceled by Monetha.';
        }

        $order->update_status('cancelled', $note, true);
    }

    public static function getOrder($orderId)
    {
        global $woocommerce;
        $order = new WC_Order($orderId);
        return $order;
    }
}
