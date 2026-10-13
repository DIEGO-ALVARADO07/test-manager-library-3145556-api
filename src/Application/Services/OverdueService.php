<?php

declare(strict_types=1);

namespace Library\Application\Services;

use DateTimeImmutable;
use Library\Application\Pagination\Page;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;

/** HU-09: unreturned lines past their due date, most days late first. */
final class OverdueService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    /** @return array{page: Page, now: DateTimeImmutable} */
    public function list(int $page, int $size): array
    {
        $now = $this->clock->now();

        return ['page' => $this->loans->overdueItems($now, $page, $size), 'now' => $now];
    }

    /** Whole days past the due date (rounded down), calculated and never stored (Art. VII). */
    public static function daysLate(DateTimeImmutable $dueAt, DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $dueAt->getTimestamp(), 86400));
    }
}
