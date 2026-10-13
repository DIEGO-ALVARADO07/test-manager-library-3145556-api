<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Pagination\Page;
use Library\Application\Ports\Clock;
use Library\Application\Ports\LoanRepository;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\DateRange;
use Library\Domain\ValueObjects\LoanStatus;

/** HU-08: by date range, status and member; newest first. The status is calculated in the query. */
final class ListLoansService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    /** @return Page<Loan> */
    public function list(?DateRange $range, ?LoanStatus $status, ?string $memberId, int $page, int $size): Page
    {
        return $this->loans->search($range, $status, $memberId, $this->clock->now(), $page, $size);
    }
}
