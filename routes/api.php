<?php

use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\Http\Controllers\AuthController;
use Library\Adapters\Inbound\Http\Controllers\BookController;
use Library\Adapters\Inbound\Http\Controllers\LoanController;
use Library\Adapters\Inbound\Http\Controllers\MemberController;
use Library\Adapters\Inbound\Http\Controllers\ReportController;

// Ids are UUIDs; anything else is simply "not found" (H-16).
Route::pattern('id', '[0-9a-fA-F-]{36}');
Route::pattern('loan_id', '[0-9a-fA-F-]{36}');
Route::pattern('item_id', '[0-9a-fA-F-]{36}');

// Routes of api-contract §3. Laravel adds the /api prefix; the group adds /v1.
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [AuthController::class, 'health']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth.jwt')->group(function (): void {
        Route::get('/categories', [BookController::class, 'categories']);
        Route::get('/books', [BookController::class, 'index']);
        Route::get('/books/{id}', [BookController::class, 'show']);
        Route::get('/books/{id}/cover', [BookController::class, 'cover']);

        Route::middleware('role:admin')->group(function (): void {
            Route::post('/users', [AuthController::class, 'createUser']);
            Route::post('/books', [BookController::class, 'store']);
            Route::put('/books/{id}', [BookController::class, 'update']);
            Route::delete('/books/{id}', [BookController::class, 'destroy']);
            Route::put('/books/{id}/cover', [BookController::class, 'uploadCover']);
            Route::delete('/books/{id}/cover', [BookController::class, 'removeCover']);
            Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
        });

        Route::middleware('role:admin,librarian')->group(function (): void {
            Route::get('/members', [MemberController::class, 'index']);
            Route::get('/members/{id}', [MemberController::class, 'show']);
            Route::post('/members', [MemberController::class, 'store']);

            // `overdue` is declared before `{id}` (H-04).
            Route::get('/loans/overdue', [LoanController::class, 'overdue']);
            Route::post('/loans', [LoanController::class, 'store']);
            Route::get('/loans', [LoanController::class, 'index']);
            Route::get('/loans/{id}', [LoanController::class, 'show']);
            Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);
        });
    });
});
