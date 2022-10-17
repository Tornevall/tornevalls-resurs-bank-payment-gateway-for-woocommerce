<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network;

use Exception;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use stdClass;
use CurlHandle;
use JsonException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Network\Model\Response;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Lib\Network\Curl\Header;

use function is_array;
use function is_string;

/**
 * Curl wrapper.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 */
class Curl
{
    /**
     * @var CurlHandle
     */
    public readonly CurlHandle $ch;

    /**
     * @param string $url
     * @param RequestMethod $requestMethod
     * @param array $headers
     * @param array $payload
     * @param ContentType $contentType
     * @param AuthType $authType
     * @param ApiType $apiType
     * @param StringValidation $stringValidation
     * @param ContentType|null $responseContentType
     * @param bool $forceObject Enforces the JSON_FORCE_OBJECT flag on json_encode of payload
     * @throws AuthException
     * @throws CurlException
     * @throws JsonException
     * @throws ValidationException
     * @throws IllegalTypeException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @todo $headers and associated methods should be moved to a collection model / service layer.
     */
    public function __construct(
        string $url,
        public readonly RequestMethod $requestMethod,
        array $headers = [],
        array $payload = [],
        public readonly ContentType $contentType = ContentType::JSON,
        public readonly AuthType $authType = AuthType::JWT,
        public readonly ApiType $apiType = ApiType::MERCHANT,
        private readonly StringValidation $stringValidation = new StringValidation(),
        public ?ContentType $responseContentType = null,
        private readonly bool $forceObject = false
    ) {
        if ($this->responseContentType === null) {
            $this->responseContentType = $this->contentType;
        }

        // Initialize Curl.
        $ch = $this->init(url: $url, headers: $headers, payload: $payload);

        // Setup plaintext auth if requested.
        $this->setAuth(ch: $ch);

        $this->ch = $ch;
    }

    /**
     * @return Response
     * @throws CurlException
     * @throws JsonException
     * @throws IllegalTypeException
     * @throws EmptyValueException
     */
    public function exec(): Response
    {
        /** @noinspection DuplicatedCode */
        $body = curl_exec(handle: $this->ch);

        $this->handleError(body: $body); // We want to check for errors immediately after running curl_exec

        if (!is_string(value: $body)) {
            throw new IllegalTypeException(
                message: 'Curl response type is ' . gettype($body) . ', expected string.'
            );
        }

        $code = (int)curl_getinfo(
            handle: $this->ch,
            option: CURLINFO_RESPONSE_CODE
        );

        if ($this->responseContentType === ContentType::JSON) {
            $this->stringValidation->notEmpty(value: $body);
            /** @psalm-suppress MixedAssignment */
            $body = json_decode(
                json: $body,
                associative: false,
                depth: 768,
                flags: JSON_THROW_ON_ERROR
            );
        } elseif ($this->responseContentType === ContentType::RAW) {
            $bodyObj = new stdClass();
            $bodyObj->message = $body;
            $body = $bodyObj;
        }

        if (!($body instanceof stdClass) && !is_array(value: $body)) {
            throw new IllegalTypeException(
                message: 'Curl response body is not an object or an array.'
            );
        }

        $this->handleError();

        curl_close(handle: $this->ch);

        return new Response(body: $body, code: $code);
    }

    /**
     * Fetch CURLINFO_EFFECTIVE_URL
     *
     * @return string
     */
    public function getEffectiveUrl(): string
    {
        return (string) curl_getinfo(
            handle: $this->ch,
            option: CURLINFO_EFFECTIVE_URL
        );
    }

    /**
     * @param string $url
     * @param array $payload
     * @param AuthType $authType
     * @return Response
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     */
    public static function get(
        string $url,
        array $payload = [],
        AuthType $authType = AuthType::JWT
    ): Response {
        $curl = new self(
            url: $url,
            requestMethod: RequestMethod::GET,
            payload: $payload,
            contentType: ContentType::URL,
            authType: $authType
        );

        return $curl->exec();
    }

    /**
     * @param string $url
     * @param array $payload
     * @param AuthType $authType
     * @return Response
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     */
    public static function post(
        string $url,
        array $payload = [],
        AuthType $authType = AuthType::JWT
    ): Response {
        $curl = new self(
            url: $url,
            requestMethod: RequestMethod::POST,
            payload: $payload,
            authType: $authType
        );

        return $curl->exec();
    }

    /**
     * @param string $url
     * @param AuthType $authType
     * @return Response
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     */
    public static function delete(
        string $url,
        AuthType $authType = AuthType::JWT
    ): Response {
        $curl = new self(
            url: $url,
            requestMethod: RequestMethod::DELETE,
            authType: $authType
        );

        return $curl->exec();
    }

