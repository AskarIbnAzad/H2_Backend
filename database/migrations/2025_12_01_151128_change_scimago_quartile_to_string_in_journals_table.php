<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // Drop the column first (if you don't have important data)
            $table->dropColumn('scimago_quartile');
        });

        Schema::table('journals', function (Blueprint $table) {
            // Recreate as string
            $table->string('scimago_quartile', 10)->nullable()->after('h_index');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('scimago_quartile');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->enum('scimago_quartile', ['Q1', 'Q2', 'Q3', 'Q4'])->nullable()->after('h_index');
        });
    }
};