<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\LoanRepository;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\Loan;

final class GetLoanService
{
    public function __construct(private readonly LoanRepository $loans)
    {
    }

    public function get(string $id): Loan
    {
        return $this->loans->find($id) ?? throw new NotFound('El préstamo no existe.');
    }
}
