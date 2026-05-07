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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('photo')->nullable();
            $table->string('designation')->nullable();
            $table->string('institution')->nullable();
            $table->string('department')->nullable();
            $table->string('country')->nullable();

            $table->text('bio')->nullable();

            $table->json('research_interests')->nullable();
            $table->json('skills')->nullable();

            $table->string('personal_website_url')->nullable();
            $table->string('orcid_id')->nullable();

            $table->json('publications')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('country');
            $table->index('institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
