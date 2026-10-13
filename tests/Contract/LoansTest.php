<?php

use Illuminate\Support\Facades\DB;

it('creates and finds members, and rejects a repeated document — CA-05.1, CA-05.2', function () {
    $librarian = authAs('librarian');

    $this->postJson('/api/v1/members', ['full_name' => 'Laura Pérez', 'document_number' => '123'], $librarian)->assertCreated();
    $this->postJson('/api/v1/members', ['full_name' => 'Otra', 'document_number' => '123'], $librarian)
        ->assertStatus(409)->assertJsonPath('code', 'DOCUMENT_TAKEN');

    expect($this->getJson('/api/v1/members?q=laura', $librarian)->json('total'))->toBe(1)
        ->and($this->getJson('/api/v1/members?q=123', $librarian)->json('total'))->toBe(1);
});

it('shows a member with the number of unreturned books — CA-05, contract 3.5', function () {
    $librarian = authAs('librarian');
    $member = makeMember($librarian);
    $book = makeBook(authAs('admin'));
    lend($librarian, $member['id'], [$book['id']])->assertCreated();

    $this->getJson('/api/v1/members/'.$member['id'], $librarian)->assertOk()->assertJsonPath('active_loans', 1);
});

it('registers a loan with frozen data, a 14-day due date and one copy less — CA-06.1, CA-06.3, CA-06.6', function () {
    $admin = authAs('admin');
    $librarian = authAs('librarian');
    $book = makeBook($admin, ['total_copies' => 2]);
    $member = makeMember($librarian);

    $response = lend($librarian, $member['id'], [$book['id']])->assertCreated()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('items.0.book_title', 'Cien años de soledad')
        ->assertJsonPath('items.0.category_name', 'Novela')
        ->assertJsonPath('items.0.returned_at', null);

    $loaned = new DateTimeImmutable($response->json('loaned_at'));
    $due = new DateTimeImmutable($response->json('items.0.due_at'));
    expect($loaned->modify('+14 days')->format('c'))->toBe($due->format('c'))
        ->and($response->json('loaned_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    expect($this->getJson('/api/v1/books/'.$book['id'], $admin)->json('available_copies'))->toBe(1);
});

it('rejects a repeated book in a loan with 422 — CA-06.2', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);
    $member = makeMember($admin);

    lend($admin, $member['id'], [$book['id'], $book['id']])
        ->assertStatus(422)->assertJsonPath('code', 'DUPLICATE_BOOK_IN_LOAN');
});

it('records nothing when one book has no copies left — CA-06.3', function () {
    $admin = authAs('admin');
    $available = makeBook($admin, ['total_copies' => 1]);
    $empty = makeBook($admin, ['total_copies' => 1]);
    $first = makeMember($admin);
    $second = makeMember($admin);
    lend($admin, $first['id'], [$empty['id']])->assertCreated();

    lend($admin, $second['id'], [$available['id'], $empty['id']])
        ->assertStatus(409)->assertJsonPath('code', 'BOOK_NOT_AVAILABLE')
        ->assertJsonPath('details.0.field', 'items[1].book_id');

    expect($this->getJson('/api/v1/books/'.$available['id'], $admin)->json('available_copies'))->toBe(1)
        ->and(DB::table('loan')->count())->toBe(1);
});

it('refuses a fourth unreturned book with 422 and the current count — CA-06.4', function () {
    $admin = authAs('admin');
    $member = makeMember($admin);
    $books = [makeBook($admin), makeBook($admin), makeBook($admin), makeBook($admin)];
    lend($admin, $member['id'], [$books[0]['id'], $books[1]['id'], $books[2]['id']])->assertCreated();

    lend($admin, $member['id'], [$books[3]['id']])
        ->assertStatus(422)->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED')
        ->assertJsonPath('details.0.issue', 'active_loans=3');
});

it('refuses a new loan to a member with overdue books with 409 — CA-06.5', function () {
    $admin = authAs('admin');
    $member = makeMember($admin);
    $old = lend($admin, $member['id'], [makeBook($admin)['id']])->assertCreated()->json();
    ageLoan($old['id'], 20);

    lend($admin, $member['id'], [makeBook($admin)['id']])
        ->assertStatus(409)->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');
});

