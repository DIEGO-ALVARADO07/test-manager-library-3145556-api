<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

/** Calculated, never stored (Art. VII). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
