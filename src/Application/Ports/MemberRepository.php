<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Application\Pagination\Page;
use Library\Domain\Member;

interface MemberRepository
{
    public function find(string $id): ?Member;

    /** @return Page<Member> */
    public function search(?string $text, int $page, int $size): Page;

    public function documentExists(string $document): bool;

    /** @throws \Library\Domain\Exceptions\DocumentTaken */
    public function insert(Member $member): void;

    /** Lines of the member that are still out. */
    public function activeLoans(string $memberId): int;
}
