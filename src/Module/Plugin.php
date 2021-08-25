<?php

namespace ResursBank\Module;

use ResursBank\Gateway\ResursDefault;
use WC_Payment_Gateway;

/**
 * Class Plugin Internal plugin handler.
 *
 * @package ResursBank\Module
 */
class Plugin
{
    public function __construct()
    {
        add_filter('rbwc_can_process_order', [$this, 'canProcessOrder']);
        add_filter('rbwc_js_loaders_checkout', [$this, 'getRcoLoaderScripts']);
        add_filter('rbwc_can_process_order_response', [$this, 'canProcessOrderResponse'], 10, 2);
    }

    /**
     * @param $scriptList
     * @return mixed
     * @since 0.0.1.0
     */
    public function getRcoLoaderScripts($scriptList)
    {
        if (Data::getCheckoutType()===ResursDefault::TYPE_RCO) {
            $scriptList['resursbank_rco_legacy'] = 'resurscheckoutjs/resurscheckout.js';
        }

        return $scriptList;
    }

    /**
     * Ruleset for internal processing, when passing through RCO.
     * @param $return
     * @return false|mixed
     * @since 0.0.1.0
     */
    public function canProcessOrder($return)
    {
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $return = false;
        }
        return $return;
    }

    /**
     * In RCO, orders are already processing during payment processing. This assures us that we get the
     * correct return response in that process, or the orders will fail otherwise.
     * @param $return
     * @param $order
     * @return mixed
     * @since 0.0.1.0
     */
    public function canProcessOrderResponse($return, $order)
    {
        $returnUrl = WC_Payment_Gateway::get_return_url($order);
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $return['result'] = 'success';
            $return['redirect'] = $returnUrl;
        }
        return $return;
    }
}
