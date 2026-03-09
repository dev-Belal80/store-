<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support existing databases that may have either old index shape.
        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique('categories_name_unique');
            });
        } catch (\Throwable $exception) {
            // Ignore when index does not exist.
        }

        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique('categories_store_id_name_deleted_at_unique');
            });
        } catch (\Throwable $exception) {
            // Ignore when index does not exist.
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['store_id', 'name'], 'categories_store_id_name_unique');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique('categories_store_id_name_unique');
            });
        } catch (\Throwable $exception) {
            // Ignore when index does not exist.
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name', 'categories_name_unique');
        });
    }
};
