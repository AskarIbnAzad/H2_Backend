<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_feedback', function (Blueprint $table) {
            // Make article_id nullable since we're using page_url instead
            $table->foreignId('article_id')->nullable()->change();
            
            // Add new fields
            $table->string('page_url', 1000)->nullable()->after('article_id');
            
            // Update status enum to include 'In Progress'
            $table->enum('status', ['Pending', 'In Progress', 'Reviewed', 'Resolved'])
                  ->default('Pending')
                  ->change();
        });
        
        // Update existing constraint
        DB::statement('ALTER TABLE article_feedback DROP FOREIGN KEY article_feedback_article_id_foreign');
        DB::statement('ALTER TABLE article_feedback ADD CONSTRAINT article_feedback_article_id_foreign 
                      FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        Schema::table('article_feedback', function (Blueprint $table) {
            $table->dropColumn('page_url');
            $table->foreignId('article_id')->nullable(false)->change();
            $table->enum('status', ['Pending', 'Reviewed', 'Resolved'])
                  ->default('Pending')
                  ->change();
        });
    }
};