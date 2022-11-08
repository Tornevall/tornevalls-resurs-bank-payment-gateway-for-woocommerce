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
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\FormatException;

use function get_class;
use function is_object;

/**
 * Write logfiles to disk.
 */
class FileLogger implements LoggerInterface
{
    private const LOG_FILENAME = 'ecom.log';
    private const PATH_ERR_EMPTY = 'Specified log file path is empty';
    private const PATH_ERR_WHITESPACE = 'Specified log file path has trailing or leading whitespace';
    private const PATH_ERR_TRAILING_SEPARATOR = 'Specified log file path has a trailing directory separator character';
    private const PATH_ERR_FILE_DOES_NOT_EXIST = 'Specified log file path does not exist';
    private const PATH_ERR_FILE_NOT_DIRECTORY = 'Specified log file path is not a directory';
    private const PATH_ERR_FILE_NOT_WRITABLE = 'Specified log file path is not writable';
    private const WRITE_ERROR = 'No data was written to the log file';
    private const ERR_UNWRITABLE = 'Log file appears to be unwritable';

    /**
     * @param string $path
     * @throws FilesystemException
     * @throws EmptyValueException
     * @throws FormatException
     */
    public function __construct(
        private readonly string $path
    ) {
        $this->validatePath();
    }

    /**
     * Logs message with log level DEBUG
     *
     * @param string|Exception|Error $message
     * @return void
     * @throws FilesystemException
     * @throws ConfigException
     */
    public function debug(string|Exception|Error $message): void
    {
        $this->log(level: LogLevel::DEBUG, message: $message);
    }

    /**
     * Logs message with log level INFO
     *
     * @param string|Exception $message
     * @return void
     * @throws FilesystemException
     * @throws ConfigException
     */
    public function info(string|Exception $message): void
    {
        $this->log(level: LogLevel::INFO, message: $message);
    }

    /**
     * Logs message with log level WARNING
     *
     * @param string|Exception $message
     * @return void
     * @throws FilesystemException
     * @throws ConfigException
     */
    public function warning(string|Exception $message): void
    {
        $this->log(level: LogLevel::WARNING, message: $message);
    }

    /**
     * Logs message with log level ERROR
     *
     * @param string|Exception $message
     * @return void
     * @throws FilesystemException
     * @throws ConfigException
     */
    public function error(string|Exception $message): void
    {
        $this->log(level: LogLevel::ERROR, message: $message);
    }

    /**
     * Write log entry to file on disk.
     *
     * @param LogLevel $level
     * @param string|Exception|Error $message
     * @return void
     * @throws FilesystemException
     * @throws ConfigException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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
            $timestamp = new DateTime();
            $formattedMessage = $timestamp->format(format: 'c') . ' ' . $level->name . ': ' . $message;

            if (!$this->logIsWritable()) {
                throw new FilesystemException(message: self::ERR_UNWRITABLE);
            }

            if (
                !file_put_contents(
                    filename: $this->getFilename(),
                    data: $formattedMessage . PHP_EOL,
                    flags: FILE_APPEND | LOCK_EX
                )
            ) {
                throw new FilesystemException(message: self::WRITE_ERROR);
            }
        }
    }

    /**
     * Log Exception object by converting it to a string and feeding it to the log method.
     *
     * @param Exception $exception
     * @return void
     * @throws FilesystemException
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
     * @throws FilesystemException
     * @throws ConfigException
     */
    private function logError(Error $error): void
    {
        $this->log(level: LogLevel::ERROR, message: $error->getTraceAsString());
    }

    /**
     * Returns absolute path to log file.
     *
     * @return string
     */
    private function getFilename(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . self::LOG_FILENAME;
    }

    /**
     * Validate logfile storage path.
     *
     * @return bool
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws FormatException
     */
    private function validatePath(): bool
    {
        if ($this->path === '') {
            throw new EmptyValueException(message: self::PATH_ERR_EMPTY);
        }
        if ($this->path !== trim(string: $this->path)) {
            throw new FormatException(message: self::PATH_ERR_WHITESPACE);
        }
        if (DIRECTORY_SEPARATOR === substr(string: $this->path, offset: -1)) {
            throw new FormatException(message: self::PATH_ERR_TRAILING_SEPARATOR);
        }
        if (!file_exists(filename: $this->path)) {
            throw new FilesystemException(message: self::PATH_ERR_FILE_DOES_NOT_EXIST);
        }
        if (!is_dir(filename: $this->path)) {
            throw new FilesystemException(message: self::PATH_ERR_FILE_NOT_DIRECTORY);
        }
        if (!is_writable(filename: $this->path)) {
            throw new FilesystemException(message: self::PATH_ERR_FILE_NOT_WRITABLE);
        }

        return true;
    }

    /**
     * Checks if the log file is writable.
     *
     * @return bool
     */
    private function logIsWritable(): bool
    {
        // Consider file writable if it either exists, isn't a directory and is writable or it doesn't exist but the
        // parent directory passes the validation test
        try {
            /** @noinspection NotOptimalIfConditionsInspection */
            if (
                (file_exists(filename: $this->getFilename()) && is_writable(filename: $this->getFilename())) ||
                (!file_exists(filename: $this->getFilename()) && $this->validatePath())
            ) {
                return true;
            }
        } catch (Exception) {
            return false;
        }

        return false;
    }
}
