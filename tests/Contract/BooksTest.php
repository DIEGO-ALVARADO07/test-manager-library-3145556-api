<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

it('lists the five seeded categories ordered by name — T-02', function () {
    $admin = authAs('admin');

    $names = collect($this->getJson('/api/v1/categories', $admin)->assertOk()->json('items'))->pluck('name')->all();

    expect($names)->toBe(['Ciencia', 'Historia', 'Infantil', 'Novela', 'Tecnología']);
});

it('creates a book with the available copies equal to the total and no cover — CA-03.1, CA-03.4', function () {
    $admin = authAs('admin');

    $this->postJson('/api/v1/books', [
        'title' => ' Cien años de soledad ', 'author' => 'Gabriel García Márquez', 'isbn' => '9780307474728',
        'category_id' => CATEGORY_NOVEL, 'total_copies' => 4,
    ], $admin)->assertCreated()
        ->assertJsonPath('title', 'Cien años de soledad')
        ->assertJsonPath('total_copies', 4)
        ->assertJsonPath('available_copies', 4)
        ->assertJsonPath('cover_key', null)
        ->assertJsonPath('category.name', 'Novela');
});

it('answers 409 ISBN_TAKEN for an active duplicate and allows it after the soft delete — CA-03.2, ADR-003', function () {
    $admin = authAs('admin');
    $book = makeBook($admin, ['isbn' => '9780307474728']);

    $this->postJson('/api/v1/books', ['title' => 'Otro', 'author' => 'X', 'isbn' => '9780307474728', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 1], $admin)
        ->assertStatus(409)->assertJsonPath('code', 'ISBN_TAKEN');

    $this->deleteJson('/api/v1/books/'.$book['id'], [], $admin)->assertNoContent();
    $this->postJson('/api/v1/books', ['title' => 'Otro', 'author' => 'X', 'isbn' => '9780307474728', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 1], $admin)
        ->assertCreated();
});

it('validates ISBN, total and category with 422 — CA-03.2, CA-03.3', function (array $override) {
    $admin = authAs('admin');

    $this->postJson('/api/v1/books', $override + [
        'title' => 'T', 'author' => 'A', 'isbn' => '9780307474728', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 1,
    ], $admin)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
})->with([
    'short isbn' => [['isbn' => '123']],
    'zero copies' => [['total_copies' => 0]],
    'unknown category' => [['category_id' => '99999999-9999-4999-8999-999999999999']],
    'empty title' => [['title' => '   ']],
]);

it('searches by partial text, filters by category, orders by title and paginates — CA-04.1, CA-04.2', function () {
    $admin = authAs('admin');
    makeBook($admin, ['title' => 'Zorro', 'author' => 'Isabel']);
    makeBook($admin, ['title' => 'Alquimia', 'author' => 'Paulo', 'category_id' => CATEGORY_SCIENCE]);
    makeBook($admin, ['title' => 'Bruma', 'author' => 'Miguel']);

    $all = $this->getJson('/api/v1/books?size=2', $admin)->assertOk();
    expect(collect($all->json('items'))->pluck('title')->all())->toBe(['Alquimia', 'Bruma'])
        ->and($all->json('total'))->toBe(3)->and($all->json('size'))->toBe(2)->and($all->json('page'))->toBe(1);

    $byText = $this->getJson('/api/v1/books?q=ISAB', $admin)->json();
    expect($byText['total'])->toBe(1)->and($byText['items'][0]['title'])->toBe('Zorro');

    $byCategory = $this->getJson('/api/v1/books?category_id='.CATEGORY_SCIENCE, $admin)->json();
    expect($byCategory['total'])->toBe(1);

    expect($this->getJson('/api/v1/books?q=%25', $admin)->json('total'))->toBe(0);
});

