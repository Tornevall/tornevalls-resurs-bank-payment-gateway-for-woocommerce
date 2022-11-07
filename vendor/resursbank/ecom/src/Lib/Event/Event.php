<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Event;

use Resursbank\Ecom\Exception\EventException;

/**
 * Basic Event implementation. This serves as a guide and centralization point
 * for our internal Event implementations.
 */
class Event implements EventInterface
{
    /**
     * NOTE: subscribers are appended after object creation, hence validation
     * is irrelevant in the constructor body.
     *
     * @param string $name
     * @param array<SubscriberInterface> $subscribers
     */
    public function __construct(
        private string $name,
        private array $subscribers = []
    ) {
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function addSubscriber(SubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * @inheritdoc
     * @throws EventException
     */
    public function getSubscribers(): array
    {
        foreach ($this->subscribers as $subscriber) {
            if (!$subscriber instanceof SubscriberInterface) {
                throw new EventException(
                    message: 'Subscribers must implement SubscriberInterface'
                );
            }
        }

        return $this->subscribers;
    }
}