    /**
     * @param string $url
     * @param array $payload
     * @param AuthType $authType
     * @param ContentType $contentType
     * @param ContentType|null $responseContentType
     * @return Response
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ValidationException
     */
    public static function put(
        string $url,
        array $payload = [],
        AuthType $authType = AuthType::JWT,
        ContentType $contentType = ContentType::JSON,
        ?ContentType $responseContentType = null
    ): Response {
        $curl = new self(
            url: $url,
            requestMethod: RequestMethod::PUT,
            payload: $payload,
            contentType: $contentType,
            authType: $authType,
            responseContentType: $responseContentType
        );

        return $curl->exec();
    }

    /**
     * @param string $url
     * @param array $headers
     * @param array $payload
     * @return CurlHandle
     * @throws JsonException
     * @throws ValidationException
     * @throws Exception
     * @todo Check if CURLOPT_ENCODING should be included and what value it should be assigned.
     */
    private function init(
        string $url,
        array $headers,
        array $payload
    ): CurlHandle {
        /** @noinspection DuplicatedCode */
        $ch = curl_init();

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => false, // Don't treat HTTP code 400+ as error.
            CURLOPT_AUTOREFERER => true, // Follow redirects.
            CURLINFO_HEADER_OUT => true, // Track outgoing headers for debugging.
            CURLOPT_HEADER => false, // Do not include header in output.
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => Header::getUserAgent(),
            CURLOPT_HTTPHEADER => Header::getHeadersData(
                headers: Header::generateHeaders(
                    headers: $headers,
                    payloadData: $this->getPayloadData(payload: $payload),
                    contentType: $this->contentType,
                    hasBodyData: $this->hasBodyData()
                )
            ),
            CURLOPT_CUSTOMREQUEST => $this->getCustomRequestValue(),
            CURLOPT_URL => $this->generateUrl(url: $url, payload: $payload),
            CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
        ];
        if (!empty(Config::$instance->proxy)) {
            $options[CURLOPT_PROXY] = Config::$instance->proxy;
            $options[CURLOPT_PROXYTYPE] = Config::$instance->proxyType;
        }
        if (Config::$instance->timeout) {
            $options[CURLOPT_CONNECTTIMEOUT] = ceil(num: Config::$instance->timeout) / 2;
            $options[CURLOPT_TIMEOUT] = ceil(num: Config::$instance->timeout);
        }

        curl_setopt_array(handle: $ch, options: $options);

        $this->setContent(ch: $ch, payload: $payload);

