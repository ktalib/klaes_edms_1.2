<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds file_number and account_no fields to customers table
     * for enhanced tracking and organization.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            // Add file_number field if it doesn't exist
            if (!Schema::connection('sqlsrv')->hasColumn('customers', 'file_number')) {
                $table->string('file_number', 50)->nullable()->after('customer_type')->comment('File or reference number for property/application');
                $table->index('file_number');
            }
            
            // Add account_no field if it doesn't exist
            if (!Schema::connection('sqlsrv')->hasColumn('customers', 'account_no')) {
                $table->string('account_no', 50)->nullable()->after('file_number')->comment('Account number or billing reference');
                $table->index('account_no');
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
        Schema::connection('sqlsrv')->table('customers', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::connection('sqlsrv')->hasColumn('customers', 'file_number')) {
                $table->dropIndex(['file_number']);
                $table->dropColumn('file_number');
            }
            
            if (Schema::connection('sqlsrv')->hasColumn('customers', 'account_no')) {
                $table->dropIndex(['account_no']);
                $table->dropColumn('account_no');
            }
        });
    }
};
