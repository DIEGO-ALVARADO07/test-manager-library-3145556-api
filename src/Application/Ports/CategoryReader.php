<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\Category;

interface CategoryReader
{
    /** @return list<Category> ordered by name */
    public function all(): array;

    public function find(string $id): ?Category;
}
