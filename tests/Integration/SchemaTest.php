<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Executable version of the checks in data-model.md §10 (Art. VI, X) and of T-02 / T-18.

it('has the six tables and no other table in the library schema — T-02', function () {
    $tables = collect(DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'library' ORDER BY 1"))
        ->pluck('table_name')->all();

    expect($tables)->toBe(['book', 'category', 'loan', 'loan_item', 'member', 'user']);
});

it('has no column with a DEFAULT and no audit or derived columns — T-02, Art. VII', function () {
    $columns = collect(DB::select("SELECT table_name, column_name, column_default FROM information_schema.columns WHERE table_schema = 'library'"));

    expect($columns->whereNotNull('column_default')->count())->toBe(0)
        ->and($columns->pluck('column_name')->intersect(['created_at', 'updated_at', 'status', 'days_late'])->all())->toBe([]);
});

it('uses timestamptz for every instant — data-model §3', function () {
    $types = collect(DB::select("SELECT DISTINCT data_type FROM information_schema.columns WHERE table_schema = 'library' AND column_name IN ('deleted_at','loaned_at','due_at','returned_at')"))
        ->pluck('data_type')->all();

    expect($types)->toBe(['timestamp with time zone']);
});

it('seeds the five categories with the fixed identifiers — data-model §9', function () {
    $rows = DB::table('category')->orderBy('name')->pluck('id', 'name')->all();

    expect($rows)->toBe([
        'Ciencia' => '22222222-2222-4222-8222-222222222222',
        'Historia' => '44444444-4444-4444-8444-444444444444',
        'Infantil' => '55555555-5555-4555-8555-555555555555',
        'Novela' => '11111111-1111-4111-8111-111111111111',
        'Tecnología' => '33333333-3333-4333-8333-333333333333',
    ]);
});

it('declares the check constraints and the partial indexes of §4', function () {
    $checks = collect(DB::select("SELECT conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'library' AND contype = 'c'"))
        ->pluck('conname')->sort()->values()->all();
    $indexes = collect(DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'library'"))->pluck('indexdef', 'indexname');

    expect($checks)->toBe(['ck_book_available_range', 'ck_book_total_positive', 'ck_user_role'])
        ->and($indexes['ux_book_isbn_active'])->toContain('WHERE (deleted_at IS NULL)')
        ->and($indexes['ix_loan_item_due_open'])->toContain('WHERE (returned_at IS NULL)')
        ->and($indexes['ux_loan_item_loan_book'])->toContain('(loan_id, book_id)');
});

it('lets the engine reject a total below 1 and an unknown role — T-18', function () {
    $category = '11111111-1111-4111-8111-111111111111';

    expect(fn () => DB::table('book')->insert([
        'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'title' => 'T', 'author' => 'A', 'isbn' => '9780000000001',
        'category_id' => $category, 'total_copies' => 0, 'available_copies' => 0,
    ]))->toThrow(QueryException::class, 'ck_book_total_positive');

    expect(fn () => DB::table('user')->insert([
        'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'username' => 'x', 'password_hash' => 'h', 'role' => 'root',
    ]))->toThrow(QueryException::class, 'ck_user_role');
});

it('is the last barrier for the copy range even when written outside the adapter — ADR-002', function () {
    $category = '11111111-1111-4111-8111-111111111111';

    expect(fn () => DB::table('book')->insert([
        'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'title' => 'T', 'author' => 'A', 'isbn' => '9780000000002',
        'category_id' => $category, 'total_copies' => 1, 'available_copies' => 2,
    ]))->toThrow(QueryException::class, 'ck_book_available_range');
});

it('does not let a book with loan lines be deleted — ADR-003', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);
    lend($admin, makeMember($admin)['id'], [$book['id']])->assertCreated();

    expect(fn () => DB::table('book')->where('id', $book['id'])->delete())->toThrow(QueryException::class);
});

it('keeps the migrations table outside the library schema — H-15', function () {
    $schemas = collect(DB::select("SELECT table_schema FROM information_schema.tables WHERE table_name = 'migrations'"))->pluck('table_schema')->all();

    expect($schemas)->toBe(['public']);
});
