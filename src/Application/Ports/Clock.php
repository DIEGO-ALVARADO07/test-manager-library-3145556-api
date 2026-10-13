<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use DateTimeImmutable;

interface Clock
{
    /** Current instant in UTC. */
    public function now(): DateTimeImmutable;
}
