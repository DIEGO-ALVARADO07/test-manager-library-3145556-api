<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Library\Application\Pagination\Page;
use Library\Application\Ports\BookRepository;
use Library\Domain\Book;
use Library\Domain\Category;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\IsbnTaken;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Uuid;

final class PgBookRepository implements BookRepository
{
    private const COLUMNS = [
        'book.id', 'book.title', 'book.author', 'book.isbn', 'book.category_id', 'book.total_copies',
        'book.available_copies', 'book.cover_key', 'book.deleted_at', 'category.name as category_name',
    ];

    public function find(string $id, bool $includeRetired = false): ?Book
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $query = $this->base()->where('book.id', $id);
        if (! $includeRetired) {
            $query->whereNull('book.deleted_at');
        }
        $row = $query->first();

        return $row === null ? null : $this->toBook($row);
    }

    public function findMany(array $ids): array
    {
        $books = [];
        foreach ($this->base()->whereIn('book.id', $ids)->whereNull('book.deleted_at')->get() as $row) {
            $books[$row->id] = $this->toBook($row);
        }

        return $books;
    }

    public function search(?string $text, ?string $categoryId, int $page, int $size): Page
    {
        $query = $this->base()->whereNull('book.deleted_at');
        if ($text !== null && trim($text) !== '') {
            $like = PgTime::likeContains(trim($text));
            $query->where(static function ($q) use ($like): void {
                $q->whereRaw("book.title ILIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("book.author ILIKE ? ESCAPE '\\'", [$like]);
            });
        }
        if ($categoryId !== null) {
            $query->where('book.category_id', $categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('book.title')->orderBy('book.id')->forPage($page, $size)->get();

        return new Page($rows->map(fn (object $r): Book => $this->toBook($r))->all(), $page, $size, $total);
    }

    public function activeIsbnExists(Isbn $isbn): bool
    {
        return DB::table('book')->where('isbn', $isbn->value)->whereNull('deleted_at')->exists();
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    public function insert(Book $book): void
    {
        try {
            DB::table('book')->insert([
                'id' => $book->id,
                'title' => $book->title(),
                'author' => $book->author(),
                'isbn' => $book->isbn->value,
                'category_id' => $book->category()->id,
                'total_copies' => $book->totalCopies(),
                'available_copies' => $book->availableCopies(),
                'cover_key' => $book->coverKey(),
                'deleted_at' => PgTime::format($book->deletedAt()),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new IsbnTaken('Ya existe un libro activo con ese ISBN.');
            }
            throw $e;
        }
    }

    public function update(Book $book): void
    {
        $affected = DB::table('book')
            ->where('id', $book->id)
            ->where('available_copies', $book->availableAtLoad())
            ->where('total_copies', $book->totalAtLoad())
            ->whereNull('deleted_at')
            ->update([
                'title' => $book->title(),
                'author' => $book->author(),
                'category_id' => $book->category()->id,
                'total_copies' => $book->totalCopies(),
                'available_copies' => $book->availableCopies(),
            ]);

        if ($affected === 0) {
            throw new BookNotAvailable('El libro cambió mientras se editaba; intente de nuevo.');
        }
    }

    public function setCoverKey(string $bookId, ?string $coverKey): void
    {
        DB::table('book')->where('id', $bookId)->update(['cover_key' => $coverKey]);
    }

    public function retire(Book $book): void
    {
        DB::table('book')->where('id', $book->id)->whereNull('deleted_at')
            ->update(['deleted_at' => PgTime::format($book->deletedAt())]);
    }

    private function base(): \Illuminate\Database\Query\Builder
    {
        return DB::table('book')->join('category', 'category.id', '=', 'book.category_id')->select(self::COLUMNS);
    }

    private function toBook(object $row): Book
    {
        return Book::hydrate(
            $row->id,
            $row->title,
            $row->author,
            new Isbn($row->isbn),
            new Category($row->category_id, $row->category_name),
            (int) $row->total_copies,
            (int) $row->available_copies,
            $row->cover_key,
            PgTime::parse($row->deleted_at),
        );
    }
}
