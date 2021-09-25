<?php

namespace ResursBank\Module;

use ResursBank\Gateway\ResursDefault;

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
        add_filter('resursbank_temporary_disable_checkout', [$this, 'setRcoDisabledWarning'], 99999, 1);
    }

    /**
     * @param $filterIsActive
     */
    public function setRcoDisabledWarning($filterIsActive)
    {
        if ($filterIsActive) {
            Data::setLogInternal(
                Data::LOG_WARNING,
                sprintf(
                    __(
                        'The filter "%s" is currently put in an active state by an unknown third party plugin. This ' .
                        'setting is deprecated and no longer fully supported. It is highly recommended to disable ' .
                        'or remove the filter entirely and solve the problem that required this from start somehow ' .
                        'else.',
                        'trbwc'
                    ),
                    'resursbank_temporary_disable_checkout'
                )
            );
        }
    }

    /**
     * @param $scriptList
     * @return mixed
     * @since 0.0.1.0
     */
    public function getRcoLoaderScripts($scriptList)
    {
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $scriptList['resursbank_rco_legacy'] = 'resurscheckoutjs/resurscheckout.js';
        }

        return $scriptList;
    }
}
