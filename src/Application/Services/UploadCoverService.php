<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\CoverStorage;
use Library\Domain\Book;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\ValueObjects\Uuid;

/** CA-03.4: JPG or PNG up to 2 MB, checked by content. The database keeps only an opaque key. */
final class UploadCoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $storage)
    {
    }

    public function upload(string $bookId, string $contents): Book
    {
        $book = $this->books->find($bookId) ?? throw new NotFound('El libro no existe.');

        if ($contents === '' || strlen($contents) > self::MAX_BYTES) {
            throw new InvalidValue('La portada debe pesar como máximo 2 MB.', [['field' => 'file', 'issue' => 'tamaño máximo 2 MB']]);
        }
        if (! in_array(self::mimeOf($contents), ['image/jpeg', 'image/png'], true)) {
            throw new InvalidValue('La portada debe ser una imagen JPG o PNG.', [['field' => 'file', 'issue' => 'solo JPG o PNG']]);
        }

        $oldKey = $book->coverKey();
        $newKey = Uuid::v4();
        $this->storage->put($newKey, $contents);
        $this->books->setCoverKey($bookId, $newKey);   // commit the new key first ...
        if ($oldKey !== null) {
            $this->storage->delete($oldKey);            // ... then delete the old binary (data-model §7)
        }

        return $this->books->find($bookId) ?? throw new NotFound('El libro no existe.');
    }

    public static function mimeOf(string $contents): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
    }
}
