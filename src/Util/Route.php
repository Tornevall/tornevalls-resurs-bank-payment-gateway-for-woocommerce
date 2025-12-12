<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Http\Controller as CoreController;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Repository as CallbackRepository;
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Ecom\Module\PaymentMethod\Http\PartPayment\GetDataController;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Http\GetStoresController;
use Resursbank\Ecom\Module\Widget\CacheManagement\Css as CacheManagementCss;
use Resursbank\Ecom\Module\Widget\CacheManagement\Js as CacheManagementJs;
use Resursbank\Ecom\Module\Widget\CallbackList\Css as CallbackListCss;
use Resursbank\Ecom\Module\Widget\CallbackTest\Css;
use Resursbank\Ecom\Module\Widget\CallbackTest\Js as TestCallbackJs;
use Resursbank\Ecom\Module\Widget\GetAddress\Css as Widget;
use Resursbank\Ecom\Module\Widget\GetAddress\Js;
use Resursbank\Ecom\Module\Widget\PaymentMethod\Js as PaymentMethodJs;
use Resursbank\Woocommerce\Modules\Gateway\GatewayHelper;
use Resursbank\Woocommerce\Modules\Order\Controller\Admin\GetOrderContentController;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Ecom\Lib\Log\Logger;
use Throwable;
use function is_string;
use function str_contains;
use function strlen;

/**
 * Primitive routing, executing arbitrary code depending on $_GET parameters.
 */
class Route
{
    /**
     * Name of the $_GET parameter containing the routing name, and also the
     * name of the API section utilised by WC:
     */
    public const ROUTE_PARAM = 'resursbank';

