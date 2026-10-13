<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Library\Application\Pagination\Page;
use Library\Application\Ports\LoanRepository;
use Library\Domain\Book;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Loan;
use Library\Domain\LoanItem;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\LoanStatus;
use Library\Domain\ValueObjects\Uuid;

final class PgLoanRepository implements LoanRepository
{
    public function find(string $id): ?Loan
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = DB::table('loan')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }

        return $this->hydrateLoans([$row])[0];
    }

    public function search(
        ?DateRange $range,
        ?LoanStatus $status,
        ?string $memberId,
        DateTimeImmutable $now,
        int $page,
        int $size,
    ): Page {
        $query = DB::table('loan');
        if ($range !== null) {
            $query->where('loaned_at', '>=', PgTime::format($range->startsAt()))
                ->where('loaned_at', '<', PgTime::format($range->endsBefore()));
        }
        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }
        // HU-08 / H-10: the status is never stored, it is derived from the lines.
        $pending = 'EXISTS (SELECT 1 FROM loan_item i WHERE i.loan_id = loan.id AND i.returned_at IS NULL)';
        $late = 'EXISTS (SELECT 1 FROM loan_item i WHERE i.loan_id = loan.id AND i.returned_at IS NULL AND i.due_at < ?)';
        $nowSql = PgTime::format($now);
        match ($status) {
            LoanStatus::Closed => $query->whereRaw("NOT $pending"),
            LoanStatus::Overdue => $query->whereRaw($late, [$nowSql]),
            LoanStatus::Open => $query->whereRaw($pending)->whereRaw("NOT $late", [$nowSql]),
            null => null,
        };

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('loaned_at')->orderByDesc('id')->forPage($page, $size)->get()->all();

        return new Page($this->hydrateLoans($rows), $page, $size, $total);
    }

    public function unreturnedItemsOfMember(string $memberId): array
    {
        return DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->where('loan.member_id', $memberId)
            ->whereNull('loan_item.returned_at')
            ->select('loan_item.*')
            ->get()
            ->map(fn (object $r): LoanItem => $this->toItem($r))
            ->all();
    }

    public function overdueItems(DateTimeImmutable $now, int $page, int $size): Page
    {
        $query = DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->join('member', 'member.id', '=', 'loan.member_id')
            ->whereNull('loan_item.returned_at')
            ->where('loan_item.due_at', '<', PgTime::format($now));

        $total = (clone $query)->count();
        $rows = $query
            ->select('loan_item.loan_id', 'loan_item.id as item_id', 'loan_item.book_title', 'loan_item.due_at', 'member.full_name as member_name')
            ->orderBy('loan_item.due_at')   // oldest due date = most days late
            ->orderBy('loan_item.id')
            ->forPage($page, $size)
            ->get();

        $items = $rows->map(static fn (object $r): array => [
            'loan_id' => $r->loan_id,
            'item_id' => $r->item_id,
            'book_title' => $r->book_title,
            'member_name' => $r->member_name,
            'due_at' => PgTime::parseRequired($r->due_at),
        ])->all();

        return new Page($items, $page, $size, $total);
    }

    public function saveLending(Loan $loan, array $books): void
    {
        DB::transaction(function () use ($loan, $books): void {
            // Fixed order avoids deadlocks between two loans that share books.
            usort($books, static fn (Book $a, Book $b): int => strcmp($a->id, $b->id));

            foreach ($books as $book) {
                // ADR-005: compare-and-set on the value that was read. 0 rows = somebody got there first.
                $affected = DB::table('book')
                    ->where('id', $book->id)
                    ->where('available_copies', $book->availableAtLoad())
                    ->whereNull('deleted_at')
                    ->update(['available_copies' => $book->availableCopies()]);

                if ($affected === 0) {
                    throw new BookNotAvailable(sprintf('No quedan ejemplares disponibles de «%s».', $book->title()));
                }
            }

            DB::table('loan')->insert([
                'id' => $loan->id,
                'member_id' => $loan->memberId,
                'loaned_by_user_id' => $loan->loanedByUserId,
                'loaned_at' => PgTime::format($loan->loanedAt),
            ]);
            foreach ($loan->items() as $item) {
                DB::table('loan_item')->insert([
                    'id' => $item->id,
                    'loan_id' => $loan->id,
                    'book_id' => $item->bookId,
                    'book_title' => $item->bookTitle,
                    'isbn' => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at' => PgTime::format($item->dueAt),
                    'returned_at' => null,
                ]);
            }
        });
    }

    public function saveReturn(Loan $loan, string $itemId, string $bookId): void
    {
        $item = null;
        foreach ($loan->items() as $candidate) {
            if ($candidate->id === $itemId) {
                $item = $candidate;
            }
        }

        DB::transaction(function () use ($loan, $itemId, $bookId, $item): void {
            $affected = DB::table('loan_item')
                ->where('id', $itemId)
                ->where('loan_id', $loan->id)
                ->whereNull('returned_at')
                ->update(['returned_at' => PgTime::format($item?->returnedAt())]);

            if ($affected === 0) {
                throw new ItemAlreadyReturned('Esa línea del préstamo ya fue devuelta.');
            }

            // One copy back, exactly once. ck_book_available_range is the last barrier.
            DB::table('book')->where('id', $bookId)->update(['available_copies' => DB::raw('available_copies + 1')]);
        });
    }

    /**
     * @param list<object> $rows
     * @return list<Loan>
     */
    private function hydrateLoans(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $byLoan = [];
        $items = DB::table('loan_item')
            ->whereIn('loan_id', array_map(static fn (object $r): string => $r->id, $rows))
            ->orderBy('due_at')->orderBy('book_title')->orderBy('id')
            ->get();
        foreach ($items as $item) {
            $byLoan[$item->loan_id][] = $this->toItem($item);
        }

        return array_map(static fn (object $r): Loan => Loan::hydrate(
            $r->id,
            $r->member_id,
            $r->loaned_by_user_id,
            PgTime::parseRequired($r->loaned_at),
            $byLoan[$r->id] ?? [],
        ), $rows);
    }

    private function toItem(object $row): LoanItem
    {
        return new LoanItem(
            $row->id,
            $row->loan_id,
            $row->book_id,
            $row->book_title,
            $row->isbn,
            $row->category_name,
            PgTime::parseRequired($row->due_at),
            PgTime::parse($row->returned_at),
        );
    }
}
