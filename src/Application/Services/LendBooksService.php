<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\BookRepository;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Exceptions\DuplicateBookInLoan;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\LendingPolicy;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\Uuid;

/** HU-06: lends 1 to 3 books to one member. All the lines or nothing. */
final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    /** @param list<string> $bookIds */
    public function lend(string $memberId, array $bookIds, string $registeredByUserId): Loan
    {
        if ($bookIds === [] || count($bookIds) > Loan::MAX_ITEMS) {
            throw new InvalidValue('Un préstamo lleva de 1 a 3 libros.', [['field' => 'items', 'issue' => 'entre 1 y 3 libros']]);
        }
        if (count(array_unique($bookIds)) !== count($bookIds)) {
            throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.', [['field' => 'items', 'issue' => 'libro repetido']]);
        }

        $this->members->find($memberId) ?? throw new NotFound('El socio no existe.');

        $now = $this->clock->now();
        LendingPolicy::assertCanLend($this->loans->unreturnedItemsOfMember($memberId), count($bookIds), $now);

        $found = $this->books->findMany($bookIds);
        foreach ($bookIds as $index => $bookId) {
            if (! isset($found[$bookId])) {
                throw new NotFound('El libro no existe.', [['field' => "items[$index].book_id", 'issue' => 'libro inexistente']]);
            }
        }

        $loan = Loan::lend(Uuid::v4(), $memberId, $registeredByUserId, $now);
        $books = [];
        foreach ($bookIds as $index => $bookId) {
            $book = $found[$bookId];
            try {
                $loan->addItem($book);
            } catch (BookNotAvailable $e) {
                throw new BookNotAvailable($e->getMessage(), [['field' => "items[$index].book_id", 'issue' => 'sin ejemplares']]);
            }
            $books[] = $book;
        }

        $this->loans->saveLending($loan, $books);

        return $loan;
    }
}
