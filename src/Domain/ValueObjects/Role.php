<?php

declare(strict_types=1);

namespace Library\Domain\ValueObjects;

enum Role: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';
}
