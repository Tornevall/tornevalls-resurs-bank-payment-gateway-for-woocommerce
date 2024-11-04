<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;

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
     */
    public static function getDefault(): ?string
    {
        return '';
    }
}
