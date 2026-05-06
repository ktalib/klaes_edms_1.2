<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mother_application_draft')) {
            Schema::table('mother_application_draft', function (Blueprint $table) {
                if (!Schema::hasColumn('mother_application_draft', 'draft_id')) {
                    $table->uuid('draft_id')->default(DB::raw('NEWID()'))->unique();
                }

                if (!Schema::hasColumn('mother_application_draft', 'application_id')) {
                    $table->integer('application_id')->nullable()->index();
                }

                if (!Schema::hasColumn('mother_application_draft', 'form_state')) {
                    $table->text('form_state')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'progress_percent')) {
                    $table->decimal('progress_percent', 5, 2)->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'last_completed_step')) {
                    $table->integer('last_completed_step')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'auto_save_frequency')) {
                    $table->integer('auto_save_frequency')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'is_locked')) {
                    $table->boolean('is_locked')->default(false);
                }

                if (!Schema::hasColumn('mother_application_draft', 'locked_by')) {
                    $table->integer('locked_by')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'locked_at')) {
                    $table->dateTime('locked_at')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'version')) {
                    $table->integer('version')->default(1);
                }

                if (!Schema::hasColumn('mother_application_draft', 'last_saved_by')) {
                    $table->integer('last_saved_by')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'last_saved_at')) {
                    $table->dateTime('last_saved_at')->nullable()->index();
                }

                if (!Schema::hasColumn('mother_application_draft', 'analytics')) {
                    $table->text('analytics')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'collaborators')) {
                    $table->text('collaborators')->nullable();
                }

                if (!Schema::hasColumn('mother_application_draft', 'last_error')) {
                    $table->string('last_error', 1000)->nullable();
                }
            });
        }

        if (!Schema::hasTable('mother_application_draft_versions')) {
            Schema::create('mother_application_draft_versions', function (Blueprint $table) {
                $table->increments('id');
                $table->uuid('draft_id');
                $table->integer('version');
                $table->text('snapshot')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();

                $table->index(['draft_id', 'version']);
            });
        }

        if (!Schema::hasTable('mother_application_draft_collaborators')) {
            Schema::create('mother_application_draft_collaborators', function (Blueprint $table) {
                $table->increments('id');
                $table->uuid('draft_id');
                $table->integer('user_id');
                $table->integer('invited_by')->nullable();
                $table->string('role', 50)->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->timestamps();

                $table->index('draft_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mother_application_draft_collaborators')) {
            Schema::dropIfExists('mother_application_draft_collaborators');
        }

        if (Schema::hasTable('mother_application_draft_versions')) {
            Schema::dropIfExists('mother_application_draft_versions');
        }

        if (Schema::hasTable('mother_application_draft')) {
            Schema::table('mother_application_draft', function (Blueprint $table) {
                $columns = [
                    'draft_id',
                    'application_id',
                    'form_state',
                    'progress_percent',
                    'last_completed_step',
                    'auto_save_frequency',
                    'is_locked',
                    'locked_by',
                    'locked_at',
                    'version',
                    'last_saved_by',
                    'last_saved_at',
                    'analytics',
                    'collaborators',
                    'last_error',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('mother_application_draft', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
