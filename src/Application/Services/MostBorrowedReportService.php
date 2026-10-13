<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\ReportReader;
use Library\Domain\ValueObjects\DateRange;

/** HU-10 */
final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reader)
    {
    }

    /** @return list<array{book_id: string, book_title: string, category_name: string, times_borrowed: int}> */
    public function report(DateRange $range): array
    {
        return $this->reader->mostBorrowed($range);
    }
}
