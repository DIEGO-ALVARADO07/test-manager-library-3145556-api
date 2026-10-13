<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Time;

use DateTimeImmutable;
use DateTimeZone;
use Library\Application\Ports\Clock;

/** Real UTC time, optionally shifted by whole days for the demo seeding phase (ADR-006). */
final class SystemClock implements Clock
{
    public function __construct(private readonly int $offsetDays = 0)
    {
    }

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->offsetDays === 0 ? $now : $now->modify(sprintf('%+d days', $this->offsetDays));
    }
}
