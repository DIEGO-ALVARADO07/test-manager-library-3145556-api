<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Library\Application\Pagination\Page;
use Library\Application\Ports\MemberRepository;
use Library\Domain\Exceptions\DocumentTaken;
use Library\Domain\Member;
use Library\Domain\ValueObjects\DocumentNumber;
use Library\Domain\ValueObjects\Uuid;

final class PgMemberRepository implements MemberRepository
{
    public function find(string $id): ?Member
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = DB::table('member')->where('id', $id)->first();

        return $row === null ? null : $this->toMember($row);
    }

    public function search(?string $text, int $page, int $size): Page
    {
        $query = DB::table('member');
        if ($text !== null && trim($text) !== '') {
            $like = PgTime::likeContains(trim($text));
            $query->where(static function ($q) use ($like): void {
                $q->whereRaw("full_name ILIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("document_number ILIKE ? ESCAPE '!'", [$like]);
            });
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->forPage($page, $size)->get();

        return new Page($rows->map(fn (object $r): Member => $this->toMember($r))->all(), $page, $size, $total);
    }

    public function documentExists(string $document): bool
    {
        return DB::table('member')->where('document_number', $document)->exists();
    }

    public function insert(Member $member): void
    {
        try {
            DB::table('member')->insert([
                'id' => $member->id,
                'full_name' => $member->fullName,
                'document_number' => $member->document->value,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new DocumentTaken('Ya existe un socio con ese documento.');
            }
            throw $e;
        }
    }

    public function activeLoans(string $memberId): int
    {
        return DB::table('loan_item')
            ->join('loan', 'loan.id', '=', 'loan_item.loan_id')
            ->where('loan.member_id', $memberId)
            ->whereNull('loan_item.returned_at')
            ->count();
    }

    private function toMember(object $row): Member
    {
        return new Member($row->id, $row->full_name, new DocumentNumber($row->document_number));
    }
}
