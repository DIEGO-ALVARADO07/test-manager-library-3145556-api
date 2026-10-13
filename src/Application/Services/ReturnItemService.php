<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\LoanItem;

/** HU-07: returns one line; the copy goes back once, in the same operation. */
final class ReturnItemService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    public function return(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw new NotFound('El préstamo no existe.');
        $item = $loan->returnItem($itemId, $this->clock->now());
        $this->loans->saveReturn($loan, $itemId, $item->bookId);

        return $item;
    }
}
