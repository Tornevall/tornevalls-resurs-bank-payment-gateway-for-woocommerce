<?php

namespace ResursBank\Gateway;

use Exception;
use ResursBank\Helper\WordPress;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use RuntimeException;
use TorneLIB\Utils\Generic;
use WC_Payment_Gateway;

/**
 * Class ResursDefault
 * @package Resursbank\Gateway
 */
class ResursDefault extends WC_Payment_Gateway
{
    /**
     * @var array $applicantPostData Applicant request.
     * @since 0.0.1.0
     */
    private $applicantPostData = [];

    /**
     * @var WC_Cart $cart
     */
    private $cart;

    /**
     * @var array $methodInformation
     * @since 0.0.1.0
     */
    private $methodInformation;

    /**
     * @var Generic $generic Generic library, mainly used for automatically handling templates.
     * @since 0.0.1.0
     */
    private $generic;

    /**
     * ResursDefault constructor.
     * @param array $getPaymentMethodObject
     */
    public function __construct($getPaymentMethodObject = [])
    {
        global $woocommerce;
        $this->cart = isset($woocommerce->cart) ? $woocommerce->cart : null;
        $this->generic = Data::getGenericClass();
        $this->id = Data::getPrefix('default');
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        $this->setPaymentMethodInformation($getPaymentMethodObject);
        $this->has_fields = Data::getResursOption('checkout_type') === 'simplified' ? true : false;
        $this->setFilters();
    }

    /**
     * @param $paymentMethodInformation
     * @since 0.0.1.0
     */
    private function setPaymentMethodInformation($paymentMethodInformation)
    {
        if (is_object($paymentMethodInformation)) {
            $this->methodInformation = $paymentMethodInformation;
            $this->id = sprintf('%s_%s', Data::getPrefix(), $this->methodInformation->id);
            $this->title = $this->methodInformation->description;
            $this->method_description = '';
            if (Data::getResursOption('payment_method_icons') === 'woocommerce_icon') {
                $this->icon = Data::getImage('resurs-logo.png');
            }

            // Applicant post data should be the final request.
            $this->applicantPostData = $this->getApplicantPostData();
        }
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    private function getApplicantPostData()
    {
        $realMethodId = $this->getRealMethodId();
        $return = [];
        // Skip the scraping if this is not a payment.
        if ($this->isPaymentReady()) {
            foreach ($_REQUEST as $requestKey => $requestValue) {
                if (preg_match(sprintf('/%s$/', $realMethodId), $requestKey)) {
                    $applicantDataKey = (string)preg_replace(
                        sprintf(
                            '/%s_(.*?)_%s/',
                            Data::getPrefix(),
                            $realMethodId
                        ),
                        '$1',
                        $requestKey
                    );
                    $return[$applicantDataKey] = $requestValue;
                }
            }
        }

        return $return;
    }

    /**
     * @return string|string[]|null
     * @since 0.0.1.0
     */
    private function getRealMethodId()
    {
        return preg_replace('/^trbwc_/', '', $this->id);
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    private function isPaymentReady()
    {
        return (isset($_REQUEST['payment_method']) &&
            isset($_REQUEST['wc-ajax']) &&
            $_REQUEST['wc-ajax'] === 'checkout');
    }

    /**
     * @since 0.0.1.0
     */
    private function setFilters()
    {
        add_filter('woocommerce_order_button_html', [$this, 'getOrderButtonHtml']);
        add_filter('woocommerce_checkout_fields', [$this, 'getCheckoutFields']);
        add_filter('wc_get_price_decimals', 'ResursBank\Module\Data::getDecimalValue');
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public function is_available()
    {
        $return = parent::is_available();

        $minMax = Api::getResurs()->getMinMax(
            $this->get_order_total(),
            $this->methodInformation->minLimit,
            $this->methodInformation->maxLimit
        );
        if (!$minMax) {
            $return = false;
        }

        return $return;
    }

    /**
     * How to handle the submit order button. For future RCO.
     *
     * @param $classButtonHtml
     * @return mixed
     */
    public function getOrderButtonHtml($classButtonHtml)
    {
        return $classButtonHtml;
    }

    /**
     * How to handle checkout fields. For future RCO.
     *
     * @param $fields
     * @return mixed
     */
    public function getCheckoutFields($fields)
    {
        return $fields;
    }

    /**
     * @since 0.0.1.0
     */
    public function admin_options()
    {
        $_REQUEST['tab'] = Data::getPrefix('admin');
        $url = admin_url('admin.php');
        $url = add_query_arg('page', $_REQUEST['page'], $url);
        $url = add_query_arg('tab', $_REQUEST['tab'], $url);
        wp_safe_redirect($url);
        die('Deprecated space');
    }

    /**
     * @since 0.0.1.0
     */
    public function payment_fields()
    {
        $fieldHtml = null;
        // If not here, no fields are required.
        $requiredFields = (array)FormFields::getSpecificTypeFields($this->methodInformation->type);

        if (Data::getResursOption('checkout_type') === 'rco') {
            // TODO: No fields should be active on RCO. Make sure we never land here at all.
            return;
        }

        if (count($requiredFields)) {
            foreach ($requiredFields as $fieldName) {
                $fieldHtml .= $this->generic->getTemplate('checkout_paymentfield.phtml', [
                    'displayMode' => $this->getDisplayableField($fieldName) ? '' : 'none',
                    'methodId' => $this->methodInformation->id,
                    'fieldSize' => WordPress::applyFilters('getPaymentFieldSize', 24, $fieldName),
                    'streamLine' => Data::getResursOption('streamline_payment_fields'),
                    'fieldLabel' => FormFields::getFieldString($fieldName),
                    'fieldName' => sprintf('%s_%s_%s', Data::getPrefix(), $fieldName, $this->methodInformation->id),
                ]);
            }

            echo $fieldHtml;
        }
    }

    /**
     * @param $fieldName
     * @return bool
     * @since 0.0.1.0
     */
    private function getDisplayableField($fieldName)
    {
        return !(Data::getResursOption('streamline_payment_fields') ||
            !FormFields::canDisplayField($fieldName));
    }

    /**
     * @param int $order_id
     * @return array|void
     * @throws Exception
     * @since 0.0.1.0
     */
    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);
        $return = [
            'result' => 'failure',
            'redirect' => $this->getReturnUrl('failure', $order),
        ];

        // Developers can put their last veto here.
        WordPress::doAction('canProcessOrder', $order);
        $return = $this->processResursOrder($order, $return);

        return $return;
    }

    /**
     * @param string $result
     * @param $order
     * @return string
     * @since 0.0.1.0
     */
    private function getReturnUrl($result = 'failure', $order)
    {
        return $result === 'success' ?
            $this->get_return_url($order) : html_entity_decode($order->get_cancel_order_url());;
    }

    /**
     * @param $order
     * @param array $return
     * @return array
     * @throws Exception
     */
    private function processResursOrder($order, $return)
    {
        if (method_exists($this, sprintf('process%s', Data::getResursOption('checkout_type')))) {
            // Automatically process orders with a checkout type that is supported by this plugin.
            // Checkout types will become available as the method starts to exist.
            $return = $this->{sprintf('process%s', Data::getResursOption('checkout_type'))}($order, $return);
        } else {
            throw new RuntimeException(
                __(
                    'Chosen checkout type is currently unsupported'
                ),
                404
            );
        }

        return $return;
    }

    private function processSimplified($order, $return)
    {
        $flopp = $this->applicantPostData;

        return $return;
    }
}
