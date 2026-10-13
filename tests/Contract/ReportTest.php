<?php

it('counts how many times each book was lent in a range, most first — CA-10.1', function () {
    $admin = authAs('admin');
    $popular = makeBook($admin, ['title' => 'Popular', 'total_copies' => 5]);
    $quiet = makeBook($admin, ['title' => 'Tranquilo', 'total_copies' => 5]);
    foreach ([1, 2, 3] as $_) {
        lend($admin, makeMember($admin)['id'], [$popular['id']])->assertCreated();
    }
    lend($admin, makeMember($admin)['id'], [$quiet['id']])->assertCreated();

    $today = date('Y-m-d');
    $rows = $this->getJson("/api/v1/reports/most-borrowed?from=$today&to=$today", $admin)->assertOk()
        ->assertJsonPath('from', $today)->json('rows');

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['book_id' => $popular['id'], 'book_title' => 'Popular', 'category_name' => 'Novela', 'times_borrowed' => 3])
        ->and($rows[1]['times_borrowed'])->toBe(1);
    expect(json_encode($rows))->not->toContain('member')->not->toContain('user');
});

it('returns an empty list for a range without loans — CA-10.2', function () {
    $this->getJson('/api/v1/reports/most-borrowed?from=2020-01-01&to=2020-01-31', authAs('admin'))
        ->assertOk()->assertJsonPath('rows', []);
});

it('rejects an inverted range with 422 INVALID_DATE_RANGE and a missing one with VALIDATION_ERROR — CA-10.2', function () {
    $admin = authAs('admin');

    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01', $admin)
        ->assertStatus(422)->assertJsonPath('code', 'INVALID_DATE_RANGE');
    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01', $admin)
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('is only for admins — CA-10.5', function () {
    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', authAs('librarian'))
        ->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_ROLE');
});

it('keeps the old title in the report after the book is renamed — CA-10.4, Art. VIII, ADR-004', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['title' => 'Título viejo', 'total_copies' => 3]);
    lend($admin, makeMember($admin)['id'], [$book['id']])->assertCreated();

    $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Título nuevo', 'author' => 'A', 'category_id' => CATEGORY_SCIENCE, 'total_copies' => 3], $admin)->assertOk();
    lend($admin, makeMember($admin)['id'], [$book['id']])->assertCreated();

    $today = date('Y-m-d');
    $rows = $this->getJson("/api/v1/reports/most-borrowed?from=$today&to=$today", $admin)->json('rows');

    // The category also changed: two rows, one per frozen (title, category) pair, never a winner.
    expect(collect($rows)->pluck('book_title')->sort()->values()->all())->toBe(['Título nuevo', 'Título viejo'])
        ->and(collect($rows)->firstWhere('book_title', 'Título viejo')['category_name'])->toBe('Novela');
});

it('keeps lent books readable in the loan after they are retired — H-12, ADR-003', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['title' => 'Efímero']);
    $loan = lend($admin, makeMember($admin)['id'], [$book['id']])->json();
    $this->postJson('/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return', [], $admin)->assertOk();

    $this->deleteJson('/api/v1/books/'.$book['id'], [], $admin)->assertNoContent();

    $this->getJson('/api/v1/loans/'.$loan['id'], $admin)->assertOk()->assertJsonPath('items.0.book_title', 'Efímero');
});
