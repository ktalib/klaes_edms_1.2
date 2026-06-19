<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        // Notices may now be issued against any file number — indexed or not —
        // so it is no longer required to have a Special Assignment application.
        DB::connection($this->connection)
            ->statement('ALTER TABLE spa_notices ALTER COLUMN spa_application_id BIGINT NULL');
    }

    public function down(): void
    {
        DB::connection($this->connection)
            ->statement('ALTER TABLE spa_notices ALTER COLUMN spa_application_id BIGINT NOT NULL');
    }
};
