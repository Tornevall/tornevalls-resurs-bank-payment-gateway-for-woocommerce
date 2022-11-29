<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\Customer\Http;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\GetAddressException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Http\Controller;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Models\GetAddressRequest;
use Resursbank\Ecom\Module\Customer\Repository;

/**
 * Base controller class to handle operations associated with address fetching.
 *
 * NOTE: Since this library can/should not be directly exposed the intention
 * is you extend this class from your implementation and use that as the
 * endpoint for the Get Address Widget and similar integrations.
 */
class GetAddressController extends Controller
{
	/**
	 * NOTE: $sessionHandler to support testing with mocked session handler.
	 *
	 * @param string $storeId
	 * @param GetAddressRequest $data
	 * @param Session $sessionHandler
	 *
	 * @return string
	 * @throws ConfigException
	 * @throws JsonException
	 * @throws ReflectionException
	 * @throws ApiException
	 * @throws AuthException
	 * @throws CurlException
	 * @throws GetAddressException
	 * @throws ValidationException
	 * @throws EmptyValueException
	 * @throws IllegalTypeException
	 */
    public function exec(
        string $storeId,
        GetAddressRequest $data,
        Session $sessionHandler = new Session()
    ): string {
        // Store supplied government id in session.
        Repository::setSsnData(data: $data, sessionHandler: $sessionHandler);

        // Fetch address.
        $address = Repository::getAddress(
            storeId: $storeId,
            governmentId: $data->govId,
            customerType: $data->customerType
        );

        return $this->respond(data: $address->toArray());
    }

    /**
     * @return GetAddressRequest
     * @throws HttpException
     */
    public function getRequestData(): GetAddressRequest
    {
        $result = $this->getRequestModel(
            model: GetAddressRequest::class
        );

        if (!$result instanceof GetAddressRequest) {
            throw new HttpException(
                message: $this->translateError(phraseId: 'invalid-post-data'),
                code: 415
            );
        }

        return $result;
    }
}
