<?php

use Library\Application\Services\BootstrapAdminService;

it('answers the health check on both routes — T-01, H-02', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
});

it('returns a token valid for 8 hours with valid credentials — CA-01.1', function () {
    app(BootstrapAdminService::class)->ensure('root', 'Clave-segura-1');

    $this->postJson('/api/v1/auth/login', ['username' => ' ROOT ', 'password' => 'Clave-segura-1'])
        ->assertOk()
        ->assertJson(['token_type' => 'Bearer', 'expires_in' => 28800, 'role' => 'admin'])
        ->assertJsonStructure(['access_token']);
});

it('gives the same 401 for an unknown user and a wrong password — CA-01.2', function () {
    app(BootstrapAdminService::class)->ensure('root', 'Clave-segura-1');

    $unknown = $this->postJson('/api/v1/auth/login', ['username' => 'nadie', 'password' => 'x'])
        ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    $wrong = $this->postJson('/api/v1/auth/login', ['username' => 'root', 'password' => 'mala'])
        ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

    expect($unknown->json())->toBe($wrong->json());
});

it('requires a token on every endpoint but login and health — CA-01.3', function () {
    $this->getJson('/api/v1/books')->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    $this->getJson('/api/v1/books', ['Authorization' => 'Bearer not-a-token'])->assertStatus(401);
});

it('forbids a librarian on admin endpoints with FORBIDDEN_ROLE — CA-01.3', function () {
    $librarian = authAs('librarian');

    $this->postJson('/api/v1/books', [], $librarian)->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN_ROLE');
    $this->postJson('/api/v1/users', [], $librarian)->assertStatus(403);
    $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $librarian)->assertStatus(403);
});

it('creates a librarian with the username normalized and never returns the password — CA-02.1, CA-02.3', function () {
    $admin = authAs('admin');

    $response = $this->postJson('/api/v1/users', ['username' => 'Ana ', 'password' => 'Una-clave-1', 'role' => 'librarian'], $admin)
        ->assertCreated()
        ->assertJsonPath('username', 'ana')
        ->assertJsonPath('role', 'librarian');

    expect(json_encode($response->json()))->not->toContain('password');
});

it('answers 409 USERNAME_TAKEN when the username repeats — CA-02.2', function () {
    $admin = authAs('admin');
    $body = ['username' => 'ana', 'password' => 'Una-clave-1', 'role' => 'librarian'];

    $this->postJson('/api/v1/users', $body, $admin)->assertCreated();
    $this->postJson('/api/v1/users', ['username' => ' ANA '] + $body, $admin)
        ->assertStatus(409)->assertJsonPath('code', 'USERNAME_TAKEN');
});

it('rejects the admin role through the API with 422 — CA-02.3', function () {
    $admin = authAs('admin');

    $this->postJson('/api/v1/users', ['username' => 'jefe', 'password' => 'Una-clave-1', 'role' => 'admin'], $admin)
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('uses the single error body for unknown routes and invalid input — RNF-4', function () {
    $admin = authAs('admin');

    $this->getJson('/api/v1/does-not-exist', $admin)
        ->assertStatus(404)->assertJsonStructure(['code', 'message', 'details'])->assertJsonPath('code', 'NOT_FOUND');
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)->assertJsonStructure(['code', 'message', 'details' => [['field', 'issue']]]);
    $this->getJson('/api/v1/books/not-a-uuid', $admin)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
});
