<?php

use Library\Domain\Book;
use Library\Domain\Category;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

function aBook(int $copies = 2): Book
{
    return Book::create('b1', '  Dune ', 'Frank Herbert', new Isbn('9780441013593'), new Category('c1', 'Novela'), new Quantity($copies));
}

it('starts with the available copies equal to the total — CA-03.1', function () {
    $book = aBook(3);

    expect($book->availableCopies())->toBe(3)->and($book->title())->toBe('Dune');
});

it('never lets available copies go below zero — T-04, RN-1', function () {
    $book = aBook(1);
    $book->withdraw();

    expect($book->availableCopies())->toBe(0);
    $book->withdraw();
})->throws(BookNotAvailable::class);

it('never lets available copies exceed the total — CA-07.3', function () {
    aBook(1)->restock();
})->throws(InvalidValue::class);

it('moves the available copies by the same difference as the total — CA-03.3', function () {
    $book = aBook(3);
    $book->withdraw();
    $book->change('Dune', 'Frank Herbert', new Category('c1', 'Novela'), new Quantity(5));

    expect($book->totalCopies())->toBe(5)->and($book->availableCopies())->toBe(4);
});

it('rejects a total that would leave available copies negative — CA-03.3', function () {
    $book = aBook(3);
    $book->withdraw();
    $book->withdraw();
    $book->change('Dune', 'Frank Herbert', new Category('c1', 'Novela'), new Quantity(1));
})->throws(InvalidValue::class);
