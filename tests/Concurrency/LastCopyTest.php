<?php

use Illuminate\Support\Facades\DB;
use Library\Adapters\Outbound\Persistence\PgBookRepository;
use Library\Adapters\Outbound\Persistence\PgLoanRepository;
use Library\Domain\Exceptions\BookNotAvailable;
use Library\Domain\Loan;
use Library\Domain\ValueObjects\Uuid;

/** Starts one worker process per member; returns their exit codes. */
function runWorkers(array $memberIds, string $bookId, string $userId): array
{
    $startAt = microtime(true) + 2.5;
    $processes = [];
    foreach ($memberIds as $memberId) {
        $command = [PHP_BINARY, __DIR__.'/lend_worker.php', $memberId, $bookId, $userId, (string) $startAt];
        $processes[] = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    }

    $codes = [];
    foreach ($processes as $process) {
        // proc_close returns the exit code once the child has finished.
        $codes[] = proc_close($process);
    }
    sort($codes);

    return $codes;
}

it('lets exactly one of two simultaneous requests take the last copy — RNF-2', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['total_copies' => 1]);
    $first = makeMember($admin);
    $second = makeMember($admin);
    $userId = DB::table('user')->value('id');

    $codes = runWorkers([$first['id'], $second['id']], $book['id'], $userId);

    expect($codes)->toBe([0, 3])
        ->and(DB::table('loan')->count())->toBe(1)
        ->and(DB::table('book')->where('id', $book['id'])->value('available_copies'))->toBe(0);
});

it('never lends more copies than exist when five requests compete for two — RNF-2', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['total_copies' => 2]);
    $members = array_map(static fn () => makeMember($admin)['id'], range(1, 5));
    $userId = DB::table('user')->value('id');

    $codes = runWorkers($members, $book['id'], $userId);

    expect(array_count_values($codes)[0] ?? 0)->toBe(2)
        ->and(array_count_values($codes)[3] ?? 0)->toBe(3)
        ->and(DB::table('loan')->count())->toBe(2)
        ->and((int) DB::table('book')->where('id', $book['id'])->value('available_copies'))->toBe(0);
});

it('detects a stale read at the repository level with compare-and-set — ADR-005', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['total_copies' => 1]);
    $first = makeMember($admin);
    $second = makeMember($admin);
    $userId = DB::table('user')->value('id');
    $books = new PgBookRepository();
    $loans = new PgLoanRepository();
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    // Both "requests" read the book while one copy is still available ...
    $readByFirst = $books->find($book['id']);
    $readBySecond = $books->find($book['id']);

    $loanOne = Loan::lend(Uuid::v4(), $first['id'], $userId, $now);
    $loanOne->addItem($readByFirst);
    $loanTwo = Loan::lend(Uuid::v4(), $second['id'], $userId, $now);
    $loanTwo->addItem($readBySecond);

    // ... the first write wins, the second one finds the value changed and is rejected.
    $loans->saveLending($loanOne, [$readByFirst]);
    expect(fn () => $loans->saveLending($loanTwo, [$readBySecond]))->toThrow(BookNotAvailable::class);

    expect(DB::table('loan')->count())->toBe(1);
});