    /**
     * @throws ConfigException
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public static function exec(): void
    {
        $routeString = (
            isset($_GET[self::ROUTE_PARAM]) &&
            is_string(value: $_GET[self::ROUTE_PARAM])
        ) ? $_GET[self::ROUTE_PARAM] : '';

        // This function executes to all requests, but we only wish to process
        // those that are actually meant for our module. If the "resursbank"
        // parameter is not present, we simply return to filter out all others.
        if ($routeString === '') {
            return;
        }

        $userIsAdmin = self::userIsAdmin() || Admin::isAdmin();

        try {
            $route = RouteVariant::from(value: $routeString);

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
     * @throws HttpException
     * @throws IllegalValueException
     * @throws UserSettingsException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function getUrl(
        RouteVariant $route,
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
            arguments: [self::ROUTE_PARAM => $route->value],
            routeVariant: $route
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
            // Use getSettingsUrl for default since getUrl now requires a RouteVariant
            $default = $admin ? self::getSettingsUrl() : (string)get_site_url();
        } catch (Throwable $error) {
            Logger::error(message: $error);
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
     * Generic function to render a JS / CSS widget and respond
     * with the appropriate content type, allowing us to render
     * our JS / CSS widgets as external assets to avoid inline.
     *
     * @param AssetWidget $widget
     * @return void
     */
    public static function renderAssetWidget(AssetWidget $widget): void
    {
        try {
            $body = match ($widget) {
                AssetWidget::GetAddressJs => (new Js(url: Route::getUrl(route: RouteVariant::GetAddress)))->content,
                AssetWidget::GetAddressCss => (new Widget())->content,
                AssetWidget::PaymentMethodJs => (new PaymentMethodJs(
                    paymentMethods: Repository::getPaymentMethods()
                ))->content,
                AssetWidget::AdminCss => (
                    (new Css())->content .
                    (new CallbackListCss())->content .
                    (new CacheManagementCss())->content
                ),
                AssetWidget::AdminJs => (
                    (new TestCallbackJs())->content .
                    (new CacheManagementJs())->content
                )
            };

            if ($body === '') {
                throw new HttpException(
                    message: 'Asset content could not be rendered.',
                    code: 500
                );
            }

            $contentType = match ($widget) {
                AssetWidget::GetAddressJs, AssetWidget::PaymentMethodJs, AssetWidget::AdminJs => 'application/javascript',
                AssetWidget::GetAddressCss, AssetWidget::AdminCss => 'text/css',
            };

            self::respondWithExit(body: $body, contentType: $contentType);
        } catch (Throwable $e) {
            Logger::error(message: $e);
        }
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
    private static function route(RouteVariant $route): void
    {
        match ($route) {
            RouteVariant::GetAddress => self::respondWithExit(body: (new GetAddressController())->exec()),
            RouteVariant::GetAddressCss => self::renderAssetWidget(widget: AssetWidget::GetAddressCss),
            RouteVariant::GetAddressJs => self::renderAssetWidget(widget: AssetWidget::GetAddressJs),
            RouteVariant::PaymentMethodJs => self::renderAssetWidget(widget: AssetWidget::PaymentMethodJs),
            RouteVariant::PartPayment => self::respondWithExit(body: (new GetDataController())->exec()),
            RouteVariant::GetStoresAdmin => self::respondWithExit(body: (new GetStoresController())->exec()),
            RouteVariant::AdminCacheInvalidate => (function () {
                try {
                    Config::getCache()->invalidate();

                    self::respondWithExit(
                        body: json_encode([
                            'success' => true,
                            'message' => Translator::translate(phraseId: 'cache-cleared')
                        ]),
                        contentType: 'application/json'
                    );
                } catch (Throwable $e) {
                    Logger::error(message: $e);

                    self::respondWithExit(
                        body: json_encode([
                            'success' => false,
                            'error' => Translator::translate(phraseId: 'clear-cache-failed')
                        ]),
                        code: 500,
                        contentType: 'application/json'
                    );
                }
            })(),
            RouteVariant::AdminTriggerTestCallback => (function () {
                try {
                    $response = CallbackRepository::triggerTest();
                    self::respondWithExit(
                        body: $response->toJson(),
                        contentType: 'application/json'
                    );
                } catch (Throwable $e) {
                    Logger::error(message: $e);
                    self::respondWithExit(
                        body: json_encode([
                            'status' => 'ERROR',
                            'code' => 500,
                            'success' => false,
                            'error' => $e->getMessage()
                        ]),
                        code: 500,
                        contentType: 'application/json'
                    );
                }
            })(),
            RouteVariant::TestCallbackReceived => (function () {
                $reader = new Reader();
                $reader->update(field: Field::TEST_RECEIVED_AT, value: time());
                self::respondWithExit(body: '');
            })(),
            RouteVariant::GetCallbackTestReceivedAt => (function () {
                try {
                    $reader = new Reader();
                    $timestamp = $reader->read(field: Field::TEST_RECEIVED_AT);
                    
                    self::respondWithExit(
                        body: json_encode(['time' => $timestamp ? (int)$timestamp : null]),
                        contentType: 'application/json'
                    );
                } catch (Throwable $e) {
                    Logger::error(message: $e);
                    self::respondWithExit(
                        body: json_encode(['time' => null, 'error' => $e->getMessage()]),
                        code: 500,
                        contentType: 'application/json'
                    );
                }
            })(),
            RouteVariant::AdminGetOrderContent => add_action(
                'woocommerce_after_register_post_type',
                static function (): void {
                    Route::respondWithExit(
                        body: GetOrderContentController::exec()
                    );
                }
            ),
            RouteVariant::Costlist => (function () {
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
                }
            })(),
            RouteVariant::AuthorizationCallback => add_action(
                'woocommerce_after_register_post_type',
                static function (): void {
                    // NOTE: In admin, we will only sse the log entry from
                    // the callback arriving, and us accepting it. The
                    // event logged when a callback completed, will only
                    // appear if we've supplied a processing closure, which
                    // we don't do here.
                    Route::respondWithExit(
                        body: '',
                        code: CallbackRepository::process(
                            callback: (new AuthorizationController())->getRequestData(),
                            process: null
                        )
                    );
                }
            ),
            RouteVariant::AdminCss => self::renderAssetWidget(widget: AssetWidget::AdminCss),
            RouteVariant::AdminJs => self::renderAssetWidget(widget: AssetWidget::AdminJs),
            default => null,
        };
    }

    /**
     * Fetches all routes that are only available to admin users.
     */
    private static function getAdminRoutes(): array
    {
        return [
            RouteVariant::PartPaymentAdmin,
            RouteVariant::AdminCacheInvalidate,
            RouteVariant::AdminTriggerTestCallback,
            RouteVariant::GetStoresAdmin,
            RouteVariant::AdminGetOrderContent,
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
