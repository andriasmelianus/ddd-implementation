<?php

declare(strict_types=1);

namespace Tests\Unit\IAM\Support;

use Src\Shared\Framework\Domain\Events\DomainEvent;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\EventBusInterface;

/**
 * Spy implementation of `EventBusInterface` that simply collects whatever the
 * handler publishes, instead of dispatching through Laravel's event system.
 *
 * Lets handler tests assert the integration event contract without booting
 * the framework.
 */
final class RecordingEventBus implements EventBusInterface
{
    /** @var list<DomainEvent> */
    public array $publishedEvents = [];

    /**
     * @param  array<int, DomainEvent>  $events
     */
    public function publishEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->publishedEvents[] = $event;
        }
    }
}
