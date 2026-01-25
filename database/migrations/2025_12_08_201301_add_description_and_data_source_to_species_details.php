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
        Schema::table('article_species_details', function (Blueprint $table) {
            // Add data source tracking fields after their respective columns
            // These match your ACTUAL column names
            
            $table->enum('age_data_source', ['provided', 'estimated', 'calculated'])
                ->default('estimated')
                ->after('age_unit');
            
            $table->enum('gender_data_source', ['provided', 'estimated', 'calculated'])
                ->default('estimated')
                ->after('gender');  // Your column is 'gender' not 'sex'
            
            $table->enum('subjects_data_source', ['provided', 'estimated', 'calculated'])
                ->default('estimated')
                ->after('number_of_subjects');  // Your column is 'number_of_subjects' not 'sample_size'
            
            $table->enum('weight_data_source', ['provided', 'estimated', 'calculated'])
                ->default('estimated')
                ->after('weight_unit');
            
            $table->enum('health_status_data_source', ['provided', 'estimated', 'calculated'])
                ->default('estimated')
                ->after('health_status');
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_species_details', function (Blueprint $table) {
            $table->dropColumn([
                'age_data_source',
                'gender_data_source',
                'subjects_data_source',
                'weight_data_source',
                'health_status_data_source'
            ]);
        });
    }
};