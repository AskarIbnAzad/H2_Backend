<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. article_inhalation_protocols - change percentage fields to string
        Schema::table('article_inhalation_protocols', function (Blueprint $table) {
            $table->string('h2_percentage', 100)->nullable()->change();
            $table->string('o2_percentage', 100)->nullable()->change();
            $table->string('estimated_fih2', 100)->nullable()->change();
        });

        // 2. article_ingestion_protocols - change concentration to string
        Schema::table('article_ingestion_protocols', function (Blueprint $table) {
            $table->string('concentration_value', 100)->nullable()->change();
        });

        // 3. article_cell_culture_protocols - change concentration to string
        Schema::table('article_cell_culture_protocols', function (Blueprint $table) {
            $table->string('h2_concentration_value', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverse back to decimal
        Schema::table('article_inhalation_protocols', function (Blueprint $table) {
            $table->decimal('h2_percentage', 5, 2)->nullable()->change();
            $table->decimal('o2_percentage', 5, 2)->nullable()->change();
            $table->decimal('estimated_fih2', 5, 2)->nullable()->change();
        });

        Schema::table('article_ingestion_protocols', function (Blueprint $table) {
            $table->decimal('concentration_value', 10, 4)->nullable()->change();
        });

        Schema::table('article_cell_culture_protocols', function (Blueprint $table) {
            $table->decimal('h2_concentration_value', 10, 4)->nullable()->change();
        });
    }
};