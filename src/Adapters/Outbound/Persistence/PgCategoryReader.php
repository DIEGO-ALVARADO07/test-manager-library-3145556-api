<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Illuminate\Support\Facades\DB;
use Library\Application\Ports\CategoryReader;
use Library\Domain\Category;
use Library\Domain\ValueObjects\Uuid;

final class PgCategoryReader implements CategoryReader
{
    public function all(): array
    {
        return DB::table('category')->orderBy('name')->get()
            ->map(static fn (object $r): Category => new Category($r->id, $r->name))
            ->all();
    }

    public function find(string $id): ?Category
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = DB::table('category')->where('id', $id)->first();

        return $row === null ? null : new Category($row->id, $row->name);
    }
}
