<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Cache;

use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\ValidationException;

use function is_int;

/**
 * Basic filesystem caching.
 *
 * @todo If we add a health report as discussed we should add some writ- / readable information about the cache dir /
 * @todo files since read will fail silently.
 */
class Filesystem extends AbstractCache implements CacheInterface
{
    /**
     * @param string $path | Directory where cache files will be stored.
     */
    public function __construct(
        private readonly string $path
    ) {
    }

    /**
     * If there should be any problem with the requested cache file, for example
     * if the file exists but isn't writable, its path is allocated by a
     * directory, its content is invalid or corrupt etc. this method will simply
     * return null, meaning it will fail silently.
     *
     * @inheritdoc
     * @throws ValidationException
     * @todo Consider adding logs.
     */
    public function read(string $key): ?string
    {
        $result = null;

        // Make sure the key consists of valid characters.
        $this->validateKey(key: $key);

        // Read and parse cache file.
        $data = $this->getFileContent(file: $this->getFile(key: $key));
        $split = $this->getSplit(content: $data);
        $ttl = substr(string: $data, offset: 0, length: $split);
        $content = substr(string: $data, offset: $split + 1);

        // Make sure the content isn't empty and TTL has not expired.
        if (
            $content !== '' &&
            !preg_match(pattern: '/\D/', subject: $ttl) &&
            time() < (int)$ttl
        ) {
            $result = $content;
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     * @throws FilesystemException
     */
    public function write(string $key, string $data, int $ttl): void
    {
        // Make sure the key consists of valid characters.
        $this->validateKey(key: $key);

        // Create the cache directory, if it's missing.
        $this->createPath();

        // Create the cache file.
        $filename = $this->getFile(key: $key);

        if (file_exists(filename: $filename)) {
            if (!is_file(filename: $filename)) {
                throw new FilesystemException(
                    message: "$filename is not a file."
                );
            }

            if (!is_writable(filename: $filename)) {
                throw new FilesystemException(
                    message: "$filename is not writable."
                );
            }
        }

        $ttl = time() + $ttl;

        file_put_contents(filename: $filename, data: "$ttl|$data");
    }

    /**
     * @inheritdoc
     * @throws FilesystemException
     * @throws ValidationException
     */
    public function clear(string $key): void
    {
        // Make sure the key consists of valid characters.
        $this->validateKey(key: $key);

        // Read cache file.
        $file = $this->getFile(key: $key);

        if (file_exists(filename: $file)) {
            if (!is_file(filename: $file)) {
                throw new FilesystemException(
                    message: "$file is not a regular file."
                );
            }

            if (!is_writable(filename: $file)) {
                throw new FilesystemException(
                    message: "$file is not writable."
                );
            }

            unlink(filename: $file);
        }
    }

    /**
     * Prepare directory where cache is stored by creating it if it doesn't
     * already exist and making sure it's writable.
     *
     * @return void
     * @throws FilesystemException
     */
    private function createPath(): void
    {
        if (
            file_exists(filename: $this->path) &&
            is_file(filename: $this->path)
        ) {
            throw new FilesystemException(message: "$this->path is a file.");
        }

        if (
            !file_exists(filename: $this->path) &&
            !mkdir(
                directory: $this->path,
                permissions: 0755,
                recursive: true
            ) &&
            !is_dir(filename: $this->path)
        ) {
            throw new FilesystemException(
                message: "Failed to create cache dir $this->path"
            );
        }

        if (!is_writable(filename: $this->path)) {
            throw new FilesystemException(
                message: "$this->path is not writable."
            );
        }
    }

    /**
     * Convert key to cache file path.
     *
     * @param string $key
     * @return string
     */
    private function getFile(string $key): string
    {
        return "$this->path/$key.cache";
    }

    /**
     * @param string $file
     * @return string
     */
    private function getFileContent(
        string $file
    ): string {
        $result = '';

        if (
            file_exists(filename: $file) &&
            is_file(filename: $file) &&
            is_readable(filename: $file)
        ) {
            $result = file_get_contents(filename: $file);
        }

        return $result;
    }

    /**
     * @param string $content
     * @return int
     */
    private function getSplit(
        string $content
    ): int {
        $result = 0;

        $split = strpos(haystack: $content, needle: '|');

        // Make sure we got a split pointer.
        if (is_int(value: $split) && $split > 1) {
            $result = $split;
        }

        return $result;
    }
}
