<?php

/**
 * Child process used by LastCopyTest: boots the application and lends one book.
 * Exit codes: 0 = loan registered, 3 = BOOK_NOT_AVAILABLE, 1 = unexpected error.
 */

use Library\Application\Services\LendBooksService;
use Library\Domain\Exceptions\BookNotAvailable;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $memberId, $bookId, $userId, $startAt] = $argv;

// Both workers wait for the same instant so their requests collide.
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $app->make(LendBooksService::class)->lend($memberId, [$bookId], $userId);
    exit(0);
} catch (BookNotAvailable) {
    exit(3);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(1);
}
