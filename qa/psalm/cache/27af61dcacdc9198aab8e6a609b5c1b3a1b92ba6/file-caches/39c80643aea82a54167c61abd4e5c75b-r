<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Customer\Widget;

use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Widget\Widget;

/**
 * Read more widget.
 */
class GetAddress extends Widget
{
    /** @var string */
    public readonly string $css;

    /**
     * @var string
     */
    public readonly string $content;

    /**
     * @param string $fetchUrl
     * @param string $govId
     * @param CustomerType $customerType
     * @param string $jsCallback
     * @throws FilesystemException
     */
    public function __construct(
        public string $fetchUrl,
        public string $govId = '',
        public CustomerType $customerType = CustomerType::NATURAL,
        public string $jsCallback = 'rbHandleFetchAddressResponse',
    ) {
        $this->content = $this->render(file: __DIR__ . '/get-address.phtml');
        $this->css = $this->render(file: __DIR__ . '/get-address.css');
    }
}
