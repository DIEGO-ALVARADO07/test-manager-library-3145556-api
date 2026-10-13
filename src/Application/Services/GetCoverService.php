<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;
use Library\Domain\Exceptions\NotFound;

final class GetCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $storage)
    {
    }

    /** @return array{contents: string, mime: string} */
    public function get(string $bookId): array
    {
        $book = $this->books->find($bookId) ?? throw new NotFound('El libro no existe.');
        $key = $book->coverKey() ?? throw new NotFound('El libro no tiene portada.');
        $contents = $this->storage->get($key) ?? throw new NotFound('El libro no tiene portada.');

        return ['contents' => $contents, 'mime' => UploadCoverService::mimeOf($contents)];
    }
}
