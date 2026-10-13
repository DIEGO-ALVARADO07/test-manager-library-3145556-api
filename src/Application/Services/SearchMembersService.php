<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Pagination\Page;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Member;

final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /** @return Page<Member> */
    public function search(?string $text, int $page, int $size): Page
    {
        return $this->members->search($text, $page, $size);
    }
}
