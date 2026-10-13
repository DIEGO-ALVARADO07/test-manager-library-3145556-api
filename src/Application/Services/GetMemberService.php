<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\MemberRepository;
use Library\Domain\Exceptions\NotFound;
use Library\Domain\Member;

final class GetMemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /** @return array{member: Member, active_loans: int} */
    public function get(string $id): array
    {
        $member = $this->members->find($id) ?? throw new NotFound('El socio no existe.');

        return ['member' => $member, 'active_loans' => $this->members->activeLoans($id)];
    }
}
