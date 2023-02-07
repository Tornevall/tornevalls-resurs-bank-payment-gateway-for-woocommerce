<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Woocommerce\Modules\MessageBag\Models\Message;
use Resursbank\Woocommerce\Modules\MessageBag\Models\MessageCollection;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

use function is_array;

/**
 * Message bag utilised in admin.
 */
class MessageBag
{
    public const SESSION_KEY = 'rb-msg-bag';

    /**
     * Whether to clear the bag after rendering it.
     */
    private static bool $clear = true;

    /**
     * Initialize this module.
     */
    public static function init(): void
    {
        add_action(
            hook_name: 'admin_notices',
            callback: static fn () => MessageBag::printMessages()
        );
    }

    /**
     * Add message to bag.
     */
    public static function add(string $msg, Type $type): void
    {
        try {
            $message = new Message(msg: $msg, type: $type);
        } catch (EmptyValueException) {
            $message = new Message(
                msg: 'Empty message encountered.',
                type: Type::ERROR
            );
        }

        try {
            $bag = self::getBag();
            $bag->offsetSet(offset: null, value: $message);
            self::updateBag(bag: $bag);
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }

    /**
     * Add error message.
     */
    public static function addError(string $msg): void
    {
        self::add(msg: $msg, type: Type::ERROR);
    }

    /**
     * Add error message.
     */
    public static function addSuccess(string $msg): void
    {
        self::add(msg: $msg, type: Type::SUCCESS);
    }

    /**
     * Print message bag.
     */
    public static function printMessages(): void
    {
        try {
            foreach (self::getBag() as $message) {
                $msg = $message->getEscapedMsg();
                $type = $message->type->value;

                echo "<div class=\"$type notice\"><p>$msg</p></div>";
            }

            if (self::$clear) {
                self::updateBag(bag: new MessageCollection(data: []));
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }

    /**
     * Do not clear bag after rendering it.
     */
    public static function keep(): void
    {
        self::$clear = false;
    }

    /**
     * Resolve MessageCollection instance from JSON in session.
     *
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalValueException
     */
    private static function getBag(): MessageCollection
    {
        $raw = WcSession::get(key: self::SESSION_KEY);

        $data = $raw !== '' ? json_decode(
            json: $raw,
            associative: false,
            depth: 512,
            flags: JSON_THROW_ON_ERROR
        ) : [];

        $collection = DataConverter::arrayToCollection(
            data: is_array(value: $data) ? $data : [],
            type: Message::class
        );

        return $collection instanceof MessageCollection
            ? $collection
            : new MessageCollection(data: []);
    }

    /**
     * Update message bag data in session.
     *
     * @throws JsonException
     */
    private static function updateBag(MessageCollection $bag): void
    {
        WcSession::set(
            key: self::SESSION_KEY,
            value: json_encode(
                value: $bag->toArray(),
                flags: JSON_THROW_ON_ERROR
            )
        );
    }
}
