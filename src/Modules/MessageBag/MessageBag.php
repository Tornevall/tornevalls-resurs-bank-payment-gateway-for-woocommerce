<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Woocommerce\Modules\MessageBag\Models\Message;
use Resursbank\Woocommerce\Modules\MessageBag\Models\MessageCollection;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

use function defined;
use function function_exists;
use function is_array;

/**
 * Message bag.
 */
class MessageBag
{
    public const SESSION_KEY = 'rb-message-bag';

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
            'admin_notices',
            'Resursbank\Woocommerce\Modules\MessageBag\MessageBag::printMessages'
        );
    }

    /**
     * Add message to bag.
     */
    // phpcs:ignore
    public static function add(string $message, Type $type): void
    {
        try {
            if ($message === '') {
                $messageInstance = new Message(
                    message: 'Empty message encountered.',
                    type: Type::ERROR
                );
            } else {
                $messageInstance = new Message(message: $message, type: $type);
            }

            if (Admin::isAdmin()) {
                if (defined(constant_name: 'DOING_AJAX')) {
                    // No way of handling messages in AJAX requests.
                    return;
                }

                $bag = self::getBag();

                if (!self::isInBag(message: $message, bag: $bag)) {
                    $bag->offsetSet(offset: null, value: $messageInstance);
                    self::updateBag(bag: $bag);
                }
            } elseif (function_exists(function: 'wc_add_notice')) {
                wc_add_notice(message: $message, notice_type: $type->value);
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }

    /**
     * Add error message.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public static function addError(string $message): void
    {
        self::add(message: $message, type: Type::ERROR);
    }

    /**
     * Add error message.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public static function addSuccess(string $message): void
    {
        self::add(message: $message, type: Type::SUCCESS);
    }

    /**
     * Print message bag.
     */
    public static function printMessages(): void
    {
        try {
            /** @var Message $message */
            foreach (self::getBag() as $message) {
                echo wp_kses(
                    '<div class="' . $message->type->value . ' notice"><p>' .
                    $message->getMessage() . '</p></div>',
                    [
                        'div' => ['class' => true],
                        'p' => [],
                    ]
                );
            }

            if (self::$clear) {
                self::clear();
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
     * @throws IllegalTypeException
     * @throws JsonException
     */
    public static function clear(): void
    {
        self::updateBag(bag: new MessageCollection(data: []));
    }

    /**
     * Resolve MessageCollection instance from JSON in session.
     *
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalValueException
     */
    public static function getBag(): MessageCollection
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
     * Look for duplicate messages in the collection.
     */
    private static function isInBag(string $message, MessageCollection $bag): bool
    {
        /** @var Message $item */
        foreach ($bag as $item) {
            if ($item->message === $message) {
                $return = true;
                break;
            }
        }

        return $return ?? false;
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
