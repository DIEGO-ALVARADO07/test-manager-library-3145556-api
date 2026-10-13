<?php

use Illuminate\Support\Facades\DB;
use Library\Application\Services\BootstrapAdminService;
use Library\Application\Services\CreateLibrarianService;
use Tests\TestCase;

uses(TestCase::class)->in('Integration', 'Contract', 'Concurrency');

const CATEGORY_NOVEL = '11111111-1111-4111-8111-111111111111';
const CATEGORY_SCIENCE = '22222222-2222-4222-8222-222222222222';

/** Logs in as a fresh user of the given role and returns the Authorization header. */
function authAs(string $role = 'admin'): array
{
    static $counter = 0;
    $counter++;
    $password = 'Secret-pass-'.$counter;

    if ($role === 'admin') {
        $username = 'admin'.$counter;
        app(BootstrapAdminService::class)->ensure($username, $password);
    } else {
        $username = 'ana'.$counter;
        app(CreateLibrarianService::class)->create($username, $password, 'librarian');
    }

    $token = test()->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password])->json('access_token');

    return ['Authorization' => 'Bearer '.$token];
}

/** Creates a book through the API and returns its JSON. */
function makeBook(array $headers, array $overrides = []): array
{
    static $isbn = 9780000000000;
    $isbn++;

    return test()->postJson('/api/v1/books', $overrides + [
        'title' => 'Cien años de soledad',
        'author' => 'Gabriel García Márquez',
        'isbn' => (string) $isbn,
        'category_id' => CATEGORY_NOVEL,
        'total_copies' => 2,
    ], $headers)->assertCreated()->json();
}

function makeMember(array $headers, string $name = 'Laura Pérez'): array
{
    static $doc = 1000;
    $doc++;

    return test()->postJson('/api/v1/members', ['full_name' => $name, 'document_number' => (string) $doc], $headers)
        ->assertCreated()->json();
}

/** Lends the given book ids to a member and returns the response. */
function lend(array $headers, string $memberId, array $bookIds)
{
    return test()->postJson('/api/v1/loans', [
        'member_id' => $memberId,
        'items' => array_map(static fn (string $id): array => ['book_id' => $id], $bookIds),
    ], $headers);
}

/** Moves a loan into the past by rewriting its dates (simulates the passing of time). */
function ageLoan(string $loanId, int $days): void
{
    DB::statement('UPDATE library.loan SET loaned_at = loaned_at - make_interval(days => ?) WHERE id = ?', [$days, $loanId]);
    DB::statement('UPDATE library.loan_item SET due_at = due_at - make_interval(days => ?) WHERE loan_id = ?', [$days, $loanId]);
}
