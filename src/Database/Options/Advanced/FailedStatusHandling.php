<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;

/**
 * Implementation of resursbank_failed_task_status_handling value in options table.
 */
class FailedStatusHandling extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'failed_task_status_handling';
    }

    /**
     * @inheritdoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public static function getDefault(): string
    {
        return 'failed';
    }

    /**
     * Retrieve the stored data or fallback to the default.
     */
    public static function getData(): string
    {
        $value = parent::getData();

        return in_array(
            needle: $value,
            haystack: ['failed', 'cancelled']
        )
            ? $value
            : self::getDefault();
    }
}
