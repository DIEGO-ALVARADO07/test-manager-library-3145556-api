<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Domain\Exceptions\BookHasOpenLoans;
use Library\Domain\Exceptions\NotFound;

/** CA-03.5: soft delete; a book with open loans is not removed. */
final class DeleteBookService
{
    public function __construct(private readonly BookRepository $books, private readonly Clock $clock)
    {
    }

    public function delete(string $id): void
    {
        $book = $this->books->find($id) ?? throw new NotFound('El libro no existe.');
        if ($this->books->hasOpenLoans($id)) {
            throw new BookHasOpenLoans('El libro tiene préstamos abiertos y no se puede dar de baja.');
        }
        $book->retire($this->clock->now());
        $this->books->retire($book);
    }
}
