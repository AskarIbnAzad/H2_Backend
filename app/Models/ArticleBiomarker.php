<?php
// ============================================================================
// FILE: app/Models/ArticleBiomarker.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleBiomarker extends Model
{
    protected $fillable = [
        'article_id', 'biomarker_id', 'is_measured', 'change_direction_id',
        'protein_name', 'protein_verified',
        'baseline_value', 'baseline_unit',
        'post_treatment_value', 'post_treatment_unit',
        'change_percentage', 'p_value', 'measurement_notes', 'verified'
    ];

    protected $casts = [
        'is_measured' => 'boolean',
        'protein_verified' => 'boolean',
        'baseline_value' => 'decimal:4',
        'post_treatment_value' => 'decimal:4',
        'change_percentage' => 'decimal:2',
        'p_value' => 'decimal:8',
        'verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function biomarker()
    {
        return $this->belongsTo(BioSub::class, 'biomarker_id');
    }

    public function changeDirection()
    {
        return $this->belongsTo(ChangeDirection::class);
    }

    // Categories (Many-to-Many)
    public function categories()
    {
        return $this->belongsToMany(BioCategory::class, 'article_biomarker_categories', 'article_biomarker_id', 'bio_category_id');
    }
}