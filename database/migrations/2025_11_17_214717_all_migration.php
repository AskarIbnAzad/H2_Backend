<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Complete Database Schema (ALL TABLES)
     */
    public function up(): void
    {
        // ============================================================================
        // SECTION 1: AUTHENTICATION & USER MANAGEMENT
        // ============================================================================
        
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive', 'Pending', 'Suspended'])->default('Active');
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('email');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Schema::create('personal_access_tokens', function (Blueprint $table) {
        //     $table->id();
        //     $table->morphs('tokenable');
        //     $table->string('name');
        //     $table->string('token', 64)->unique();
        //     $table->text('abilities')->nullable();
        //     $table->timestamp('last_used_at')->nullable();
        //     $table->timestamp('expires_at')->nullable();
        //     $table->timestamps();
        // });

        // ============================================================================
        // SECTION 2: MASTER DATA TABLES (Hierarchical & Reference)
        // ============================================================================
        
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('species')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('diseases')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('organs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('organs')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('systems', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('systems')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('research_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('research_topics')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('study_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('parent_id')->nullable()->constrained('study_types')->cascadeOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('status');
        });

        Schema::create('administration_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index('status');
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 255)->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->fullText('keyword');
        });

        Schema::create('verified_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->string('orcid', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('institution_affiliation', 500)->nullable();
            $table->unsignedInteger('author_h_index')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('verified_authors')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->index('parent_id');
            $table->index('orcid');
            $table->index('is_featured');
        });

        // ============================================================================
        // SECTION 3: BIOMARKER SYSTEM
        // ============================================================================
        
        Schema::create('bio_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });

        Schema::create('bio_sub', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->enum('status', ['Approved', 'Pending', 'Deleted'])->default('Pending');
            $table->timestamps();
            
            $table->index('status');
        });

        Schema::create('bio_bridge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cat_id')->constrained('bio_categories')->cascadeOnDelete();
            $table->foreignId('sub_id')->constrained('bio_sub')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['cat_id', 'sub_id']);
        });

        // ============================================================================
        // SECTION 4: CORE ARTICLE TABLE (No JSON!)
        // ============================================================================
        
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('mhid', 50)->unique();
            $table->string('doi', 255)->unique()->nullable();
            $table->string('pmid', 50)->unique()->nullable();
            
            // Relationships
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by')->default(1)->constrained('users')->restrictOnDelete();
            
            // Status & Flags
            $table->enum('status', ['Unverified', 'Verified', 'Draft', 'In Review', 'Flagged for Review', 'Review Complete'])->default('Unverified');
            $table->boolean('is_trending')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedTinyInteger('rank_score')->nullable(); // 0-100
            
            $table->timestamps();
            
            // Indexes
            $table->index('mhid');
            $table->index('doi');
            $table->index('pmid');
            $table->index('status');
            $table->index('is_trending');
            $table->index(['status', 'is_trending', 'created_at'], 'idx_article_status_trending');
        });

        // ====================================     ========================================
        // SECTION 5: PUBLICATION METADATA TABLES
        // ============================================================================
        
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->unique();
            $table->string('url', 1000)->nullable();
            $table->decimal('impact_factor', 10, 3)->nullable();
            $table->unsignedInteger('h_index')->nullable();
            $table->enum('scimago_quartile', ['Q1', 'Q2', 'Q3', 'Q4'])->nullable();
            $table->string('issn', 20)->nullable();
            $table->timestamps();
            
            $table->index('impact_factor');
        });

        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->unique();
            $table->timestamps();
        });

        Schema::create('article_publication_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            $table->text('title');
            $table->text('abstract')->nullable();
            $table->year('year')->nullable();
            $table->string('volume', 50)->nullable();
            $table->string('issue', 50)->nullable();
            $table->string('pages', 100)->nullable();
            
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('publishers')->nullOnDelete();
            
            // Verification flags
            $table->boolean('title_verified')->default(false);
            $table->boolean('abstract_verified')->default(false);
            $table->boolean('year_verified')->default(false);
            $table->boolean('volume_verified')->default(false);
            $table->boolean('pages_verified')->default(false);
            
            $table->timestamps();
            
            $table->unique('article_id');
            $table->fullText(['title', 'abstract'], 'idx_publication_search');
            $table->index(['year', 'article_id'], 'idx_year_article');
        });

        Schema::create('article_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('verified_authors')->cascadeOnDelete();
            
            $table->unsignedTinyInteger('author_order'); // 1st, 2nd, 3rd author
            $table->text('affiliation')->nullable();
            $table->boolean('is_corresponding')->default(false);
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'author_order']);
            $table->index(['article_id', 'author_id']);
        });

        Schema::create('article_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'keyword_id']);
        });

        Schema::create('article_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            
            $table->enum('country_type', ['publication', 'grant', 'research']);
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index(['article_id', 'country_type']);
        });

        Schema::create('article_pdf_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            $table->string('url', 1000);
            $table->boolean('is_paywall')->default(false);
            $table->decimal('file_size_mb', 10, 2)->nullable();
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index('article_id');
        });

        // ============================================================================
        // SECTION 6: STUDY DESIGN & CLASSIFICATION TABLES
        // ============================================================================
        
        Schema::create('article_study_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('study_type_id')->constrained('study_types')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'study_type_id']);
        });

        Schema::create('study_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('category_type', ['in_vivo', 'in_vitro', 'ex_vivo', 'clinical', 'non_experimental']);
            $table->foreignId('parent_id')->nullable()->constrained('study_categories')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['name', 'category_type']);
            $table->index('category_type');
        });

        Schema::create('article_study_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('study_category_id')->constrained('study_categories')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'study_category_id']);
        });

        Schema::create('article_highlight_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            $table->text('description')->nullable();
            $table->boolean('description_verified')->default(false);
            
            $table->timestamps();
            
            $table->unique('article_id');
        });

        Schema::create('article_species', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'species_id']);
        });

        Schema::create('article_species_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            
            // Subject Information
            $table->unsignedInteger('number_of_subjects')->nullable();
            $table->enum('health_status', ['Healthy', 'Diseased', 'Mixed'])->nullable();
            $table->enum('gender', ['Male', 'Female', 'Both', 'Not Specified'])->nullable();
            
            // Age Information
            $table->decimal('average_age', 10, 2)->nullable();
            $table->enum('age_unit', ['years', 'months', 'weeks', 'days'])->nullable();
            $table->decimal('age_range_min', 10, 2)->nullable();
            $table->decimal('age_range_max', 10, 2)->nullable();
            
            // Weight Information
            $table->decimal('average_weight', 10, 2)->nullable();
            $table->enum('weight_unit', ['kg', 'g', 'lbs'])->nullable();
            $table->decimal('weight_range_min', 10, 2)->nullable();
            $table->decimal('weight_range_max', 10, 2)->nullable();
            
            $table->text('description')->nullable();
            
            // Verification flags
            $table->boolean('subjects_verified')->default(false);
            $table->boolean('health_verified')->default(false);
            $table->boolean('gender_verified')->default(false);
            $table->boolean('age_verified')->default(false);
            $table->boolean('weight_verified')->default(false);
            
            $table->timestamps();
            
            $table->unique(['article_id', 'species_id']);
        });

        Schema::create('article_organs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('organ_id')->constrained('organs')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'organ_id']);
        });

        Schema::create('article_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'system_id']);
        });

        Schema::create('article_diseases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();
            
            $table->text('disease_model_description')->nullable();
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index(['article_id', 'disease_id']);
        });

        Schema::create('article_research_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('research_topic_id')->constrained('research_topics')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'research_topic_id'], 'unique_article_topic');
        });

        Schema::create('timing_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('context', ['in_vivo', 'in_vitro', 'ex_vivo']);
            $table->timestamps();
            
            $table->unique(['name', 'context']);
        });

        Schema::create('article_timing_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('timing_treatment_id')->constrained('timing_treatments')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'timing_treatment_id'], 'unique_article_timing');
        });

        Schema::create('outcome_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->timestamps();
        });

        Schema::create('article_outcome_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('outcome_type_id')->constrained('outcome_types')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'outcome_type_id']);
        });

        Schema::create('article_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            $table->text('outcome_description');
            $table->boolean('outcome_verified')->default(false);
            
            $table->timestamps();
            
            $table->unique('article_id');
            $table->fullText('outcome_description');
        });

        Schema::create('article_study_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            $table->unsignedInteger('duration_value');
            $table->enum('duration_unit', ['minutes', 'hours', 'days', 'weeks', 'months', 'years']);
            $table->enum('context', ['in_vivo', 'in_vitro', 'ex_vivo', 'overall']);
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index(['article_id', 'context']);
        });

        // ============================================================================
        // SECTION 7: EXPERIMENTAL DESIGN TABLES (from researcherData)
        // ============================================================================
        
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->unique();
            $table->string('manufacturer', 500)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('website', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('article_experimental_design', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            // Brand/Product
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->boolean('is_commercial_product')->default(false);
            
            // Study Design Flags
            $table->boolean('has_pharmacokinetics')->default(false);
            $table->text('pharmacokinetics_description')->nullable();
            
            $table->boolean('has_dose_comparison')->default(false);
            $table->text('dose_comparison_description')->nullable();
            
            $table->boolean('has_dose_dependent_effect')->default(false);
            
            $table->boolean('has_drug_comparison')->default(false);
            $table->text('drug_comparison_description')->nullable();
            
            $table->boolean('has_method_admin_comparison')->default(false);
            $table->text('method_admin_comparison_description')->nullable();
            
            // ERW Specific
            $table->boolean('is_erw')->default(false);
            $table->text('erw_comparison_description')->nullable();
            $table->decimal('ph_value', 4, 2)->nullable();
            
            // Oxyhydrogen
            $table->boolean('uses_oxyhydrogen')->default(false);
            
            // Safety & Effects
            $table->boolean('has_safety_focus')->default(false);
            $table->text('safety_profile_description')->nullable();
            
            $table->boolean('has_adverse_effects')->default(false);
            $table->text('adverse_effects_description')->nullable();
            
            $table->boolean('includes_pregnant_breastfeeding')->default(false);
            
            $table->boolean('has_responder_difference')->default(false);
            $table->boolean('has_sex_difference')->default(false);
            
            // Mechanistic
            $table->boolean('has_gene_expression_data')->default(false);
            $table->text('gene_expression_description')->nullable();
            
            $table->boolean('has_mechanistic_insights')->default(false);
            $table->text('mechanistic_insights_description')->nullable();
            
            // External Links
            $table->string('study_url', 1000)->nullable();
            $table->string('video_webpage_url', 1000)->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->unique('article_id');
        });

        Schema::create('article_administration_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('administration_method_id')->constrained('administration_methods')->cascadeOnDelete();
            $table->boolean('verified')->default(false);
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_id', 'administration_method_id'], 'unique_article_admin_method');
        });

        Schema::create('article_inhalation_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            
            // Concentration
            $table->decimal('h2_percentage', 5, 2)->nullable();
            $table->decimal('o2_percentage', 5, 2)->nullable();
            $table->decimal('estimated_fih2', 5, 2)->nullable();
            
            // Flow Rate
            $table->decimal('flow_rate_value', 10, 2)->nullable();
            $table->string('flow_rate_unit', 50)->nullable();
            
            // Duration & Frequency
            $table->decimal('duration_value', 10, 2)->nullable();
            $table->enum('duration_unit', ['minutes', 'hours', 'days', 'weeks'])->nullable();
            $table->string('frequency', 255)->nullable();
            
            // Delivery Method
            $table->string('delivery_method', 255)->nullable();
            
            // Peak Breath Hydrogen
            $table->decimal('peak_breath_hydrogen_value', 10, 2)->nullable();
            $table->string('peak_breath_hydrogen_unit', 50)->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index(['article_id', 'species_id']);
        });

        Schema::create('article_ingestion_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            
            // Volume
            $table->decimal('volume_value', 10, 2)->nullable();
            $table->string('volume_unit', 50)->nullable();
            
            // Concentration
            $table->string('concentration_value', 10, 4)->nullable();
            $table->string('concentration_unit', 50)->nullable();
            
            // Absolute Dose
            $table->decimal('absolute_dose_value', 10, 4)->nullable();
            $table->string('absolute_dose_unit', 50)->nullable();
            
            // Relative Dose
            $table->decimal('relative_dose_value', 10, 4)->nullable();
            $table->string('relative_dose_unit', 50)->nullable();
            
            // Duration & Frequency
            $table->decimal('duration_value', 10, 2)->nullable();
            $table->enum('duration_unit', ['minutes', 'hours', 'days', 'weeks', 'months'])->nullable();
            $table->string('frequency', 255)->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index(['article_id', 'species_id']);
        });

        Schema::create('article_cell_culture_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            
            // Cell/Tissue Information
            $table->string('cell_tissue_type', 500)->nullable();
            $table->string('cell_line', 255)->nullable();
            
            // Concentration in Medium
            $table->decimal('h2_concentration_value', 10, 4)->nullable();
            $table->string('h2_concentration_unit', 50)->nullable();
            
            // Duration & Frequency
            $table->decimal('duration_value', 10, 2)->nullable();
            $table->enum('duration_unit', ['minutes', 'hours', 'days'])->nullable();
            $table->string('frequency', 255)->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index('article_id');
        });

        Schema::create('article_topical_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('species_id')->nullable()->constrained('species')->nullOnDelete();
            
            $table->text('application_method')->nullable();
            $table->text('concentration_description')->nullable();
            $table->text('duration_frequency')->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index('article_id');
        });

        // ============================================================================
        // SECTION 8: BIOMARKER TABLES (from biomaker JSON)
        // ============================================================================
        
        Schema::create('change_directions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->enum('direction', ['increased', 'decreased', 'unchanged', 'mixed']);
            $table->timestamps();
        });

        Schema::create('article_biomarkers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('biomarker_id')->constrained('bio_sub')->cascadeOnDelete();
            
            // Measurement Details
            $table->boolean('is_measured')->default(true);
            $table->foreignId('change_direction_id')->nullable()->constrained('change_directions')->nullOnDelete();
            
            // Protein Information
            $table->string('protein_name', 500)->nullable();
            $table->boolean('protein_verified')->default(false);
            
            // Statistical Details
            $table->decimal('baseline_value', 20, 4)->nullable();
            $table->string('baseline_unit', 100)->nullable();
            
            $table->decimal('post_treatment_value', 20, 4)->nullable();
            $table->string('post_treatment_unit', 100)->nullable();
            
            $table->decimal('change_percentage', 10, 2)->nullable();
            $table->decimal('p_value', 10, 8)->nullable();
            
            // Notes
            $table->text('measurement_notes')->nullable();
            
            $table->boolean('verified')->default(false);
            
            $table->timestamps();
            
            $table->index(['article_id', 'biomarker_id']);
            $table->index(['article_id', 'is_measured', 'change_direction_id'], 'idx_biomarker_measurements');
        });

        Schema::create('article_biomarker_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_biomarker_id')->constrained('article_biomarkers')->cascadeOnDelete();
            $table->foreignId('bio_category_id')->constrained('bio_categories')->cascadeOnDelete();
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['article_biomarker_id', 'bio_category_id'], 'unique_biomarker_category');
        });

        // ============================================================================
        // SECTION 9: SUPPORTING TABLES (Feedback, Claims, Revisions, etc.)
        // ============================================================================
        
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            
            
            $table->index(['article_id', 'created_at']);
        });

        Schema::create('article_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('user', 255);
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->text('feedback');
            $table->enum('status', ['Pending', 'Reviewed', 'Resolved'])->default('Pending');
            $table->timestamps();
            
            $table->index(['article_id', 'status']);
        });

        Schema::create('article_claims', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('affiliation', 500)->nullable();
            $table->string('position_title', 255)->nullable();
            $table->string('orcid_id', 50)->nullable();
            $table->text('explanation')->nullable();
            $table->text('supporting_evidence')->nullable();
            $table->foreignId('final_article_id')->constrained('articles')->cascadeOnDelete();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['final_article_id', 'status']);
        });

        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('video_url', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('graph_data', function (Blueprint $table) {
            $table->id();
            $table->string('lbl', 255);
            $table->string('type', 100);
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index('type');
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('subject', 500)->nullable();
            $table->text('message');
            $table->enum('status', ['New', 'Read', 'Responded'])->default('New');
            $table->timestamps();
            
            $table->index('status');
        });

        // ============================================================================
        // SECTION 10: LEGACY TABLES (Portal Articles - if still needed)
        // ============================================================================
        
        Schema::create('portal_articles', function (Blueprint $table) {
            $table->id();
            $table->text('title')->nullable();
            $table->text('authors')->nullable();
            $table->string('year', 10)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('grant_country', 100)->nullable();
            $table->string('research_country', 100)->nullable();
            $table->string('pmid', 100)->nullable();
            $table->string('doi', 100)->nullable();
            $table->text('abstract')->nullable();
            $table->string('publisher', 500)->nullable();
            $table->text('journal')->nullable();
            $table->text('journal_url')->nullable();
            $table->text('volume')->nullable();
            $table->text('pages')->nullable();
            $table->string('impact_factor', 50)->nullable();
            $table->string('h_index', 50)->nullable();
            $table->string('sci_mago', 50)->nullable();
            $table->text('outcome')->nullable();
            $table->enum('admin_approval', ['Approved', 'Pending', 'Rejected'])->default('Pending');
            $table->timestamps();
            
            $table->index('admin_approval');
        });

        // ============================================================================
        // SECTION 11: SEED DEFAULT DATA
        // ============================================================================
        
        // Insert default role
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Admin', 'description' => 'System Administrator', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Reviewer', 'description' => 'Article Reviewer', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Contributor', 'description' => 'Content Contributor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'User', 'description' => 'Regular User', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert default admin user
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'System Admin',
            'email' => 'admin@h2research.org',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert outcome types
        DB::table('outcome_types')->insert([
            ['name' => 'positive', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'negative', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'neutral', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'mixed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert change directions
        DB::table('change_directions')->insert([
            ['name' => 'Statistically Increased', 'direction' => 'increased', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Statistically Decreased', 'direction' => 'decreased', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Increasing Trend', 'direction' => 'increased', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Decreasing Trend', 'direction' => 'decreased', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'No Change', 'direction' => 'unchanged', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Not Measured', 'direction' => 'unchanged', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert timing treatments
        DB::table('timing_treatments')->insert([
            ['name' => 'Pre-treatment', 'context' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Post-treatment', 'context' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Simultaneous', 'context' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Prophylactic', 'context' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Therapeutic', 'context' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pre-treatment', 'context' => 'in_vitro', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Post-treatment', 'context' => 'in_vitro', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Simultaneous', 'context' => 'in_vitro', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pre-treatment', 'context' => 'ex_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Post-treatment', 'context' => 'ex_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Simultaneous', 'context' => 'ex_vivo', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Insert study categories
        DB::table('study_categories')->insert([
            // Clinical Trial Design
            ['name' => 'Randomized', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Double-Blinded', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Placebo-Controlled', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Controlled', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cross-over', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Parallel', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            
            // Observational Studies
            ['name' => 'Cohort', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Case-Control', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cross-Sectional', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Case Series', 'category_type' => 'clinical', 'created_at' => now(), 'updated_at' => now()],
            
            // In Vivo Types
            ['name' => 'Human Study', 'category_type' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Animal Study', 'category_type' => 'in_vivo', 'created_at' => now(), 'updated_at' => now()],
            
            // Non-Experimental
            ['name' => 'Review', 'category_type' => 'non_experimental', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Meta-analysis', 'category_type' => 'non_experimental', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Systematic Review', 'category_type' => 'non_experimental', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Opinion Piece', 'category_type' => 'non_experimental', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hypothesis', 'category_type' => 'non_experimental', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order to handle foreign key constraints
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('graph_data');
        Schema::dropIfExists('tutorials');
        Schema::dropIfExists('article_claims');
        Schema::dropIfExists('article_feedback');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('portal_articles');
        
        Schema::dropIfExists('article_biomarker_categories');
        Schema::dropIfExists('article_biomarkers');
        Schema::dropIfExists('change_directions');
        
        Schema::dropIfExists('article_topical_protocols');
        Schema::dropIfExists('article_cell_culture_protocols');
        Schema::dropIfExists('article_ingestion_protocols');
        Schema::dropIfExists('article_inhalation_protocols');
        Schema::dropIfExists('article_administration_methods');
        Schema::dropIfExists('article_experimental_design');
        Schema::dropIfExists('brands');
        
        Schema::dropIfExists('article_study_durations');
        Schema::dropIfExists('article_outcomes');
        Schema::dropIfExists('article_outcome_types');
        Schema::dropIfExists('outcome_types');
        Schema::dropIfExists('article_timing_treatments');
        Schema::dropIfExists('timing_treatments');
        Schema::dropIfExists('article_research_topics');
        Schema::dropIfExists('article_diseases');
        Schema::dropIfExists('article_systems');
        Schema::dropIfExists('article_organs');
        Schema::dropIfExists('article_species_details');
        Schema::dropIfExists('article_species');
        Schema::dropIfExists('article_highlight_info');
        Schema::dropIfExists('article_study_categories');
        Schema::dropIfExists('study_categories');
        Schema::dropIfExists('article_study_types');
        
        Schema::dropIfExists('article_pdf_files');
        Schema::dropIfExists('article_countries');
        Schema::dropIfExists('article_keywords');
        Schema::dropIfExists('article_authors');
        Schema::dropIfExists('article_publication_details');
        Schema::dropIfExists('publishers');
        Schema::dropIfExists('journals');
        
        Schema::dropIfExists('articles');
        
        Schema::dropIfExists('bio_bridge');
        Schema::dropIfExists('bio_sub');
        Schema::dropIfExists('bio_categories');
        
        Schema::dropIfExists('verified_authors');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('administration_methods');
        Schema::dropIfExists('study_types');
        Schema::dropIfExists('research_topics');
        Schema::dropIfExists('systems');
        Schema::dropIfExists('organs');
        Schema::dropIfExists('diseases');
        Schema::dropIfExists('species');
        Schema::dropIfExists('countries');
        
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};