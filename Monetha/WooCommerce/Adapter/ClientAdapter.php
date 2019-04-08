<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-27
 * Time: 10:47
 */

namespace Monetha\WooCommerce\Adapter;


use Monetha\Adapter\ClientAdapterInterface;

class ClientAdapter implements ClientAdapterInterface
{
    /**
     * @var \WC_Order
     */
    private $order;

    public function __construct(\WC_Order $order)
    {
        $this->order = $order;
    }

    public function getAddress()
    {
        return $this->order->get_billing_address_1();
    }

    public function getCity()
    {
        return $this->order->get_billing_city();
    }

    public function getContactEmail()
    {
        return $this->order->get_billing_email();
    }

    public function getContactName()
    {
        return $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name();
    }

    public function getContactPhoneNumber()
    {
        return $this->order->get_billing_phone();
    }

    public function getCountryIsoCode()
    {
        return $this->order->get_billing_country();
    }

    public function getZipCode()
    {
        return $this->order->get_billing_postcode();
    }
}