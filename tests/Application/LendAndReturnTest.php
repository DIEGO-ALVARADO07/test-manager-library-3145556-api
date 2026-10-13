<?php

// T-09, T-10 — use cases run against fakes: no database, no framework, no adapters (Art. II).

use Library\Application\Services\LendBooksService;
use Library\Application\Services\ReturnItemService;
use Library\Domain\Book;
use Library\Domain\Category;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\DuplicateBookInLoan;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Exceptions\MemberHasOverdue;
use Library\Domain\Exceptions\MemberLimitExceeded;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\Member;
use Library\Domain\ValueObjects\DocumentNumber;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Library\Domain\ValueObjects\Uuid;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;

beforeEach(function () {
    $this->clock = FixedClock::at('2026-10-14 14:15:00');
    $this->books = new InMemoryBookRepository();
    $this->members = new InMemoryMemberRepository();
    $this->loans = new InMemoryLoanRepository($this->books);
    $this->lend = new LendBooksService($this->members, $this->books, $this->loans, $this->clock);
    $this->return = new ReturnItemService($this->loans, $this->clock);
    $this->member = $this->members->add(new Member(Uuid::v4(), 'Laura Pérez', new DocumentNumber('1001')));

    $this->book = function (int $copies = 1): Book {
        static $n = 0;
        $n++;

        return $this->books->add(Book::create(Uuid::v4(), "Libro $n", 'Autor', new Isbn(str_pad((string) $n, 13, '0', STR_PAD_LEFT)), new Category('c', 'Ciencia'), new Quantity($copies)));
    };
});

it('lends and takes one copy per book — HU-06', function () {
    $book = ($this->book)(2);

    $loan = $this->lend->lend($this->member->id, [$book->id], 'user-1');

    expect($loan->items())->toHaveCount(1)
        ->and($this->books->availableOf($book->id))->toBe(1)
        ->and($loan->items()[0]->dueAt->format('Y-m-d H:i:s'))->toBe('2026-10-28 14:15:00');
});

it('registers nothing when one of the books has no copies — CA-06.3', function () {
    $free = ($this->book)(1);
    $empty = ($this->book)(1);
    $empty->withdraw();
    $this->books->add($empty);

    expect(fn () => $this->lend->lend($this->member->id, [$free->id, $empty->id], 'user-1'))->toThrow(BookNotAvailable::class);

    expect($this->loans->loans)->toBe([])
        ->and($this->books->availableOf($free->id))->toBe(1);
});

it('refuses repeated books, empty or oversized loans and unknown ids', function () {
    $book = ($this->book)(2);

    expect(fn () => $this->lend->lend($this->member->id, [$book->id, $book->id], 'u'))->toThrow(DuplicateBookInLoan::class)
        ->and(fn () => $this->lend->lend($this->member->id, [], 'u'))->toThrow(InvalidValue::class)
        ->and(fn () => $this->lend->lend($this->member->id, ['a', 'b', 'c', 'd'], 'u'))->toThrow(InvalidValue::class)
        ->and(fn () => $this->lend->lend(Uuid::v4(), [$book->id], 'u'))->toThrow(NotFound::class)
        ->and(fn () => $this->lend->lend($this->member->id, [Uuid::v4()], 'u'))->toThrow(NotFound::class);
});

it('limits a member to 3 unreturned books in total — CA-06.4', function () {
    $this->lend->lend($this->member->id, [($this->book)()->id, ($this->book)()->id], 'u');

    expect(fn () => $this->lend->lend($this->member->id, [($this->book)()->id, ($this->book)()->id], 'u'))
        ->toThrow(MemberLimitExceeded::class);
});

it('blocks a member whose book is overdue until it is returned — CA-06.5', function () {
    $loan = $this->lend->lend($this->member->id, [($this->book)()->id], 'u');
    $this->clock->advanceDays(15);

    expect(fn () => $this->lend->lend($this->member->id, [($this->book)()->id], 'u'))->toThrow(MemberHasOverdue::class);

    $this->return->return($loan->id, $loan->items()[0]->id);
    expect($this->lend->lend($this->member->id, [($this->book)()->id], 'u')->items())->toHaveCount(1);
});

it('gives the copy back once and refuses a second return — CA-07.1, CA-07.2', function () {
    $book = ($this->book)(1);
    $loan = $this->lend->lend($this->member->id, [$book->id], 'u');
    $itemId = $loan->items()[0]->id;

    $this->return->return($loan->id, $itemId);
    expect($this->books->availableOf($book->id))->toBe(1);

    expect(fn () => $this->return->return($loan->id, $itemId))->toThrow(ItemAlreadyReturned::class)
        ->and($this->books->availableOf($book->id))->toBe(1);
});

it('answers not found for an unknown loan or line', function () {
    $loan = $this->lend->lend($this->member->id, [($this->book)()->id], 'u');

    expect(fn () => $this->return->return(Uuid::v4(), Uuid::v4()))->toThrow(NotFound::class)
        ->and(fn () => $this->return->return($loan->id, Uuid::v4()))->toThrow(NotFound::class);
});
