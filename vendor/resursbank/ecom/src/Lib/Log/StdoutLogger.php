<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Log;

use DateTime;
use Error;
use Exception;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\IOException;

use function get_class;
use function is_object;

/**
 * Write logs directly to STDOUT and STDERR
 */
class StdoutLogger implements LoggerInterface
{
    private const ERR_GENERAL_WRITE = 'Unable to write message to STDOUT/STDERR';

    /**
     * @inheritDoc
     * @throws IOException
     * @throws ConfigException
     */
    public function debug(Exception|string|Error $message): void
    {
        $this->log(level: LogLevel::DEBUG, message: $message);
    }

    /**
     * @inheritDoc
     * @throws IOException
     * @throws ConfigException
     */
    public function info(Exception|string $message): void
    {
        $this->log(level: LogLevel::INFO, message: $message);
    }

    /**
     * @inheritDoc
     * @throws IOException
     * @throws ConfigException
     */
    public function warning(Exception|string $message): void
    {
        $this->log(level: LogLevel::WARNING, message: $message);
    }

    /**
     * @inheritDoc
     * @throws IOException
     * @throws ConfigException
     */
    public function error(Exception|string $message): void
    {
        $this->log(level: LogLevel::ERROR, message: $message);
    }

    /**
     * Write log entry to STDOUT/STDERR (depending on log level)
     *
     * @param LogLevel $level
     * @param string|Exception|Error $message
     * @return void
     * @throws IOException
     * @throws ConfigException
     */
    private function log(LogLevel $level, string|Exception|Error $message): void
    {
        /**
         * @psalm-suppress RedundantCondition
         */
        if (
            is_object(value: $message) &&
            (
                get_class(object: $message) === Exception::class ||
                is_subclass_of(object_or_class: $message, class: Exception::class)
            )
        ) {
            $this->logException(exception: $message);
        } elseif (
            is_object(value: $message) &&
            (
                get_class(object: $message) === Error::class ||
                is_subclass_of(object_or_class: $message, class: Error::class)
            )
        ) {
            $this->logError(error: $message);
        } elseif (LogLevel::loggable(level: $level)) {
            $fileHandle = match ($level) {
                LogLevel::EXCEPTION, LogLevel::ERROR => fopen(filename: 'php://stderr', mode: 'ab'),
                LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING => fopen(filename: 'php://stdout', mode: 'ab')
            };

            if ($fileHandle === false) {
                throw new IOException(message: self::ERR_GENERAL_WRITE);
            }

            $timestamp = new DateTime();
            $formattedMessage = $timestamp->format(format: 'c') . ' ' . $level->name . ': ' . $message;
            fwrite(stream: $fileHandle, data: $formattedMessage);
            fclose(stream: $fileHandle);
        }
    }

    /**
     * Log Exception object by converting it to a string and feeding it to the log method.
     *
     * @param Exception $exception
     * @return void
     * @throws IOException
     * @throws ConfigException
     */
    private function logException(Exception $exception): void
    {
        $this->log(level: LogLevel::EXCEPTION, message: $exception->getTraceAsString());
    }

    /**
     * Log Error object by converting it to a string and feeding it to the log method.
     *
     * @param Error $error
     * @return void
     * @throws IOException
     * @throws ConfigException
     */
    private function logError(Error $error): void
    {
        $this->log(level: LogLevel::ERROR, message: $error->getTraceAsString());
    }
}
