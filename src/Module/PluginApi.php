<?php

/** @noinspection CompactCanBeUsedInspection */

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Module\Customer\Enum\CustomerType;
use Resursbank\Ecommerce\Types\Callback;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Modules\Gateway\ResursDefault;
use Resursbank\Woocommerce\Util\Url;
use function in_array;

/**
 * Backend API Handler.
 *
 * @package ResursBank\Module
 */
class PluginApi
{
    /**
     * @since 0.0.1.0
     */
    public static function execApi()
    {
        // Making sure ecom2 is preconfigured during ajax-calls too.
        new ResursBankAPI();
        // Logging $_REQUEST may break the WooCommerce status log view if not decoded first.
        // For some reason, the logs can't handle utf8-strings.
        Data::writeLogEvent(
            Data::CAN_LOG_BACKEND,
            sprintf(
                'Backend: %s (%s), params %s',
                __FUNCTION__,
                self::getActionFromRequest(),
                print_r(Data::getObfuscatedData($_REQUEST), true)
            )
        );

        $returnedValue = WordPress::applyFilters(self::getActionFromRequest(), null, $_REQUEST);

        if (!empty($returnedValue)) {
            self::reply($returnedValue);
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    private static function getActionFromRequest(): string
    {
        return WordPress::getCamelCase(self::getTrimmedActionString(Url::getRequest('action')));
    }

    /**
     * @param $action
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTrimmedActionString($action)
    {
        $action = preg_replace('/^resursbank_/i', '', $action);

        return $action;
    }

    /**
     * @param null $out
     * @param bool $dieInstantly Set to exit after reply if true.
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function reply($out = null, $dieInstantly = true)
    {
        $success = true;

        if (!isset($out['error'])) {
            $out['error'] = null;
        }
        if (!isset($out['ajax_success'])) {
            if (!empty($out['error'])) {
                $success = false;
            }
            $out['ajax_success'] = $success;
        }

        $out['action'] = self::getActionFromRequest();

        header('Content-type: application/json; charset=utf-8', true, 200);
        // Can not sanitize output as the browser is strictly typed to specific content.
        echo json_encode($out);
        if ($dieInstantly) {
            exit;
        }
    }
}
