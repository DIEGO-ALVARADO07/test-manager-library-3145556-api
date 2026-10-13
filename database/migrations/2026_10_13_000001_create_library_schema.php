<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** ADR-001 / H-08: the application owns the DDL, including the schema itself. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE SCHEMA IF NOT EXISTS library');
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
    }
};