it('refuses unknown members and books with 404 and bad item counts with 422', function () {
    $admin = authAs('admin');
    $member = makeMember($admin);

    lend($admin, '99999999-9999-4999-8999-999999999999', [makeBook($admin)['id']])->assertStatus(404);
    lend($admin, $member['id'], ['99999999-9999-4999-8999-999999999999'])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    $this->postJson('/api/v1/loans', ['member_id' => $member['id'], 'items' => []], $admin)->assertStatus(422);
});

it('returns one line once, gives the copy back and closes the loan — CA-07.1, CA-07.2, CA-07.3, CA-07.4', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['total_copies' => 1]);
    $member = makeMember($admin);
    $loan = lend($admin, $member['id'], [$book['id']])->assertCreated()->json();
    $url = '/api/v1/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return';

    $this->postJson($url, [], $admin)->assertOk()->assertJson(['id' => $loan['items'][0]['id']])
        ->assertJsonStructure(['returned_at']);
    expect($this->getJson('/api/v1/books/'.$book['id'], $admin)->json('available_copies'))->toBe(1);

    $this->postJson($url, [], $admin)->assertStatus(409)->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');
    expect($this->getJson('/api/v1/books/'.$book['id'], $admin)->json('available_copies'))->toBe(1);

    $this->getJson('/api/v1/loans/'.$loan['id'], $admin)->assertOk()->assertJsonPath('status', 'closed');
});

it('answers 404 when the line does not belong to the loan', function () {
    $admin = authAs('admin');
    $member = makeMember($admin);
    $loan = lend($admin, $member['id'], [makeBook($admin)['id']])->json();

    $this->postJson('/api/v1/loans/'.$loan['id'].'/items/99999999-9999-4999-8999-999999999999/return', [], $admin)
        ->assertStatus(404);
});

it('lists loans filtered by member, status and dates, newest first — CA-08.2, H-10', function () {
    $admin = authAs('admin');
    $one = makeMember($admin);
    $two = makeMember($admin);
    $overdue = lend($admin, $one['id'], [makeBook($admin)['id']])->json();
    ageLoan($overdue['id'], 20);
    $open = lend($admin, $two['id'], [makeBook($admin)['id']])->json();

    $ids = fn (string $query) => collect($this->getJson('/api/v1/loans'.$query, $admin)->assertOk()->json('items'))->pluck('id')->all();

    expect($ids(''))->toBe([$open['id'], $overdue['id']])
        ->and($ids('?status=overdue'))->toBe([$overdue['id']])
        ->and($ids('?status=open'))->toBe([$open['id']])
        ->and($ids('?status=closed'))->toBe([])
        ->and($ids('?member_id='.$two['id']))->toBe([$open['id']])
        ->and($ids('?from='.date('Y-m-d').'&to='.date('Y-m-d')))->toBe([$open['id']]);

    $this->getJson('/api/v1/loans?status=wrong', $admin)->assertStatus(422);
    $this->getJson('/api/v1/loans/'.$open['id'], $admin)->assertOk()->assertJsonPath('status', 'open');
});

it('lists overdue lines with the days late, most late first — CA-09.1, CA-09.2', function () {
    $admin = authAs('admin');
    $member = makeMember($admin, 'Socio Uno');
    $other = makeMember($admin, 'Socio Dos');
    $a = lend($admin, $member['id'], [makeBook($admin, ['title' => 'Libro A'])['id']])->json();
    $b = lend($admin, $other['id'], [makeBook($admin, ['title' => 'Libro B'])['id']])->json();
    ageLoan($a['id'], 20);   // 6 days late
    ageLoan($b['id'], 25);   // 11 days late

    $items = $this->getJson('/api/v1/loans/overdue', $admin)->assertOk()->json('items');

    expect(collect($items)->pluck('book_title')->all())->toBe(['Libro B', 'Libro A'])
        ->and($items[0]['member_name'])->toBe('Socio Dos')
        ->and($items[0]['days_late'])->toBe(11)
        ->and($items[1]['days_late'])->toBe(6);
    $this->getJson('/api/v1/loans/overdue', authAs('librarian'))->assertOk();
});
