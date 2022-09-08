<?php

use ResursBank\Module\Data;

if (!function_exists('rbwc_get_option')) {
    function rbwc_get_option(
        string $key,
        string $namespace = '',
        bool $getDefaults = true
    ) {
        return Data::getResursOption(
            $key,
            $namespace,
            $getDefaults
        );
    }
}

if (!function_exists('rbwc_get_prefix')) {
    function rbwc_get_prefix($extra = null, $ignoreCodeBase = null)
    {
        return Data::getPrefix($extra, $ignoreCodeBase);
    }
}

if (!function_exists('rbwc_get_truth')) {
    function rbwc_get_truth($value) {
        return Data::getTruth($value);
    }
}
