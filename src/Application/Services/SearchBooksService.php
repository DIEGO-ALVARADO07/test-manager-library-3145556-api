<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Pagination\Page;
use Library\Application\Ports\BookRepository;
use Library\Domain\Book;

/** HU-04: partial text in title or author, category filter, active books, ordered by title. */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    /** @return Page<Book> */
    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        return $this->books->search($text, $categoryId, $page, $size);
    }
}
