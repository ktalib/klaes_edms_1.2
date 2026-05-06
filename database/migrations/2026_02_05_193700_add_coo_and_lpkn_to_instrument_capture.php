<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('instrument_capture', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'coo_recertification')) {
                $table->boolean('coo_recertification')->nullable()->after('op_serial_number');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'coo_conversion')) {
                $table->boolean('coo_conversion')->nullable()->after('op_serial_number'); // Fallback after op_serial_number if coo_recertification not committed yet?
                // Actually if we just added coo_recertification above, hasColumn might return FALSE because it's in the same transaction/blueprint?
                // Schema::hasColumn checks database definition. The blueprint commands are executed AFTER the closure finishes?
                // No, Schema::builder executes commands.
                // But inside closure?
            }
            // To be safe against "after" dependency issues if previous column not added:
            // Just use consistent 'after' or don't worry about order too much.
            // But let's stick to the code.

            if (!Schema::connection('sqlsrv')->hasColumn('instrument_capture', 'lpkn_no')) {
                $table->string('lpkn_no')->nullable()->after('plot_size');
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
        Schema::table('instrument_capture', function (Blueprint $table) {
            $table->dropColumn(['coo_recertification', 'coo_conversion', 'lpkn_no']);
        });
    }
};
