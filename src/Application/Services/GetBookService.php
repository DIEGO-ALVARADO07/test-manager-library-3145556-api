<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Domain\Book;
use Library\Domain\Exceptions\NotFound;

final class GetBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function get(string $id): Book
    {
        return $this->books->find($id) ?? throw new NotFound('El libro no existe.');
    }
}
