<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;

/** One book inside a loan, with the values frozen at lending time (Art. VIII). */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $loanId,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    public function markReturned(DateTimeImmutable $now): void
    {
        $this->returnedAt = $now;
    }
}
