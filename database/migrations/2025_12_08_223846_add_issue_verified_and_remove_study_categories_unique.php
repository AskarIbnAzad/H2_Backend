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
        Schema::table('article_study_categories', function (Blueprint $table) {
            // Step 1: Drop foreign keys that depend on the unique constraint
            $table->dropForeign(['article_id']);
            $table->dropForeign(['study_category_id']);
            
            // Step 2: Drop the unique constraint
            $table->dropUnique('article_study_categories_article_id_study_category_id_unique');
            
            // Step 3: Recreate foreign keys without the unique constraint
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->onDelete('cascade');
            
            $table->foreign('study_category_id')
                ->references('id')
                ->on('study_categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_study_categories', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['article_id']);
            $table->dropForeign(['study_category_id']);
            
            // Recreate unique constraint
            $table->unique(['article_id', 'study_category_id'], 'article_study_categories_article_id_study_category_id_unique');
            
            // Recreate foreign keys
            $table->foreign('article_id')
                ->references('id')
                ->on('articles')
                ->onDelete('cascade');
            
            $table->foreign('study_category_id')
                ->references('id')
                ->on('study_categories')
                ->onDelete('cascade');
        });
    }
};