<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CategoryReader;
use Library\Domain\Book;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\IsbnTaken;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;
use Library\Domain\ValueObjects\Uuid;

/** HU-03 / CA-03.1, CA-03.2: available copies start equal to the total. */
final class CreateBookService
{
    public function __construct(private readonly BookRepository $books, private readonly CategoryReader $categories)
    {
    }

    public function create(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $isbn = new Isbn($isbn);
        $quantity = new Quantity($totalCopies);
        $category = $this->categories->find($categoryId)
            ?? throw new InvalidValue('La categoría no existe.', [['field' => 'category_id', 'issue' => 'categoría inexistente']]);

        if ($this->books->activeIsbnExists($isbn)) {
            throw new IsbnTaken('Ya existe un libro activo con ese ISBN.');
        }

        $book = Book::create(Uuid::v4(), $title, $author, $isbn, $category, $quantity);
        $this->books->insert($book);

        return $book;
    }
}
