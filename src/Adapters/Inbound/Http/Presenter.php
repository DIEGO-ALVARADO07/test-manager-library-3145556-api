<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http;

use DateTimeImmutable;
use DateTimeZone;
use Library\Application\Pagination\Page;
use Library\Domain\Book;
use Library\Domain\Loan;
use Library\Domain\LoanItem;
use Library\Domain\Member;
use Library\Domain\User;

/** Builds the JSON shapes of api-contract §3. Never includes password or password_hash. */
final class Presenter
{
    public static function date(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array<string, mixed> */
    public static function book(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn->value,
            'category' => ['id' => $book->category()->id, 'name' => $book->category()->name],
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
        ];
    }

    /** @return array<string, mixed> */
    public static function member(Member $member): array
    {
        return ['id' => $member->id, 'full_name' => $member->fullName, 'document_number' => $member->document->value];
    }

    /** @return array<string, mixed> */
    public static function user(User $user): array
    {
        return ['id' => $user->id, 'username' => $user->username, 'role' => $user->role->value];
    }

    /** @return array<string, mixed> */
    public static function item(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'book_id' => $item->bookId,
            'book_title' => $item->bookTitle,
            'isbn' => $item->isbn,
            'category_name' => $item->categoryName,
            'due_at' => self::date($item->dueAt),
            'returned_at' => self::date($item->returnedAt()),
        ];
    }

    /** @return array<string, mixed> */
    public static function loan(Loan $loan, DateTimeImmutable $now): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => self::date($loan->loanedAt),
            'status' => $loan->status($now)->value,
            'items' => array_map(self::item(...), $loan->items()),
        ];
    }

    /**
     * @param callable(mixed): array<string, mixed> $map
     * @return array<string, mixed>
     */
    public static function page(Page $page, callable $map): array
    {
        return [
            'items' => array_map($map, $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ];
    }
}
