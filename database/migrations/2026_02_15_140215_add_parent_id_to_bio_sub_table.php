<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bio_sub', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bio_sub', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
    }
};
