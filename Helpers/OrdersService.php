<?php

require_once dirname(__FILE__) . '/../Consts/Consts.php';
require_once dirname(__FILE__) . '/HttpService.php';

class OrdersService
{
    public static function prepareOfferBody($orderId, $apiUrl, $apiKey)
    {
        global $woocommerce;
        $order = new WC_Order($orderId);
        $items = array();

        // collect line items
        foreach ($order->get_items() as $item) {
            $li = array(
                    'name'			=> $item->get_name(),
                    'quantity'		=> $item->get_quantity(),
                    'amount_fiat'	=> wc_format_decimal($item->get_total()/$item->get_quantity(), 2)
                );

            if($li['amount_fiat'] > 0)
            {
                $items[] = $li;
            }
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

        // define reusable variables
        $client_id              = 0;
        $contact_name           = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $contact_email          = $order->get_billing_email();
        $contact_phone_number   = $order->get_billing_phone();
        $country_code           = $order->get_billing_country();
        $postal_code            = $order->get_billing_postcode();
        $address                = $order->get_billing_address_1();
        $city                   = $order->get_billing_city();

        if($contact_phone_number) {
            $client_body = array(
                "contact_name" => $contact_name,
                "contact_email" => $contact_email,
                "contact_phone_number" => $contact_phone_number,
                "country_code_iso" => $country_code,
                "address" => $address,
                "city" => $city,
                "zipcode" => $postal_code
            );

            $clientResponse = HttpService::callApi($apiUrl . 'v1/clients', 'POST', $client_body, ["Authorization: Bearer " . $apiKey]);

            if(isset($clientResponse->client_id)) {
                $client_id = $clientResponse->client_id;
            }
        }

        $req = array(
                'deal' => array(
                    'amount_fiat' => $order->get_total(),
                    'currency_fiat' => get_woocommerce_currency(),
                    'line_items' => $items,
                    'client_id' => $client_id,
                    'client' => array(
                        'contact_name' => $contact_name,
                        'contact_email' => $contact_email,
                        'contact_phone_number' => $contact_phone_number
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

    public static function setOrderPaidByCard($order)
    {
        $order->update_status('processing', 'Order has been successfully paid by your card.', true);
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
