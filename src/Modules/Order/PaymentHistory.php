<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use InvalidArgumentException;
use JsonException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Model\PaymentHistory\DataHandler\DataHandlerInterface;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Entry;
use Resursbank\Ecom\Lib\Model\PaymentHistory\EntryCollection;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Event;
use Resursbank\Ecom\Lib\Utilities\DataConverter;
use Resursbank\Ecom\Module\PaymentHistory\Translator;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;

class PaymentHistory implements DataHandlerInterface
{
    const META_KEY_PREFIX = 'resursbank_payment_history_';

    /**
     * @param string $paymentId
     * @param Event|null $event
     * @return EntryCollection
     * @throws IllegalTypeException
     */
    public function getList(
        string $paymentId,
        ?Event $event
    ): EntryCollection {
        global $wpdb;

        try {
            $results = $wpdb->get_results(
                query: $wpdb->prepare(
                    "SELECT * FROM {$wpdb->commentmeta} WHERE meta_key = %s",
                    self::META_KEY_PREFIX . $paymentId
                )
            );

            $entries = [];

            foreach ($results as $row) {
                try {
                    $entries[] = json_decode(
                        json: $row->meta_value,
                        associative: false,
                        depth: 512,
                        flags: JSON_THROW_ON_ERROR
                    );
                } catch (Throwable $error) {
                    Logger::error(message: $error);
                }
            }

            $data = DataConverter::arrayToCollection(
                data: $entries,
                type: Entry::class
            );

            if (!$data instanceof EntryCollection) {
                throw new InvalidArgumentException(
                    message: 'Failed to convert payment history entries to collection.'
                );
            }

            return $data;
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }

        return new EntryCollection(data: []);
    }

    public function write(Entry $entry): void
    {
        try {
            // First, add a note to the WC_Order. This is how we actually render
            // the payment history in the admin interface, since we do not use
            // our regular payment history widget in WC.
            //
            // Note that order notes are tracked in the database in post
            // comments (wp_comments), since orders are actually posts.
            $order = Metadata::getOrderByPaymentId(paymentId: $entry->paymentId);

            if ($order === null) {
                throw new InvalidArgumentException(
                    message: 'Failed to resolve order from payment ' . $entry->paymentId
                );
            }

            $commentId = $order->add_order_note(note: $this->getNote(entry: $entry));

            // Track the entire Event as JSON data in the "commentmeta" table.
            add_comment_meta(
                comment_id: $commentId,
                meta_key: self::META_KEY_PREFIX . $entry->paymentId,
                meta_value: json_encode(
                    value: $entry->toArray(),
                    flags: JSON_THROW_ON_ERROR
                ),
                unique: false
            );

            // Sync order status.
            if (in_array(
                needle: $entry->event,
                haystack: self::ORDER_STATUS_SYNC_EVENTS
            )) {
                Status::update(order: $order);
            }
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }
    }

    /**
     * Get
     *
     * @param Entry $entry
     * @return string
     * @throws JsonException
     * @throws ConfigException
     * @throws FilesystemException
     * @throws TranslationException
     */
    public function getNote(Entry $entry): string
    {
        $note = Translator::translate(phraseId: $entry->event->value);

        if (in_array(
            needle: $entry->event,
            haystack: [Event::CAPTURED, Event::PARTIALLY_CAPTURED, Event::REFUNDED, Event::PARTIALLY_REFUNDED]
        )) {
            // Replace final dot in the note with amount info from extra.
            $note = rtrim(string: $note, characters: '.') .
                ' (' . $entry->extra . ').';
        }

        return $note;
    }


    public function hasExecuted(string $paymentId, Event $event): bool
    {
        $entries = $this->getList(paymentId: $paymentId, event: $event);

        foreach ($entries as $entry) {
            if ($entry->event === $event) {
                return true;
            }
        }

        return false;
    }
}