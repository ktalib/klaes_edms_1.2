<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('phs_institutions', 'username')) {
            Schema::connection('sqlsrv')->table('phs_institutions', function (Blueprint $table) {
                $table->string('username', 100)->nullable()->after('name');
            });
        }

        // Backfill existing institutions with a unique username derived from name.
        $taken = [];
        DB::connection('sqlsrv')->table('phs_institutions')
            ->whereNull('username')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($row) use (&$taken) {
                $base = Str::slug((string) $row->name, '_') ?: 'org';
                $username = $base;
                $i = 1;
                while (in_array($username, $taken, true)
                    || DB::connection('sqlsrv')->table('phs_institutions')->where('username', $username)->exists()) {
                    $username = $base . $i;
                    $i++;
                }
                $taken[] = $username;
                DB::connection('sqlsrv')->table('phs_institutions')
                    ->where('id', $row->id)
                    ->update(['username' => $username]);
            });

        // Filtered unique index: SQL Server otherwise treats multiple NULLs as
        // duplicates, so scope uniqueness to non-null usernames.
        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX phs_institutions_username_unique ON phs_institutions (username) WHERE username IS NOT NULL'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('sqlsrv')->statement(
            'DROP INDEX IF EXISTS phs_institutions_username_unique ON phs_institutions'
        );

        if (Schema::connection('sqlsrv')->hasColumn('phs_institutions', 'username')) {
            Schema::connection('sqlsrv')->table('phs_institutions', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};
