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
        add_filter('rbwc_js_loaders_checkout', [$this, 'getRcoLoaderScripts']);
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
}
