<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\CategoryReader;
use Library\Domain\Category;

final class ListCategoriesService
{
    public function __construct(private readonly CategoryReader $categories)
    {
    }

    /** @return list<Category> */
    public function list(): array
    {
        return $this->categories->all();
    }
}
