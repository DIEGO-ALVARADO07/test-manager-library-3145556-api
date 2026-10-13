<?php

declare(strict_types=1);

namespace Tests\Fakes;

use DateTimeImmutable;
use DateTimeZone;
use Library\Application\Pagination\Page;
use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Book;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Loan;
use Library\Domain\Member;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\LoanStatus;

/** Fixed clock: lets tests jump over the 14-day term without waiting. */
final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $when): self
    {
        return new self(new DateTimeImmutable($when, new DateTimeZone('UTC')));
    }

    public function advanceDays(int $days): void
    {
        $this->now = $this->now->modify("+$days days");
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, array<string, mixed>> persisted rows, so every find() returns a fresh object like a database would */
    public array $rows = [];

    public function add(Book $book): Book
    {
        $this->rows[$book->id] = [
            'title' => $book->title(), 'author' => $book->author(), 'isbn' => $book->isbn, 'category' => $book->category(),
            'total' => $book->totalCopies(), 'available' => $book->availableCopies(), 'cover' => $book->coverKey(),
            'deleted_at' => $book->deletedAt(),
        ];

        return $book;
    }

    public function availableOf(string $id): int
    {
        return $this->rows[$id]['available'];
    }

    public function find(string $id, bool $includeRetired = false): ?Book
    {
        $r = $this->rows[$id] ?? null;
        if ($r === null || ($r['deleted_at'] !== null && ! $includeRetired)) {
            return null;
        }

        return Book::hydrate($id, $r['title'], $r['author'], $r['isbn'], $r['category'], $r['total'], $r['available'], $r['cover'], $r['deleted_at']);
    }

    public function findMany(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            if (($book = $this->find($id)) !== null) {
                $found[$id] = $book;
            }
        }

        return $found;
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        return new Page([], $page, $size, 0);
    }

    public function activeIsbnExists(Isbn $isbn): bool
    {
        return false;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return false;
    }

    public function insert(Book $book): void
    {
        $this->add($book);
    }

    public function update(Book $book): void
    {
        $this->add($book);
    }

    public function setCoverKey(string $bookId, ?string $coverKey): void
    {
    }

    public function retire(Book $book): void
    {
        $this->add($book);
    }
}

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    public array $members = [];

    public function add(Member $member): Member
    {
        return $this->members[$member->id] = $member;
    }

    public function find(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function search(?string $text, int $page, int $size): Page
    {
        return new Page(array_values($this->members), $page, $size, count($this->members));
    }

    public function documentExists(string $document): bool
    {
        return false;
    }

    public function insert(Member $member): void
    {
        $this->add($member);
    }

    public function activeLoans(string $memberId): int
    {
        return 0;
    }
}

/** Keeps loans in memory and applies the same copy bookkeeping as the PostgreSQL adapter. */
final class InMemoryLoanRepository implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $loans = [];

    public function __construct(private readonly InMemoryBookRepository $books)
    {
    }

    public function find(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function search(?DateRange $range, ?LoanStatus $status, ?string $memberId, DateTimeImmutable $now, int $page, int $size): Page
    {
        return new Page(array_values($this->loans), $page, $size, count($this->loans));
    }

    public function unreturnedItemsOfMember(string $memberId): array
    {
        $items = [];
        foreach ($this->loans as $loan) {
            if ($loan->memberId === $memberId) {
                foreach ($loan->items() as $item) {
                    if (! $item->isReturned()) {
                        $items[] = $item;
                    }
                }
            }
        }

        return $items;
    }

    public function overdueItems(DateTimeImmutable $now, int $page, int $size): Page
    {
        return new Page([], $page, $size, 0);
    }

    public function saveLending(Loan $loan, array $books): void
    {
        foreach ($books as $book) {
            if ($this->books->availableOf($book->id) !== $book->availableAtLoad()) {
                throw new BookNotAvailable('conflict');
            }
        }
        foreach ($books as $book) {
            $this->books->add($book);
        }
        $this->loans[$loan->id] = $loan;
    }

    public function saveReturn(Loan $loan, string $itemId, string $bookId): void
    {
        $this->books->rows[$bookId]['available']++;
    }
}
