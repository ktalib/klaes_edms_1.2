<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('sqlsrv')->create('cadastral_officers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rank');
            $table->timestamps();
        });

        // Seed two test officers with native Northern Nigeria names
        DB::connection('sqlsrv')->table('cadastral_officers')->insert([
            [
                'name' => 'Musa Abdullahi',
                'rank' => 'Deputy Director',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Amina Sani',
                'rank' => 'Assistant Director',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('cadastral_officers');
    }
};
// SQL Server version script for cadastral_officers table
// Location: database_scripts/cadastral_officers_setup.sql

/*
CREATE TABLE cadastral_officers (
    id BIGINT PRIMARY KEY IDENTITY(1,1),
    name NVARCHAR(255) NOT NULL,
    rank NVARCHAR(255) NOT NULL,
    created_at DATETIME2,
    updated_at DATETIME2
);

INSERT INTO cadastral_officers (name, rank, created_at, updated_at) VALUES
(N'Musa Abdullahi', N'Deputy Director', GETDATE(), GETDATE()),
(N'Amina Sani', N'Assistant Director', GETDATE(), GETDATE());
*/