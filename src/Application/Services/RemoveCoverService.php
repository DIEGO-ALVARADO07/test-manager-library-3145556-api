<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;
use Library\Domain\Exceptions\NotFound;

final class RemoveCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $storage)
    {
    }

    public function remove(string $bookId): void
    {
        $book = $this->books->find($bookId) ?? throw new NotFound('El libro no existe.');
        $key = $book->coverKey();
        if ($key === null) {
            return;
        }
        $this->books->setCoverKey($bookId, null);   // null the key and commit first ...
        $this->storage->delete($key);                // ... then delete the binary (data-model §7)
    }
}
