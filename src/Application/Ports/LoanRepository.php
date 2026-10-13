<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use DateTimeImmutable;
use Library\Application\Pagination\Page;
use Library\Domain\Book;
use Library\Domain\Loan;
use Library\Domain\LoanItem;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\LoanStatus;

interface LoanRepository
{
    public function find(string $id): ?Loan;

    /** @return Page<Loan> */
    public function search(
        ?DateRange $range,
        ?LoanStatus $status,
        ?string $memberId,
        DateTimeImmutable $now,
        int $page,
        int $size,
    ): Page;

    /** @return list<LoanItem> */
    public function unreturnedItemsOfMember(string $memberId): array;

    /**
     * Overdue lines, the oldest due date first.
     *
     * @return Page<array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: DateTimeImmutable}>
     */
    public function overdueItems(DateTimeImmutable $now, int $page, int $size): Page;

    /**
     * Stores the loan and takes the copies in ONE transaction.
     *
     * @param list<Book> $books
     * @throws \Library\Domain\Exceptions\BookNotAvailable when a copy was taken by someone else
     */
    public function saveLending(Loan $loan, array $books): void;

    /**
     * Marks the line as returned and gives the copy back in ONE transaction.
     *
     * @throws \Library\Domain\Exceptions\ItemAlreadyReturned
     */
    public function saveReturn(Loan $loan, string $itemId, string $bookId): void;
}
