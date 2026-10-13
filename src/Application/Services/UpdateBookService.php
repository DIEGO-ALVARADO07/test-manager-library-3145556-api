<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryReader;
use Library\Domain\Book;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\ValueObjects\Quantity;

/** PUT /books/{id}: replaces title, author, category and total. The ISBN never changes (H-09). */
final class UpdateBookService
{
    public function __construct(private readonly BookRepository $books, private readonly CategoryReader $categories)
    {
    }

    public function update(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $quantity = new Quantity($totalCopies);
        $category = $this->categories->find($categoryId)
            ?? throw new InvalidValue('La categoría no existe.', [['field' => 'category_id', 'issue' => 'categoría inexistente']]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $book = $this->books->find($id) ?? throw new NotFound('El libro no existe.');
            $book->change($title, $author, $category, $quantity);
            try {
                $this->books->update($book);

                return $book;
            } catch (BookNotAvailable) {
                // a copy was lent or returned between the read and the write: read again once
            }
        }

        throw new BookNotAvailable('El libro cambió mientras se editaba; intente de nuevo.');
    }
}
