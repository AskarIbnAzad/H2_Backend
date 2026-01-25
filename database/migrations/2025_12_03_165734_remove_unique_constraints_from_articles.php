<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Drop unique constraints
            $table->dropUnique('articles_doi_unique');
            $table->dropUnique('articles_pmid_unique');
            
            // Keep the indexes for performance (but not unique)
            // The indexes were already created, so we just need to ensure they exist
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Restore unique constraints
            $table->unique('doi', 'articles_doi_unique');
            $table->unique('pmid', 'articles_pmid_unique');
        });
    }
};