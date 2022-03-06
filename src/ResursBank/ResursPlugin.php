<?php

namespace ResursBank\ResursBank;

use ResursBank\Module\Data;

/**
 * Suggested class to handle simpler actions from, if something has to be changed.
 *
 * Initially, this space is reserved for Resurs Bank. This is where we want to add specialities if we can avoid
 * editing the codebase.
 *
 * @see https://docs.tornevall.net/x/iQAdBg
 * @since 0.0.1.6
 */
class ResursPlugin
{
    /**
     * Prefix to use when overriding internal prefixes
     *
     * @var string
     * @since 0.0.1.6
     */
    const RESURS_BANK_PREFIX = 'resursbank';

    /**
     * Set this to true, to make a permanent for the official Resurs Bank release.
     *
     * This will also remove the config setting from the form fields array. Make sure
     * this feature passed through the slug detection.
     *
     * @var bool
     * @see https://tracker.tornevall.net/browse/RWC-336
     * @since 0.0.1.6
     */
    private $forcePaymentMethodsToFirstTab = false;

    /**
     * @since 0.0.1.6
     */
    public function __construct()
    {
        $this->getFilters();
    }

    /**
     * Initialize filters that should be hooked from the main codebase.
     * @since 0.0.1.6
     */
    private function getFilters()
    {
        add_filter('rbwc_payment_methods_on_first_page', [$this, 'paymentMethodsOnFirstPage']);
        add_filter('rbwc_get_custom_form_fields', [$this, 'getCustomFormFields']);
        add_filter('rbwc_get_plugin_prefix', [$this, 'getPluginPrefix']);
    }

    /**
     * @param $currentPrefix
     * @return string
     * @since 0.0.1.6
     */
    public function getPluginPrefix($currentPrefix)
    {
        return $this->canUseFeature() ? self::RESURS_BANK_PREFIX : $currentPrefix;
    }

    /**
     * Make sure we are not breaking original code base on enforced features.
     *
     * @return bool
     * @since 0.0.1.6
     */
    private function canUseFeature(): bool
    {
        return !Data::isOriginalCodeBase();
    }

    /**
     * @param $formFieldArray
     * @return mixed
     * @since 0.0.1.6
     */
    public function getCustomFormFields($formFieldArray)
    {
        // Only allow moving of payment methods to first page by force if the codebase is ours.
        if ($this->canUseFeature() && $this->isEnforcedPaymentMethodsTab()) {
            unset($formFieldArray['advanced']['payment_methods_on_first_page']);
        }

        return $formFieldArray;
    }

    /**
     * @return bool
     * @since 0.0.1.6
     */
    private function isEnforcedPaymentMethodsTab(): bool
    {
        return $this->canUseFeature() ? $this->forcePaymentMethodsToFirstTab : false;
    }

    /**
     * @param bool $isTrue
     * @return bool
     * @since 0.0.1.6
     */
    public function paymentMethodsOnFirstPage(bool $isTrue): bool
    {
        if (!$isTrue && $this->isEnforcedPaymentMethodsTab()) {
            $isTrue = true;
        }

        return $isTrue;
    }
}