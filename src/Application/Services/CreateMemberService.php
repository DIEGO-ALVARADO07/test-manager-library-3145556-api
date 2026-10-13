<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\MemberRepository;
use Library\Domain\Exceptions\DocumentTaken;
use Library\Domain\Member;
use Library\Domain\ValueObjects\DocumentNumber;
use Library\Domain\ValueObjects\Uuid;

/** HU-05 / CA-05.1 */
final class CreateMemberService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    public function create(string $fullName, string $document): Member
    {
        $member = new Member(Uuid::v4(), $fullName, new DocumentNumber($document));
        if ($this->members->documentExists($member->document->value)) {
            throw new DocumentTaken('Ya existe un socio con ese documento.');
        }
        $this->members->insert($member);

        return $member;
    }
}
