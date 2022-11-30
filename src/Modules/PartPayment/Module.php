<?php

namespace Resursbank\Woocommerce\Modules\PartPayment;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use ResursBank\Module\Data;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PartPayment;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;

/**
 * Part payment widget
 */
class Module {
    private PartPayment $instance;

    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws \Resursbank\Ecom\Exception\CacheException
     * @throws \Resursbank\Ecom\Exception\ConfigException
     * @throws \Resursbank\Ecom\Exception\CurlException
     * @throws \Resursbank\Ecom\Exception\FilesystemException
     * @throws \Resursbank\Ecom\Exception\TranslationException
     * @throws \Resursbank\Ecom\Exception\ValidationException
     * @throws \Resursbank\Ecom\Exception\Validation\EmptyValueException
     * @throws \Resursbank\Ecom\Exception\Validation\IllegalTypeException
     * @throws \Resursbank\Ecom\Exception\Validation\IllegalValueException
     */
    public function __construct() {
        global $product;

        $this->instance = new PartPayment(
            storeId: StoreId::getData(),
            paymentMethod: Repository::getById(
                storeId: StoreId::getData(),
                paymentMethodId: PaymentMethod::getData()
            ),
            months: Period::getData(),
            amount: $product->get_price(),
            apiUrl: Route::getUrl(route: Route::ROUTE_PART_PAYMENT)
        );
    }

    /**
     * Output widget HTML if on single product page
     *
     * @return void
     */
    public static function getWidget(): void {
        if (is_product() && Enabled::isEnabled()) {
            try {
                $widget = new self();
                echo Data::getEscapedHtml($widget->instance->content);
            } catch (Exception $exception) {
            }
        }
    }

    /**
     * Output widget CSS if on single product page
     *
     * @return void
     */
    public static function setCss(): void {
        if (is_product() && Enabled::isEnabled()) {
            try {
                $widget = new self();
                echo '<style id="rb-pp-styles">' . $widget->instance->css . '</style>';
            } catch (Exception $exception) {
            }
        }
    }

    public static function setJs(): void {
        if (is_product() && Enabled::isEnabled()) {
            try {
                $widget = new self();

                $url = plugin_dir_url(file: RESURSBANK_MODULE_DIR_NAME . '/js/') . 'js/resursbank_partpayment.js';
                wp_enqueue_script(
                    handle: 'partpayment-script',
                    src: $url,
                    deps: ['jquery']
                );
                wp_add_inline_script(
                    handle: 'partpayment-script',
                    data: $widget->instance->js
                );
                add_action(
                    'wp_enqueue_scripts',
                    'partpayment-script'
                );
            } catch (Exception $exception) {
            }
        }
    }
}
