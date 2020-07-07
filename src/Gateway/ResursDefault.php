<?php

namespace ResursBank\Gateway;

use ResursBank\Module\Data;
use TorneLIB\Utils\Generic;

/**
 * Class ResursDefault
 * @package Resursbank\Gateway
 */
class ResursDefault extends \WC_Payment_Gateway
{
    /**
     * @var Generic $generic Generic library, mainly used for automatically handling templates.
     * @since 0.0.1.0
     */
    private $generic;

    public function __construct()
    {
        $this->id = Data::getPrefix('default');
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        //$this->has_fields = true;
        //$this->icon = Data::getImage('logo2018.png');
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
}
