<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\DuplicateBookInLoan;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\ItemAlreadyReturned;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\ValueObjects\LoanStatus;
use Library\Domain\ValueObjects\Uuid;

/** Aggregate root: 1 to 3 lines, no repeated book, due 14 days after lending, immutable except returns. */
final class Loan
{
    public const MAX_ITEMS = 3;

    public const TERM_DAYS = 14;

    /** @var list<LoanItem> */
    private array $items;

    /** @param list<LoanItem> $items */
    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        array $items,
    ) {
        $this->items = $items;
    }

    public static function lend(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, []);
    }

    /** @param list<LoanItem> $items */
    public static function hydrate(
        string $id,
        string $memberId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items,
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $items);
    }

    /** Taking the copy and creating the line are one operation (architecture.md §3). */
    public function addItem(Book $book): LoanItem
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new InvalidValue('Un préstamo admite como máximo 3 libros.', [
                ['field' => 'items', 'issue' => 'máximo 3 libros'],
            ]);
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.', [
                    ['field' => 'items', 'issue' => 'libro repetido'],
                ]);
            }
        }

        $book->withdraw();

        $item = new LoanItem(
            Uuid::v4(),
            $this->id,
            $book->id,
            $book->title(),
            $book->isbn->value,
            $book->category()->name,
            $this->loanedAt->modify('+'.self::TERM_DAYS.' days'),
        );
        $this->items[] = $item;

        return $item;
    }

    /** Marks one line as returned. Returning twice never counts twice (CA-07.2). */
    public function returnItem(string $itemId, DateTimeImmutable $now): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                if ($item->isReturned()) {
                    throw new ItemAlreadyReturned('Esa línea del préstamo ya fue devuelta.');
                }
                $item->markReturned($now);

                return $item;
            }
        }

        throw new NotFound('La línea del préstamo no existe.');
    }

    public function status(DateTimeImmutable $now): LoanStatus
    {
        $pending = array_filter($this->items, static fn (LoanItem $i): bool => ! $i->isReturned());
        if ($pending === []) {
            return LoanStatus::Closed;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }
}
