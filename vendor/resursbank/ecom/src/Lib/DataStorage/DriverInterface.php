<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\DataStorage;

/**
 * Describes data required to access a MySQL database.
 */
interface DriverInterface
{
    /**
     * Execute a raw query against storage.
     *
     * @return void
     */
    public function query(): void;

    /**
     * Perform query to load entity from storage.
     *
     * @return string
     */
    public function load(): string;

    /**
     * Perform query to persist entity to storage.
     *
     * @return void
     */
    public function write(): void;

    /**
     * Perform query to delete entity from storage.
     *
     * @return void
     */
    public function delete(): void;
}
