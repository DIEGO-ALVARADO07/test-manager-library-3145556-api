<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Base for tests that use the real PostgreSQL (integration, contract, concurrency). */
abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            // The schema is owned by the migrations (ADR-001): rebuild it from them once per run.
            DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
            DB::unprepared('DROP TABLE IF EXISTS public.migrations');
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }

        DB::unprepared('TRUNCATE library.loan_item, library.loan, library.book, library.member, library."user" CASCADE');
    }
}
