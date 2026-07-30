<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flags a token as a "General" search — one requested by a statutory body
     * (Court, EFCC, Public Complaints, NDIC), recorded in `general_body`. This is
     * independent of `payment_reason`, which is still captured as usual.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'is_general')) {
                $table->boolean('is_general')->default(false)->after('payment_reason');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'general_body')) {
                $table->string('general_body', 100)->nullable()->after('is_general');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('legal_search_tokens', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'general_body')) {
                $table->dropColumn('general_body');
            }
            if (Schema::connection('sqlsrv')->hasColumn('legal_search_tokens', 'is_general')) {
                $table->dropColumn('is_general');
            }
        });
    }
};
