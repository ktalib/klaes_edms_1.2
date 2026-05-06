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
        if (!Schema::connection('sqlsrv')->hasTable('sub_application_draft')) {
            Schema::connection('sqlsrv')->create('sub_application_draft', function (Blueprint $table) {
                $table->id();
                $table->uuid('draft_id')->unique();
                $table->unsignedBigInteger('sub_application_id')->nullable();
                $table->unsignedBigInteger('main_application_id')->nullable();
                $table->json('form_state')->nullable();
                $table->decimal('progress_percent', 5, 2)->default(0.00);
                $table->integer('last_completed_step')->default(1);
                $table->integer('auto_save_frequency')->default(30);
                $table->boolean('is_locked')->default(false);
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->datetime('locked_at')->nullable();
                $table->integer('version')->default(1);
                $table->unsignedBigInteger('last_saved_by')->nullable();
                $table->datetime('last_saved_at')->nullable();
                $table->json('analytics')->nullable();
                $table->json('collaborators')->nullable();
                $table->text('last_error')->nullable();
                $table->string('unit_file_no', 100)->nullable();
                $table->boolean('is_sua')->default(false);
                $table->timestamps();

                // Indexes for performance
                $table->index(['sub_application_id']);
                $table->index(['main_application_id']);
                $table->index(['last_saved_by']);
                $table->index(['unit_file_no']);
                $table->index(['is_sua']);
                $table->index(['created_at']);
                $table->index(['last_saved_at']);
            });
        }

        // Create sub_application_draft_versions table for version history
        if (!Schema::connection('sqlsrv')->hasTable('sub_application_draft_versions')) {
            Schema::connection('sqlsrv')->create('sub_application_draft_versions', function (Blueprint $table) {
                $table->id();
                $table->uuid('draft_id');
                $table->integer('version');
                $table->json('snapshot');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['draft_id']);
                $table->index(['version']);
                $table->unique(['draft_id', 'version']);
            });
        }

        // Create sub_application_draft_collaborators table
        if (!Schema::connection('sqlsrv')->hasTable('sub_application_draft_collaborators')) {
            Schema::connection('sqlsrv')->create('sub_application_draft_collaborators', function (Blueprint $table) {
                $table->id();
                $table->uuid('draft_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 50)->default('editor');
                $table->datetime('invited_at')->nullable();
                $table->datetime('joined_at')->nullable();
                $table->timestamps();

                $table->index(['draft_id']);
                $table->index(['user_id']);
                $table->unique(['draft_id', 'user_id']);
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
        Schema::connection('sqlsrv')->dropIfExists('sub_application_draft_collaborators');
        Schema::connection('sqlsrv')->dropIfExists('sub_application_draft_versions');
        Schema::connection('sqlsrv')->dropIfExists('sub_application_draft');
    }
};