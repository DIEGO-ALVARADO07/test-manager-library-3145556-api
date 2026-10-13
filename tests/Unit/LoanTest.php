<?php

use Library\Domain\Book;
use Library\Domain\Category;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\DuplicateBookInLoan;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Exceptions\MemberHasOverdue;
use Library\Domain\Exceptions\MemberLimitExceeded;
use Library\Domain\LendingPolicy;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\LoanStatus;
use Library\Domain\ValueObjects\Quantity;

function bookNo(int $n, int $copies = 1): Book
{
    return Book::create("book-$n", "Libro $n", 'Autor', new Isbn(str_pad((string) $n, 13, '0', STR_PAD_LEFT)), new Category('c', 'Ciencia'), new Quantity($copies));
}

function at(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when, new DateTimeZone('UTC'));
}

it('freezes the book data and sets the due date 14 days later — CA-06.6', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-14 14:15:00'));
    $item = $loan->addItem(bookNo(1));

    expect($item->dueAt->format('Y-m-d H:i:s'))->toBe('2026-10-28 14:15:00')
        ->and($item->bookTitle)->toBe('Libro 1')
        ->and($item->categoryName)->toBe('Ciencia');
});

it('takes one copy per line in the same operation — CA-06.3', function () {
    $book = bookNo(1, 2);
    Loan::lend('l1', 'm1', 'u1', at('2026-10-14'))->addItem($book);

    expect($book->availableCopies())->toBe(1);
});

it('rejects a line when no copies are left — CA-06.3', function () {
    $book = bookNo(1, 1);
    $book->withdraw();
    Loan::lend('l1', 'm1', 'u1', at('2026-10-14'))->addItem($book);
})->throws(BookNotAvailable::class);

it('does not repeat a book in a loan — CA-06.2', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-14'));
    $book = bookNo(1, 2);
    $loan->addItem($book);
    $loan->addItem($book);
})->throws(DuplicateBookInLoan::class);

it('allows at most 3 lines — CA-06.1', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-14'));
    foreach ([1, 2, 3, 4] as $n) {
        $loan->addItem(bookNo($n));
    }
})->throws(InvalidValue::class);

it('calculates open, overdue and closed with a fixed clock — T-05, CA-08.3', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-01 10:00:00'));
    $a = $loan->addItem(bookNo(1));
    $b = $loan->addItem(bookNo(2));

    expect($loan->status(at('2026-10-10')))->toBe(LoanStatus::Open)
        ->and($loan->status(at('2026-10-16')))->toBe(LoanStatus::Overdue);

    $loan->returnItem($a->id, at('2026-10-16'));
    expect($loan->status(at('2026-10-16')))->toBe(LoanStatus::Overdue);

    $loan->returnItem($b->id, at('2026-10-17'));
    expect($loan->status(at('2026-10-17')))->toBe(LoanStatus::Closed);
});

it('never returns the same line twice — CA-07.2', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-01'));
    $item = $loan->addItem(bookNo(1));
    $loan->returnItem($item->id, at('2026-10-02'));
    $loan->returnItem($item->id, at('2026-10-03'));
})->throws(ItemAlreadyReturned::class);

it('blocks a member with overdue books before checking the limit — CA-06.5', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-01'));
    $loan->addItem(bookNo(1));

    LendingPolicy::assertCanLend($loan->items(), 1, at('2026-10-20'));
})->throws(MemberHasOverdue::class);

it('blocks a fourth unreturned book and reports the current count — CA-06.4', function () {
    $loan = Loan::lend('l1', 'm1', 'u1', at('2026-10-01'));
    foreach ([1, 2, 3] as $n) {
        $loan->addItem(bookNo($n));
    }

    try {
        LendingPolicy::assertCanLend($loan->items(), 1, at('2026-10-05'));
        $this->fail('Expected MemberLimitExceeded');
    } catch (MemberLimitExceeded $e) {
        expect($e->details())->toBe([['field' => 'member_id', 'issue' => 'active_loans=3']]);
    }
});
