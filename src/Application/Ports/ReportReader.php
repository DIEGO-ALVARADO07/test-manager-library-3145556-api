<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\ValueObjects\DateRange;

interface ReportReader
{
    /**
     * One aggregated query over the frozen values of the lines (ADR-004).
     *
     * @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     */
    public function mostBorrowed(DateRange $range): array;
}
