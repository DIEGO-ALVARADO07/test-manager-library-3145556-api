<?php

declare(strict_types=1);

namespace Library\Domain;

use DateTimeImmutable;
use Library\Domain\Exceptions\MemberHasOverdue;
use Library\Domain\Exceptions\MemberLimitExceeded;

/** Pure rule: who may borrow. Overdue is checked first, then the limit of 3 (architecture.md §4). */
final class LendingPolicy
{
    public const MAX_UNRETURNED = 3;

    /** @param list<LoanItem> $unreturned lines of the member that are still out */
    public static function assertCanLend(array $unreturned, int $newItems, DateTimeImmutable $now): void
    {
        foreach ($unreturned as $item) {
            if ($item->isOverdue($now)) {
                throw new MemberHasOverdue('El socio tiene libros vencidos y no puede pedir más hasta devolverlos.');
            }
        }

        $current = count($unreturned);
        if ($current + $newItems > self::MAX_UNRETURNED) {
            throw new MemberLimitExceeded(
                sprintf('El socio ya tiene %d libro(s) sin devolver y el máximo es 3.', $current),
                [['field' => 'member_id', 'issue' => 'active_loans='.$current]],
            );
        }
    }
}
