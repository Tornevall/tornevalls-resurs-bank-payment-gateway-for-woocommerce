<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\UserSettings;

use Resursbank\Ecom\Lib\Api\Environment;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Lib\UserSettings\ReaderInterface;
use Resursbank\Ecom\Module\UserSettings\Repository;
use ValueError;

class Reader implements ReaderInterface
{
    /**
     * Name prefix for entries in options table.
     *
     * @noinspection PhpMissingClassConstantTypeInspection
     */
    public const NAME_PREFIX = 'resursbank_';

    /**
     * @param Field $field
     * @return string|null
     */
    public function read(Field $field): ?string
    {
        return get_option(self::getOptionName(field: $field), null);
    }

    /**
     * Method to resolve default logs directory. This is utilized by Ecom's
     * config reader in case no value is stored in the integration persistence
     * layer (the database most likely).
     *
     * NOTE: This method claims to be unused since it's called dynamically by
     * the USerSettings repository.
     *
     * @noinspection PhpUnused
     * @todo The logic around checking if the dir exists could actually be moved to Ecom. This is a little flawed as it is now, if the dir is not there then no default value at all will be listed and logs will never work. It's better we create our own log dir within the uploads dir, which should be possible since if that doesn't have write permissions we would not be able to create a log anyways, and PHP would not be able to write any uploads to it, so it's safe to assume that's not going to be an issue. Then, we let Ecom create the log dir path if it's missing, and if that fails, it will just throw an error that we can handle as we please in the integration. Should probably throw a specific error then, so we can specifically catch and handle it, with like a message in backend and nothing in frontend. We probably do not want the integration to break because of this though, it needs considering a bit, throwing might be bad.
     */
    public static function getDefaultLogDir(): string
    {
        $result = '';
        $ulDir = wp_upload_dir(create_dir: false);

        if (
            is_array(value: $ulDir) &&
            isset($ulDir['basedir']) &&
            is_string(value: $ulDir['basedir']) &&
            $ulDir['basedir'] !== '' &&
            is_dir(filename: $ulDir['basedir'])
        ) {
            $dir = $ulDir['basedir'] . '/wc-logs';

            if (is_dir(filename: $dir) && is_writable(filename: $dir)) {
                $result = $dir;
            }
        }

        return $result;
    }



    /**
     * Converter from Field enum to option name in WP options table.
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public static function getOptionName(Field $field): string
    {
        return self::NAME_PREFIX . match (
            $field
        ) {
            Field::ENABLED => 'enabled',
            Field::CLIENT_ID_PROD => 'client_id',
            Field::CLIENT_SECRET_PROD => 'client_secret',
            Field::CLIENT_ID_TEST => 'client_id',
            Field::CLIENT_SECRET_TEST => 'client_secret',
            Field::STORE_ID => 'store_id',
            Field::ENVIRONMENT => 'environment',
            Field::API_TIMEOUT => 'api_timeout',
            Field::LOG_ENABLED => 'log_enabled',
            Field::LOG_LEVEL => 'log_level',
            Field::PART_PAYMENT_METHOD => '',
            Field::PART_PAYMENT_THRESHOLD => 'part_payment_limit',
            Field::PART_PAYMENT_PERIOD => '',
            Field::PART_PAYMENT_LEGACY_LINKS => '',
            Field::PART_PAYMENT_SHOW_COST_EXAMPLE => '',
            Field::ENABLE_GET_ADDRESS => 'get_address_enabled',
            Field::CAPTURE_ENABLED => 'enable_capture',
            Field::REFUND_ENABLED => 'enable_refund',
            Field::CANCEL_ENABLED => 'enable_cancel',
            Field::MODIFY_ENABLED => 'enable_modify',
            Field::PAYMENT_HISTORY_ENABLED => '',
            Field::PART_PAYMENT_ENABLED => 'part_payment_enabled',
            Field::DEVELOPER_MODE => '',
            Field::HANDLE_FROZEN_PAYMENTS => '',
            Field::HANDLE_MANUAL_INSPECTION => '',
            Field::XDEBUG_SESSION_VALUE => 'xdebug_session_value',
            Field::TEST_TRIGGERED_AT => '',
            Field::TEST_RECEIVED_AT => 'callback_test_received_at',
            Field::SWISH_MAX_LIMIT => '',
            Field::CACHE_ENABLED => 'cache_enabled',
            Field::LOG_DIR => 'log_dir',
            default => ''
        };
    }
}
