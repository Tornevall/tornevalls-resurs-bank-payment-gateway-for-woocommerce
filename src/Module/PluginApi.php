<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Helper\WordPress;
use TorneLIB\IO\Data\Strings;

/**
 * Class PluginApi
 * @package ResursBank\Module
 */
class PluginApi
{
    /**
     * @since 0.0.1.0
     */
    public static function execApi()
    {
        WordPress::doAction(self::getAction(), null);
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private static function getAction()
    {
        $action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

        return (new Strings())->getCamelCase(self::getTrimmedActionString($action));
    }

    /**
     * @param $action
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTrimmedActionString($action)
    {
        $action = ltrim($action, 'resursbank_');

        return $action;
    }

    /**
     * @since 0.0.1.0
     */
    public static function execApiNoPriv()
    {
        WordPress::doAction(self::getAction(), null);
    }

    /**
     * @since 0.0.1.0
     */
    public static function importCredentials()
    {
        update_option('resursImportCredentials', time());
        self::getValidatedNonce();
        self::reply([
            'login' => Data::getResursOptionDeprecated('login'),
            'pass' => Data::getResursOptionDeprecated('password'),
        ]);
    }

    /**
     * @param bool $expire
     * @return bool
     * @since 0.0.1.0
     */
    private static function getValidatedNonce($expire = null)
    {
        $return = false;
        $expired = false;
        $defaultNonceError = 'nonce_validation';

        $nonceArray = [
            'admin',
            'all',
            'simple',
        ];

        // Not recommended as this expires immediately and stays expired.
        if ((bool)$expire && ($expired = self::expireNonce(__FUNCTION__))) {
            $defaultNonceError = 'nonce_expire';
        }

        foreach ($nonceArray as $nonceType) {
            if (wp_verify_nonce(self::getParam('n'), WordPress::getNonceTag($nonceType))) {
                $return = true;
                break;
            }
        }

        if (!$return || $expired) {
            self::reply(
                [
                    'error' => $defaultNonceError,
                ]
            );
        }

        return $return;
    }

    /**
     * Make sure the used nonce can only be used once.
     * @param $nonceTag
     * @return bool
     * @since 0.0.1.0
     */
    public static function expireNonce($nonceTag)
    {
        $optionTag = 'resurs_nonce_' . $nonceTag;
        $return = false;
        $lastNonce = get_option($optionTag);
        if (self::getParam('n') === $lastNonce) {
            $return = true;
        } else {
            // Only update if different.
            update_option($optionTag, self::getParam('n'));
        }
        return $return;
    }

    /**
     * @param $key
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getParam($key)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
    }

    /**
     * @param $out
     * @since 0.0.1.0
     */
    private static function reply($out = null)
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

        header('Content-type: application/json; charset=utf-8', true, 200);
        echo json_encode($out);
        die();
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testCredentials()
    {
        self::getValidatedNonce();

        $validationResponse = (new Api())->getConnection()->validateCredentials(
            (self::getParam('e') !== 'live'),
            self::getParam('u'),
            self::getParam('p')
        );

        self::reply([
            'validation' => $validationResponse,
        ]);
    }
}