        return $ch;
    }

    /**
     * @return bool
     */
    public function hasBodyData(): bool
    {
        return (
            $this->requestMethod === RequestMethod::POST ||
            $this->requestMethod === RequestMethod::PUT ||
            $this->requestMethod === RequestMethod::DELETE
        );
    }

    /**
     * @param string $url
     * @param array $payload
     * @return string
     * @throws JsonException
     * @throws ValidationException
     * @todo Add URL prefix based on $this->authType?
     */
    public function generateUrl(string $url, array $payload): string
    {
        $url .= $this->hasBodyData() || empty($payload)
            ? '' :
            '?' . $this->getPayloadData(payload: $payload);

        if (!filter_var(value: $url, filter: FILTER_VALIDATE_URL)) {
            throw new ValidationException(message: 'Invalid URL requested (' . $url . ').');
        }

        return $url;
    }

    /**
     * @return string
     */
    private function getCustomRequestValue(): string
    {
        return match ($this->requestMethod) {
            RequestMethod::GET => 'GET',
            RequestMethod::POST => 'POST',
            RequestMethod::PUT => 'PUT',
            RequestMethod::DELETE => 'DELETE'
        };
    }

    /**
     * Append POST | PUT data / options to CURL.
     *
     * @param CurlHandle $ch
     * @param array $payload
     * @return void
     * @throws JsonException
     */
    private function setContent(CurlHandle $ch, array $payload): void
    {
        if ($this->contentType === ContentType::EMPTY) {
            return;
        }

        $data = $this->getPayloadData(payload: $payload);

        if ($data !== '' && $this->hasBodyData()) {
            curl_setopt(
                handle: $ch,
                option: CURLOPT_POSTFIELDS,
                value: $data
            );
        }

        if ($this->requestMethod === RequestMethod::POST) {
            curl_setopt(
                handle: $ch,
                option: CURLOPT_POST,
                value: true
            );
        }
    }

    /**
     * @param array $payload
     * @return string
     * @throws JsonException
     * @todo Consider caching this is a local variable on this instance to avoid subsequent calls. NOTE: Generating this
     * @todo data directly in the constructor harms refactoring.
     */
    public function getPayloadData(
        array $payload
    ): string {
        $flags = JSON_THROW_ON_ERROR;
        if ($this->forceObject) {
            $flags = JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT;
        }
        return match ($this->contentType) {
            ContentType::EMPTY, ContentType::RAW => '',
            ContentType::JSON => json_encode($payload, JSON_THROW_ON_ERROR | $flags),
            ContentType::URL => http_build_query(data: $payload)
        };
    }

    /**
     * @param CurlHandle $ch
     * @return void
     * @throws CurlException
     * @throws AuthException
     * @throws IllegalTypeException
     */
    private function setAuth(CurlHandle $ch): void
    {
        switch ($this->authType) {
            case AuthType::BASIC:
                $this->setBasicAuth(ch: $ch);
                break;
            case AuthType::JWT:
                $this->setJwtAuth(ch: $ch);
                break;
            case AuthType::NONE:
                break;
        }
    }

    /**
     * @param CurlHandle $ch
     * @return void
     * @throws CurlException
     */
    private function setBasicAuth(CurlHandle $ch): void
    {
        $auth = Config::$instance->basicAuth;

        if ($auth === null) {
            throw new CurlException(message: 'Basic auth not configured.');
        }

        curl_setopt(
            handle: $ch,
            option: CURLOPT_USERPWD,
            value: "$auth->username:$auth->password"
        );
    }

    /**
     * @param CurlHandle $ch
     * @return void
     * @throws CurlException
     * @throws AuthException
     * @throws IllegalTypeException
     */
    private function setJwtAuth(CurlHandle $ch): void
    {
        $auth = Config::$instance->jwtAuth;

        if ($auth === null) {
            throw new CurlException(message: 'JWT auth not configured.');
        }

        curl_setopt(
            handle: $ch,
            option: CURLOPT_HTTPAUTH,
            value: CURLAUTH_BEARER
        );

        curl_setopt(
            handle: $ch,
            option: CURLOPT_XOAUTH2_BEARER,
            value: $auth->getToken()->accessToken
        );
    }

    /**
     * @param mixed $body
     * @return void
     * @throws CurlException
     */
    private function handleError(mixed $body = null): void
    {
        /** @noinspection DuplicatedCode */
        $msg = curl_error(handle: $this->ch);
        $code = curl_errno(handle: $this->ch);
        $httpCode = curl_getinfo(handle: $this->ch, option: CURLINFO_HTTP_CODE);
        $connectCode = curl_getinfo(handle: $this->ch, option: CURLINFO_HTTP_CONNECTCODE);

        if ($this->responseContentType === ContentType::JSON && !empty($body)) {
            /** @psalm-suppress MixedAssignment */
            try {
                $jsonMessage = json_decode(
                    json: $body,
                    associative: false,
                    depth: 768,
                    flags: JSON_THROW_ON_ERROR
                );
                if ($jsonMessage && isset($jsonMessage->message)) {
                    $msg = $jsonMessage->message . '.';
                    if (property_exists($jsonMessage, 'parameters') && is_object($jsonMessage->parameters)) {
                        foreach ($jsonMessage->parameters as $key => $value) {
                            $msg .= " $key: $value";
                        }
                    }
                }
            } catch (Exception) {
                // Ignore.
            }
        }

        if ($code !== 0 || $httpCode >= 400) {
            // Some exceptions that curl are throwing as CURLE_RECV_ERROR may falsely state that data could
            // not be received from the remote. However, in some cases, the remote server is actually telling
            // why curl can not receive data. Those errors are based on HTTP >= 400 responses and should, in
            // cases where the remote end actually have a proper answer, be returned correctly instead of the generic
            // CURLE_RECV_ERROR. Among a few examples, this could happen when integrations are using proxy layers
            // for which the proxy remote end won't allow. Usually the remote proxy may throw 400 or permission
            // denied errors, which should be used instead of CURLE_RECV_ERROR.
            $throwCode = $code !== 0 ? $code : $httpCode;
            if ($connectCode >= 400 && $code === CURLE_RECV_ERROR) {
                $throwCode = $connectCode;
            }

            throw new CurlException(
                message: 'CURL error (' . $throwCode . "): $msg",
                code: ($code !== 0 ? $throwCode : $httpCode),
                requestBody: is_string($body) ? $body : null
            );
        }
    }

    /**
     * Returns configured auth credentials as array
     *
     * @return array
     */
    public function getAuthentication(): array
    {
        return match ($this->authType) {
            AuthType::BASIC => (array)Config::$instance->basicAuth,
            AuthType::JWT => (array)Config::$instance->jwtAuth,
            default => [],
        };
    }
}
