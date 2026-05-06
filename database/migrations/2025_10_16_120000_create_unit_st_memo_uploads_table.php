<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('unit_st_memo_uploads')) {
            Schema::connection('sqlsrv')->create('unit_st_memo_uploads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('unit_application_id');
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedBigInteger('uploaded_by');
                $table->timestamp('uploaded_at');
                $table->timestamps();

                $table->index('unit_application_id');
                $table->index('uploaded_by');
                $table->index('uploaded_at');
            });
        }

        if (Schema::connection('sqlsrv')->hasTable('unit_memo_uploads')) {
            $existingUploads = DB::connection('sqlsrv')->table('unit_memo_uploads')->get();

            foreach ($existingUploads as $upload) {
                $alreadyExists = DB::connection('sqlsrv')->table('unit_st_memo_uploads')
                    ->where('unit_application_id', $upload->unit_application_id)
                    ->where('file_path', $upload->file_path)
                    ->exists();

                if (!$alreadyExists) {
                    DB::connection('sqlsrv')->table('unit_st_memo_uploads')->insert([
                        'unit_application_id' => $upload->unit_application_id,
                        'file_path' => $upload->file_path,
                        'original_name' => $upload->original_name,
                        'mime_type' => null,
                        'file_size' => null,
                        'uploaded_by' => $upload->uploaded_by,
                        'uploaded_at' => $upload->uploaded_at,
                        'created_at' => $upload->created_at,
                        'updated_at' => $upload->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('unit_st_memo_uploads');
    }
};
