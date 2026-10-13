<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\ValueObjects\Isbn;
use Library\Domain\ValueObjects\Quantity;

/** Aggregate root of the catalog: defends 0 <= available <= total, total >= 1 and the soft delete. */
final class Book
{
    private int $availableAtLoad;

    private int $totalAtLoad;

    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        public readonly Isbn $isbn,
        private Category $category,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {
        $this->availableAtLoad = $availableCopies;
        $this->totalAtLoad = $totalCopies;
    }

    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        Category $category,
        Quantity $totalCopies,
    ): self {
        [$title, $author] = self::cleanText($title, $author);

        return new self($id, $title, $author, $isbn, $category, $totalCopies->value, $totalCopies->value, null, null);
    }

    /** Rebuilds a stored book (used by the persistence adapter). */
    public static function hydrate(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        Category $category,
        int $totalCopies,
        int $availableCopies,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self($id, $title, $author, $isbn, $category, $totalCopies, $availableCopies, $coverKey, $deletedAt);
    }

    /** Takes one copy. Never leaves available below zero. */
    public function withdraw(): void
    {
        if ($this->availableCopies < 1) {
            throw new BookNotAvailable(sprintf('No quedan ejemplares disponibles de «%s».', $this->title));
        }
        $this->availableCopies--;
    }

    /** Gives one copy back. Never exceeds the total. */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new InvalidValue('Los ejemplares disponibles no pueden superar el total.');
        }
        $this->availableCopies++;
    }

    /** CA-03.3: the available copies move by the same difference as the total and never go below zero. */
    public function change(string $title, string $author, Category $category, Quantity $totalCopies): void
    {
        [$title, $author] = self::cleanText($title, $author);
        $available = $this->availableCopies + ($totalCopies->value - $this->totalCopies);
        if ($available < 0) {
            throw new InvalidValue('El nuevo total deja ejemplares disponibles en negativo.', [
                ['field' => 'total_copies', 'issue' => 'menor que los ejemplares prestados'],
            ]);
        }
        $this->title = $title;
        $this->author = $author;
        $this->category = $category;
        $this->totalCopies = $totalCopies->value;
        $this->availableCopies = $available;
    }

    public function retire(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function isRetired(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** Values read from the store: the optimistic-concurrency witness (ADR-005). */
    public function availableAtLoad(): int
    {
        return $this->availableAtLoad;
    }

    public function totalAtLoad(): int
    {
        return $this->totalAtLoad;
    }

    /** @return array{0: string, 1: string} */
    private static function cleanText(string $title, string $author): array
    {
        $title = trim($title);
        $author = trim($author);
        if ($title === '' || mb_strlen($title) > 250) {
            throw new InvalidValue('El título es obligatorio y admite hasta 250 caracteres.', [
                ['field' => 'title', 'issue' => 'obligatorio, máximo 250 caracteres'],
            ]);
        }
        if ($author === '' || mb_strlen($author) > 200) {
            throw new InvalidValue('El autor es obligatorio y admite hasta 200 caracteres.', [
                ['field' => 'author', 'issue' => 'obligatorio, máximo 200 caracteres'],
            ]);
        }

        return [$title, $author];
    }
}
