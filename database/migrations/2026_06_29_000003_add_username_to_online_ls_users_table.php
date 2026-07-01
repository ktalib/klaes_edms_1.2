<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online LS accounts now sign in with a username instead of their email.
 *
 * The column is added as NULLable so pre-existing rows are not blocked, and a
 * FILTERED unique index (WHERE username IS NOT NULL) enforces uniqueness only on
 * real usernames — a plain UNIQUE constraint on SQL Server allows just one NULL,
 * which would clash if multiple legacy rows are still username-less.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = Schema::connection('sqlsrv');

        if ($conn->hasTable('online_ls_users') && ! $conn->hasColumn('online_ls_users', 'username')) {
            $conn->table('online_ls_users', function (Blueprint $table) {
                $table->string('username', 100)->nullable()->after('name');
            });

            DB::connection('sqlsrv')->statement(
                'CREATE UNIQUE INDEX ux_online_ls_users_username ON online_ls_users (username) WHERE username IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        $conn = Schema::connection('sqlsrv');

        if ($conn->hasTable('online_ls_users') && $conn->hasColumn('online_ls_users', 'username')) {
            DB::connection('sqlsrv')->statement(
                'DROP INDEX IF EXISTS ux_online_ls_users_username ON online_ls_users'
            );

            $conn->table('online_ls_users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};
