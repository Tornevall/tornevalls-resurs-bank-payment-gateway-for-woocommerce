<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Http\Controller as CoreController;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Http\GetStoresController;
use Resursbank\Woocommerce\Modules\Cache\Controller\Admin\Invalidate;
use Resursbank\Woocommerce\Modules\Callback\Controller\Admin\TestTrigger;
use Resursbank\Woocommerce\Modules\Callback\Controller\TestReceived;
use Resursbank\Woocommerce\Modules\CustomerType\Controller\SetCustomerType;
use Resursbank\Woocommerce\Modules\Gateway\GatewayHelper;
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress;
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddressCss;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Controller\Admin\GetOrderContentController;
use Resursbank\Woocommerce\Modules\PartPayment\Controller\PartPayment;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Callback;
use Throwable;
use function is_string;
use function str_contains;
use function strlen;

/**
 * Primitive routing, executing arbitrary code depending on $_GET parameters.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class Route
{
    /**
     * Name of the $_GET parameter containing the routing name, and also the
     * name of the API section utilised by WC:
     */
    public const ROUTE_PARAM = 'resursbank';

    /**
     * Route to get address controller.
     */
    public const ROUTE_GET_ADDRESS = 'get-address';

    /**
     * Route to controller injecting get address css.
     */
    public const ROUTE_GET_ADDRESS_CSS = 'get-address-css';

    /**
     * Route to update current customer type in session.
     */
    public const ROUTE_SET_CUSTOMER_TYPE = 'set-customer-type';

    /**
     * Route to get part payment controller.
     */
    public const ROUTE_PART_PAYMENT = 'part-payment';

    /**
     * Route to get part payment admin controller.
     */
    public const ROUTE_PART_PAYMENT_ADMIN = 'part-payment-admin';

    /**
     * Route to get part payment admin controller.
     */
    public const ROUTE_ADMIN_CACHE_INVALIDATE = 'admin-cache-invalidate';

    /**
     * Route to admin controller which triggers test callback.
     */
    public const ROUTE_ADMIN_TRIGGER_TEST_CALLBACK = 'admin-trigger-test-callback';

    /**
     * Route to controller accepting test callback from Resurs Bank.
     */
    public const ROUTE_TEST_CALLBACK_RECEIVED = 'test-callback-received';

    /**
     * Route to get JSON encoded list of stores (only in admin).
     */
    public const ROUTE_GET_STORES_ADMIN = 'get-stores-admin';

    /**
     * Route to get updated cost list HTML.
     */
    public const ROUTE_COSTLIST = 'get-costlist';

    /**
     * Route to get JSON encoded order view content.
     */
    public const ROUTE_ADMIN_GET_ORDER_CONTENT = 'get-order-content-admin';

    /**
     * @throws ConfigException
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public static function exec(): void
    {
        $route = (
            isset($_GET[self::ROUTE_PARAM]) &&
            is_string(value: $_GET[self::ROUTE_PARAM])
        ) ? $_GET[self::ROUTE_PARAM] : '';

        $userIsAdmin = self::userIsAdmin() || Admin::isAdmin();

        try {
            if (
                in_array(
                    needle: $route,
                    haystack: self::getAdminRoutes(),
                    strict: true
                ) &&
                !$userIsAdmin
            ) {
                self::respondWithError(
                    exception: new HttpException(
                        message: 'Forbidden',
                        code: 403
                    )
                );
            }

            self::route(route: $route);
        } catch (Throwable $exception) {
            self::respondWithError(exception: $exception);
        }
    }

    /**
     * Redirect request to WC Settings configuration tab for our plugin.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function redirectToSettings(
        string $tab = 'api_settings'
    ): void {
        wp_safe_redirect(self::getSettingsUrl(tab: $tab));

        MessageBag::keep();
    }

    /**
     * Get URL to settings page in admin.
     */
    public static function getSettingsUrl(
        string $tab = 'api_settings'
    ): string {
        return admin_url(
            path: 'admin.php?page=wc-settings&tab='
            . RESURSBANK_MODULE_PREFIX
            . "&section=$tab"
        );
    }

    /**
     * Resolve full URL.
     *
     * @throws HttpException|IllegalValueException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function getUrl(
        string $route,
        bool $admin = false
    ): string {
        $url = !$admin ? get_site_url() : get_admin_url();

        if (!is_string(value: $url)) {
            throw new HttpException(
                message: 'A site URL could not be created.'
            );
        }

        // Some sites may not add the trailing slash properly, making urls break with arguments
        // merged into the hostname instead of the uri. This one fixes that problem.
        $url = self::getUrlWithProperTrailingSlash(url: $url);
        $url .= str_contains(haystack: $url, needle: '?') ? '&' : '?';

        return Url::getQueryArg(
            baseUrl: $url,
            arguments: [self::ROUTE_PARAM => $route]
        );
    }

    /**
     * Echo JSON response.
     */
    public static function respond(
        string $body,
        int $code = 200,
        string $contentType = 'application/json'
    ): void {
        status_header(code: $code);
        header(header: 'Content-Type: ' . $contentType);
        header(header: 'Content-Length: ' . strlen(string: $body));

        echo $body;
    }

    /**
     * Method that exits after response instead of proceeding with regular WordPress executions.
     *
     * In some cases, during API responding, WordPress could potentially execute other data that renders
     * more content after the final json responses, and breaks the requests. This happens due to how
     * WP is handling unknown requests and depends on how the site is configured with permalinks and rewrite-urls.
     * For example, when WP handles 404 errors on unknown http-requests, we have to stop our own execution
     * like this.
     *
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @noinspection PhpNoReturnAttributeCanBeAddedInspection
     */
    public static function respondWithExit(
        string $body,
        int $code = 200,
        string $contentType = 'application/json'
    ): void {
        self::respond(body: $body, code: $code, contentType: $contentType);
        exit;
    }

    /**
     * Respond to browser with an error based on Throwable.
     *
     * @throws ConfigException
     */
    public static function respondWithError(
        Throwable $exception
    ): void {
        $controller = new CoreController();

        self::respondWithExit(
            body: $controller->respondWithError(
                exception: $exception
            ),
            code: $controller->getErrorResponseCode(
                exception: $exception
            )
        );
    }

    /**
     * Redirect back and exit.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function redirectBack(
        bool $admin = true
    ): void {
        $url = $_SERVER['HTTP_REFERER'] ?? '';

        try {
            $default = self::getUrl(route: '', admin: $admin);
        } catch (Throwable $error) {
            Log::error(error: $error);
            $default = (string)get_site_url();
        }

        if (
            !is_string(value: $url) ||
            $url === '' ||
            !filter_var(value: $url, filter: FILTER_VALIDATE_URL)
        ) {
            $url = $default;
        }

        header(header: 'Location: ' . $url);
        exit;
    }

    /**
     * Perform actual execution of controller code.
     *
     * @throws HttpException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws Throwable
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function route(string $route): void
    {
        switch ($route) {
            case self::ROUTE_GET_ADDRESS:
                self::respondWithExit(body: GetAddress::exec());
                break;

            case self::ROUTE_GET_ADDRESS_CSS:
                self::respondWithExit(
                    body: GetAddressCss::exec(),
                    contentType: 'text/css'
                );
                break;

            case self::ROUTE_PART_PAYMENT:
                self::respondWithExit(body: PartPayment::exec());
                break;

            case self::ROUTE_GET_STORES_ADMIN:
                self::respondWithExit(body: (new GetStoresController())->exec());
                break;

            case self::ROUTE_SET_CUSTOMER_TYPE:
                self::respondWithExit(body: SetCustomerType::exec());
                exit;

            case self::ROUTE_ADMIN_CACHE_INVALIDATE:
                Invalidate::exec();
                self::redirectToSettings(tab: Advanced::SECTION_ID);
                break;

            case self::ROUTE_ADMIN_TRIGGER_TEST_CALLBACK:
                TestTrigger::exec();
                self::redirectToSettings(tab: Callback::SECTION_ID);
                break;

            case self::ROUTE_TEST_CALLBACK_RECEIVED:
                TestReceived::exec();
                self::respondWithExit(body: '');
                break;

            case self::ROUTE_ADMIN_GET_ORDER_CONTENT:
                // We should not execute this until after post types are registered.
                add_action(
                    'woocommerce_after_register_post_type',
                    static function (): void {
                        Route::respondWithExit(
                            body: GetOrderContentController::exec()
                        );
                    }
                );
                break;

            case self::ROUTE_COSTLIST:
                try {
                    $paymentMethod = Repository::getById(paymentMethodId: $_GET['method'] ?? '');

                    if (!$paymentMethod instanceof PaymentMethod) {
                        self::respondWithExit(
                            body: wp_json_encode(
                                ['html' => '']
                            ),
                            contentType: 'application/json'
                        );
                    }

                    $helper = new GatewayHelper(
                        amount: isset($_GET['amount']) ? (float)$_GET['amount'] : 0,
                        paymentMethod: $paymentMethod
                    );

                    $html = $helper->getCostList();

                    self::respondWithExit(
                        body: wp_json_encode(['html' => $html]),
                        contentType: 'application/json'
                    );
                } catch (Throwable $e) {
                    self::respondWithError(exception: $e);
                } break;

            default:
                break;
        }
    }

    /**
     * Fetches all routes that are only available to admin users.
     */
    private static function getAdminRoutes(): array
    {
        return [
            self::ROUTE_PART_PAYMENT_ADMIN,
            self::ROUTE_ADMIN_CACHE_INVALIDATE,
            self::ROUTE_ADMIN_TRIGGER_TEST_CALLBACK,
            self::ROUTE_GET_STORES_ADMIN,
            self::ROUTE_ADMIN_GET_ORDER_CONTENT,
        ];
    }

    /**
     * Check if user is logged in and has administrator capabilities.
     */
    private static function userIsAdmin(): bool
    {
        return is_user_logged_in() && current_user_can(
                capability: 'administrator'
            );
    }

    /**
     * Fix trailing slashes for urls that is missing them out.
     */
    private static function getUrlWithProperTrailingSlash(string $url): string
    {
        return preg_replace(
                pattern: '/\/$/',
                replacement: '',
                subject: $url
            ) . '/';
    }
}
