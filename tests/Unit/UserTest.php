<?php

use Library\Domain\User;
use Library\Domain\ValueObjects\Role;

it('normalizes the username to lowercase and trimmed — T-06, CA-02.2', function () {
    $user = new User('u1', 'Ana ', 'hash', Role::Librarian);

    expect($user->username)->toBe('ana');
});
