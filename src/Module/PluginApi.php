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
     * @var ResursDefault
     * @since 0.0.1.0
     */
    private static $resursCheckout;

    /**
     * List of callbacks required for this plugin to handle payments properly.
     * @var array
     * @since 0.0.1.0
     */
    private static $callbacks = [
        Callback::UNFREEZE,
        Callback::TEST,
        Callback::UPDATE,
        Callback::BOOKED,
    ];

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

    /**
     * @param null $expire
     * @param null $noReply Boolean that returns the answer instead of replying live.
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getValidatedNonce(
        $expire = null,
        $noReply = null,
        $fromFunction = null
    ): bool {
        $return = false;
        $expired = false;
        $preExpired = self::expireNonce(__FUNCTION__);
        $defaultNonceError = 'nonce_validation';

        $isNotSafe = [
        ];

        $isSafe = (is_admin() && is_ajax() && empty($fromFunction) && in_array($fromFunction, $isNotSafe, true));
        $nonceArray = [
            'admin',
            'all',
            'simple',
        ];

        // Not recommended as this expires immediately and stays expired.
        if ((bool)$expire && $preExpired) {
            $expired = $preExpired;
            $defaultNonceError = 'nonce_expire';
        }

        foreach ($nonceArray as $nonceType) {
            if (wp_verify_nonce(Url::getRequest('n'), WordPress::getNonceTag($nonceType))) {
                $return = true;
                break;
            }
        }

        if (!$return && $isSafe && (bool)Data::getResursOption('nonce_trust_admin_session')) {
            // If request is based on ajax and admin.
            $return = true;
            $expired = false;
        }

        // We do not ask if this can be logged before it is logged, so that we can backtrack
        // errors without the permission from wp-admin.
        Data::writeLogInfo(
            sprintf(
                __(
                    'Nonce validation accepted (from function: %s): %s.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $fromFunction,
                $return ? 'true' : 'false'
            )
        );

        if (!$return || $expired) {
            if (!(bool)$noReply) {
                self::reply(
                    [
                        'error' => $defaultNonceError,
                    ]
                );
            }
        }

        return $return;
    }

    /**
     * Make sure the used nonce can only be used once.
     *
     * @param $nonceTag
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function expireNonce($nonceTag): bool
    {
        $optionTag = 'resurs_nonce_' . $nonceTag;
        $return = false;
        $lastNonce = get_option($optionTag);
        if (Url::getRequest('n') === $lastNonce) {
            $return = true;
        } else {
            // Only update if different.
            update_option($optionTag, Url::getRequest('n'));
        }
        return $return;
    }

    /**
     * @param bool $reply
     * @param bool $validate
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection BadExceptionsProcessingInspection
     */
    public static function getPaymentMethods($reply = true, $validate = true)
    {
        if ($validate) {
            self::getValidatedNonce();
        }
        $e = null;

        // Re-fetch payment methods.
        try {
            ResursBankAPI::getPaymentMethods(false);
            // @todo Can't use old SOAP annuities, so we disable this one during the initial migration!
            //ResursBankAPI::getAnnuityFactors(false);
            $canReload = true;
        } catch (Exception $e) {
            Data::writeLogException($e, __FUNCTION__);
            $canReload = false;
        }
        if (self::canReply($reply)) {
            self::reply([
                'reload' => $canReload,
                'error' => $e instanceof Exception ? $e->getMessage() : '',
                'code' => $e instanceof Exception ? $e->getCode() : 0,
            ]);
        }
    }

    /**
     * @param $reply
     * @return bool
     * @since 0.0.1.0
     */
    private static function canReply($reply): bool
    {
        return $reply === null || (bool)$reply === true;
    }
}
