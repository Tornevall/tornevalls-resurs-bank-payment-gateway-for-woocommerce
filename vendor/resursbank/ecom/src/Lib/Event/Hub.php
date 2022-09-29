<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Event;

use JsonException;
use Resursbank\Ecom\Exception\EventException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\EventSubscriberException;

use function json_encode;

/**
 * The event hub. This class keeps events in memory and lets you dispatch them
 * through the Resursbank\Ecom\Config instance.
 */
class Hub
{
    /**
     * @param array<EventInterface> $events
     */
    public function __construct(
        private readonly Config $config,
        private array $events
    ) {
    }

    /**
     * @param EventInterface $event
     * @return void
     */
    public function addEvent(EventInterface $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param string $name
     * @param array $data
     * @return void
     * @throws EventException
     * @throws EventSubscriberException
     * @throws JsonException
     */
    public function dispatch(string $name, array $data): void
    {
        $found = false;

        foreach ($this->events as $event) {
            if (!$event instanceof EventInterface) {
                throw new EventException(
                    message: 'Events must implement EventInterface.'
                );
            }

            if ($event->getName() !== $name) {
                continue;
            }

            $found = true;

            foreach ($event->getSubscribers() as $subscriber) {
                if (!$subscriber->validate(data: $data)) {
                    throw new EventSubscriberException(
                        message: sprintf(
                            'Rejected data %d for %s',
                            json_encode(
                                value: $data,
                                flags: JSON_THROW_ON_ERROR
                            ),
                            $name
                        )
                    );
                }

                $subscriber->execute(config: $this->config, data: $data);
            }
        }

        if (!$found) {
            throw new EventException(
                message: sprintf(
                    'Failed to dispatch %d. Event not found.',
                    $name
                )
            );
        }
    }
}
