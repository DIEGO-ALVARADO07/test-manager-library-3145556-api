<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\Pagination\Page;
use Library\Domain\Book;
use Library\Domain\ValueObjects\Isbn;

interface BookRepository
{
    /** Active books only unless $includeRetired is true. */
    public function find(string $id, bool $includeRetired = false): ?Book;

    /**
     * @param list<string> $ids
     * @return array<string, Book> active books indexed by id
     */
    public function findMany(array $ids): array;

    /** @return Page<Book> */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page;

    public function activeIsbnExists(Isbn $isbn): bool;

    public function hasOpenLoans(string $bookId): bool;

    /** @throws \Library\Domain\Exceptions\IsbnTaken */
    public function insert(Book $book): void;

    /** Compare-and-set on the values read (ADR-005). @throws \Library\Domain\Exceptions\BookNotAvailable */
    public function update(Book $book): void;

    public function setCoverKey(string $bookId, ?string $coverKey): void;

    public function retire(Book $book): void;
}