it('rejects a page size above 50 — CA-04.1', function () {
    $this->getJson('/api/v1/books?size=51', authAs('admin'))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('answers 404 for a missing or retired book — CA-04.3', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);

    $this->getJson('/api/v1/books/'.$book['id'], $admin)->assertOk();
    $this->getJson('/api/v1/books/99999999-9999-4999-8999-999999999999', $admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');

    $this->deleteJson('/api/v1/books/'.$book['id'], [], $admin)->assertNoContent();
    $this->getJson('/api/v1/books/'.$book['id'], $admin)->assertStatus(404);
    expect($this->getJson('/api/v1/books', $admin)->json('total'))->toBe(0);
});

it('moves the available copies with the total and refuses a negative result — CA-03.3', function () {
    $admin = authAs('admin');
    $librarian = authAs('librarian');
    $book = makeBook($admin, ['total_copies' => 3]);
    $member = makeMember($librarian);
    lend($librarian, $member['id'], [$book['id']])->assertCreated();            // 2 of 3 available

    $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Nuevo', 'author' => 'A', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 5], $admin)
        ->assertOk()->assertJsonPath('available_copies', 4)->assertJsonPath('total_copies', 5);

    $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Nuevo', 'author' => 'A', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 0], $admin)
        ->assertStatus(422);
    $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Nuevo', 'author' => 'A', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 1], $admin)
        ->assertOk()->assertJsonPath('available_copies', 0);
    // 1 total with 1 lent: lowering the total again would need -1 available
    DB::table('book')->where('id', $book['id'])->update(['total_copies' => 2, 'available_copies' => 1]);
    $this->putJson('/api/v1/books/'.$book['id'], ['title' => 'Nuevo', 'author' => 'A', 'category_id' => CATEGORY_NOVEL, 'total_copies' => 1], $admin)->assertOk();
});

it('refuses to retire a book with open loans — CA-03.5', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);
    $member = makeMember($admin);
    lend($admin, $member['id'], [$book['id']])->assertCreated();

    $this->deleteJson('/api/v1/books/'.$book['id'], [], $admin)
        ->assertStatus(409)->assertJsonPath('code', 'BOOK_HAS_OPEN_LOANS');
});

it('stores a cover as an opaque key, serves it and removes it — CA-03.4, T-15', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);
    $png = UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    $this->get('/api/v1/books/'.$book['id'].'/cover', $admin)->assertStatus(404);

    $response = $this->post('/api/v1/books/'.$book['id'].'/cover', ['file' => $png, '_method' => 'PUT'], $admin + ['Accept' => 'application/json'])
        ->assertOk();
    $key = $response->json('cover_key');
    expect($key)->toBeString()->not->toBe('')->not->toContain('/')->not->toContain('.');

    $this->get('/api/v1/books/'.$book['id'].'/cover', $admin)->assertOk()->assertHeader('Content-Type', 'image/png');

    $this->deleteJson('/api/v1/books/'.$book['id'].'/cover', [], $admin)->assertNoContent();
    expect($this->getJson('/api/v1/books/'.$book['id'], $admin)->json('cover_key'))->toBeNull();
    $this->get('/api/v1/books/'.$book['id'].'/cover', $admin)->assertStatus(404);
});

it('rejects covers that are too big or not JPG/PNG with 422 — CA-03.4', function () {
    $admin = authAs('admin');
    $book = makeBook($admin);
    $headers = $admin + ['Accept' => 'application/json'];

    $this->post('/api/v1/books/'.$book['id'].'/cover', ['file' => UploadedFile::fake()->create('big.png', 3000, 'image/png'), '_method' => 'PUT'], $headers)
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->post('/api/v1/books/'.$book['id'].'/cover', ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), '_method' => 'PUT'], $headers)
        ->assertStatus(422);
    $this->post('/api/v1/books/'.$book['id'].'/cover', ['file' => UploadedFile::fake()->createWithContent('fake.png', 'this is not an image'), '_method' => 'PUT'], $headers)
        ->assertStatus(422);
});
