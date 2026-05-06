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
        if (!Schema::connection('sqlsrv')->hasTable('memo_uploads')) {
            Schema::connection('sqlsrv')->create('memo_uploads', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('application_id'); // References mother_applications.id
                $table->string('memo_type', 50)->default('primary'); // Type: primary, unit, sua, etc.
                $table->string('file_no'); // File number for reference
                $table->string('original_filename'); // Original uploaded filename
                $table->string('stored_filename'); // System generated filename
                $table->string('file_path'); // Storage path
                $table->unsignedBigInteger('file_size'); // File size in bytes
                $table->string('mime_type', 120)->default('application/pdf'); // MIME type
                $table->text('description')->nullable(); // Optional description
                $table->unsignedBigInteger('uploaded_by'); // User who uploaded
                $table->timestamp('uploaded_at'); // Upload timestamp
                $table->enum('status', ['active', 'archived', 'deleted'])->default('active'); // Status
                $table->timestamps();

                // Add indexes for better performance
                $table->index('application_id');
                $table->index('memo_type');
                $table->index('file_no');
                $table->index('uploaded_by');
                $table->index('uploaded_at');
                $table->index('status');
                
                // Add foreign key constraint
                $table->foreign('application_id')
                    ->references('id')->on('mother_applications')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('memo_uploads');
    }
};
