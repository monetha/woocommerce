<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-27
 * Time: 10:47
 */

namespace Monetha\WooCommerce\Adapter;


use Monetha\Adapter\ConfigAdapterInterface;
use Monetha\ConfigAdapterTrait;

class ConfigAdapter implements ConfigAdapterInterface
{
    use ConfigAdapterTrait;

    public function __construct(array $settings)
    {
        if ($settings['woocommerce_monetha_testMode'] == 'no') {
            $this->testMode = '0';
        } else {
            $this->testMode = $settings['woocommerce_monetha_testMode'] ? '1' : '0';
        }

        $this->merchantSecret = $settings['woocommerce_monetha_merchantSecret'];
        $this->monethaApiKey = $settings['woocommerce_monetha_mthApiKey'];
    }
}