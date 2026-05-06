<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
            $table->string('entity_type')->nullable();
            $table->string('entity_name')->nullable();
            $table->text('entity_physical_address')->nullable();
            $table->string('customer_type')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_account_no')->nullable();
            $table->string('customer_code')->nullable();
            $table->text('property_address')->nullable();
            $table->string('customer_status')->default('Active');
            $table->string('land_use_type')->nullable();
            $table->string('district')->nullable();
            $table->string('lga')->nullable();
            $table->string('plot_size')->nullable();
            $table->date('dob')->nullable();
            $table->string('nin')->nullable();
            $table->string('tin')->nullable();
            $table->string('rc_no')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('file_indexing_links', function (Blueprint $table) {
             $table->dropColumn([
                'entity_type',
                'entity_name',
                'entity_physical_address',
                'customer_type',
                'customer_name',
                'customer_account_no',
                'customer_code',
                'property_address',
                'customer_status',
                'land_use_type',
                'district',
                'lga',
                'plot_size',
                'dob',
                'nin',
                'tin',
                'rc_no',
                'phone',
                'email'
            ]);
        });
    }
};
