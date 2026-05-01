<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $table = 'articles';

    protected $fillable = [
        'mhid', 'doi', 'pmid', 'reviewer_id', 'verified_by', 'added_by',
        'status', 'is_trending', 'is_highlighted', 'rank_score', 'folder_id'
    ];

    protected $casts = [
        'is_trending' => 'boolean',
        'is_highlighted' => 'boolean',
        'rank_score' => 'integer',
    ];

    // ==================== Relationships ====================

    public function folder() {
        return $this->belongsTo(Folder::class);
    }

    // Users
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // Publication Details
    public function publicationDetail()
    {
        return $this->hasOne(ArticlePublicationDetail::class);
    }

    // Authors (Many-to-Many)
    public function getAuthors()
    {
        $authors = VerifiedAuthor::withCount([
            'articles' => function ($q) {
                $q->where('status', 'Verified');
            },
            'children',
        ])
            ->having('articles_count', '>', 0)
            ->with('children')
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $authors->map(function ($author) {
            return [
                'id' => $author->id,
                'name' => $author->name,
                'is_featured' => $author->is_featured ? 1 : 0,
                'article_count' => $author->articles_count,
                'total_occurrences' => $author->articles_count,
                'children_count' => $author->children_count,
                'children' => $author->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'parent_id' => $child->parent_id,
                        'is_featured' => $child->is_featured ? 1 : 0,
                    ];
                })->toArray(),
                'created_at' => $author->created_at->toIso8601String(),
                'updated_at' => $author->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => true,
            'authors' => $transformed,
        ]);
    }

    // Keywords (Many-to-Many)
    public function keywords()
    {
        return $this->belongsToMany(Keyword::class, 'article_keywords')
            // ->withPivot('verified')
            ->withTimestamps();
    }

    // Countries (Many-to-Many with type)
    public function countries()
    {
        return $this->belongsToMany(Country::class, 'article_countries')
            ->withPivot('country_type', 'verified')
            ->withTimestamps();
    }

    public function publicationCountries()
    {
        return $this->countries()->wherePivot('country_type', 'publication');
    }

    public function grantCountries()
    {
        return $this->countries()->wherePivot('country_type', 'grant');
    }

    public function researchCountries()
    {
        return $this->countries()->wherePivot('country_type', 'research');
    }

    // PDF Files
    public function pdfFiles()
    {
        return $this->hasMany(ArticlePdfFile::class);
    }

    // Study Types (Many-to-Many)
    public function studyTypes()
    {
        return $this->belongsToMany(StudyType::class, 'article_study_types')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Study Categories (Many-to-Many)
    public function studyCategories()
    {
        return $this->belongsToMany(StudyCategory::class, 'article_study_categories')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Highlight Info
    public function highlightInfo()
    {
        return $this->hasOne(ArticleHighlightInfo::class);
    }

    // Species (Many-to-Many)
    public function species()
    {
        return $this->belongsToMany(Species::class, 'article_species')
            ->withPivot('verified')
            ->withTimestamps();
    }

    public function speciesDetails()
    {
        return $this->hasMany(ArticleSpeciesDetail::class);
    }

    // Organs (Many-to-Many)
    public function organs()
    {
        return $this->belongsToMany(Organ::class, 'article_organs')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Systems (Many-to-Many)
    public function systems()
    {
        return $this->belongsToMany(System::class, 'article_systems')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Diseases (Many-to-Many)
    public function diseases()
    {
        return $this->belongsToMany(Disease::class, 'article_diseases')
            ->withPivot('disease_model_description', 'verified')
            ->withTimestamps();
    }

    // Research Topics (Many-to-Many)
    public function researchTopics()
    {
        return $this->belongsToMany(ResearchTopic::class, 'article_research_topics')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Timing Treatments (Many-to-Many)
    public function timingTreatments()
    {
        return $this->belongsToMany(TimingTreatment::class, 'article_timing_treatments')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Outcome Types (Many-to-Many)
    public function outcomeTypes()
    {
        return $this->belongsToMany(OutcomeType::class, 'article_outcome_types')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Outcome Description
    public function outcome()
    {
        return $this->hasOne(ArticleOutcome::class);
    }

    // Study Durations
    public function studyDurations()
    {
        return $this->hasMany(ArticleStudyDuration::class);
    }


    // Experimental Design
    public function experimentalDesign()
    {
        return $this->hasOne(ArticleExperimentalDesign::class);
    }

    // Administration Methods (Many-to-Many)
    public function administrationMethods()
    {
        return $this->belongsToMany(AdministrationMethod::class, 'article_administration_methods')
            ->withPivot('verified')
            ->withTimestamps();
    }

    // Protocols
    public function inhalationProtocols()
    {
        return $this->hasMany(ArticleInhalationProtocol::class);
    }

    public function ingestionProtocols()
    {
        return $this->hasMany(ArticleIngestionProtocol::class);
    }

    public function cellCultureProtocols()
    {
        return $this->hasMany(ArticleCellCultureProtocol::class);
    }

    public function topicalProtocols()
    {
        return $this->hasMany(ArticleTopicalProtocol::class);
    }

    // Biomarkers
    public function biomarkers()
    {
        return $this->hasMany(ArticleBiomarker::class);
    }

    // Support Tables
    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class)->latest();
    }

    public function feedback()
    {
        return $this->hasMany(ArticleFeedback::class);
    }

    public function claims()
    {
        return $this->hasMany(ArticleClaim::class, 'final_article_id');
    }



 // One-to-Many Relationships (use plural names)


    public function outcomes()  // ✅ Plural
    {
        return $this->hasMany(ArticleOutcome::class);
    }







    // ==================== Scopes ====================

    public function scopeTrending($query)
    {
        return $query->where('is_trending', true);
    }

    public function scopeHighlighted($query)
    {
        return $query->where('is_highlighted', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'Verified');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function authors()
    {
        return $this->belongsToMany(
            VerifiedAuthor::class,
            'article_authors',
            'article_id',
            'author_id'
        )
            ->whereNull('verified_authors.parent_id')
            ->withPivot('author_order', 'affiliation', 'is_corresponding', 'verified')
            ->withTimestamps()
            ->orderBy('article_authors.author_order');
    }
}
