<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No schema changes are required. Registry metadata will be derived at runtime.
    }

    public function down(): void
    {
        // No schema changes were applied in up(), so nothing to roll back.
    }
};
