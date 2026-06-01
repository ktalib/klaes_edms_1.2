<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('digital_file_accesses', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('digital_request_id')
                    ->comment('FK → digital_file_tracking_requests.id');
                $table->unsignedBigInteger('requester_user_id')
                    ->comment('FK → users.id');

                $table->string('file_no', 100);
                $table->string('file_title')->nullable();

                // Relative path inside storage/app/ — e.g. DFR/42/RES-2026-1234
                $table->string('temp_folder_path', 500);

                // JSON array of bare filenames inside temp_folder_path
                $table->text('files_copied')->nullable()
                    ->comment('JSON array of filenames copied to temp folder');

                $table->dateTime('expires_at');
                $table->dateTime('granted_at')->nullable();

                // active | expired | revoked
                $table->string('access_status', 20)->default('active');

                $table->timestamps();

                $table->index('requester_user_id');
                $table->index('digital_request_id');
                $table->index(['access_status', 'expires_at']);
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('digital_file_accesses');
    }
};
