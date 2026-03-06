<?php

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Database\Options\Advanced;

use Resursbankabpayments\Woocommerce\Database\DataType\StringOption;
use Resursbankabpayments\Woocommerce\Database\OptionInterface;

/**
 * Xdebug session value option.
 */
class XDebugSessionValue extends StringOption implements OptionInterface
{
    /**
     * Value for enabled xdebug.
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'xdebug_session_value';
    }

    /**
     * @return string|null'
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): ?string
    {
        return '';
    }
}
