<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\ReportReader;
use Library\Domain\ValueObjects\DateRange;

/** ADR-004: one aggregated query that groups by the values frozen on the line, never by the live catalog. */
final class PgReportReader implements ReportReader
{
    public function mostBorrowed(DateRange $range): array
    {
        $rows = DB::select(
            'SELECT li.book_id, li.book_title, li.category_name, COUNT(*) AS times_borrowed
               FROM loan_item li
               JOIN loan l ON l.id = li.loan_id
              WHERE l.loaned_at >= ? AND l.loaned_at < ?
              GROUP BY li.book_id, li.book_title, li.category_name
              ORDER BY times_borrowed DESC, li.book_title ASC, li.book_id ASC',
            [PgTime::format($range->startsAt()), PgTime::format($range->endsBefore())],
        );

        return array_map(static fn (object $r): array => [
            'book_id' => $r->book_id,
            'book_title' => $r->book_title,
            'category_name' => $r->category_name,
            'times_borrowed' => (int) $r->times_borrowed,
        ], $rows);
    }
}
