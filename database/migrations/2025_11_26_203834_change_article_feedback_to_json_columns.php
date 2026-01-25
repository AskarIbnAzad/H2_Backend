<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL
        DB::statement('ALTER TABLE article_feedback MODIFY user JSON');
        DB::statement('ALTER TABLE article_feedback MODIFY feedback JSON');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE article_feedback MODIFY user TEXT');
        DB::statement('ALTER TABLE article_feedback MODIFY feedback TEXT');
    }
};