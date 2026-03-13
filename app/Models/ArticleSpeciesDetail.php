<?php
// ============================================================================
// FILE: app/Models/ArticleSpeciesDetail.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleSpeciesDetail extends Model
{
    protected $fillable = [
        'article_id', 'species_id', 'number_of_subjects', 'health_status', 'gender',
        'average_age', 'age_unit', 'age_range_min', 'age_range_max',
        'average_weight', 'weight_unit', 'weight_range_min', 'weight_range_max',
        'description', 'subjects_verified', 'health_verified', 'gender_verified',
        'age_verified', 'weight_verified', 'age_data_source', 'weight_data_source',
    ];

    protected $casts = [
        'number_of_subjects' => 'integer',
        'average_age' => 'decimal:2',
        'age_range_min' => 'decimal:2',
        'age_range_max' => 'decimal:2',
        'average_weight' => 'decimal:2',
        'weight_range_min' => 'decimal:2',
        'weight_range_max' => 'decimal:2',
        'subjects_verified' => 'boolean',
        'health_verified' => 'boolean',
        'gender_verified' => 'boolean',
        'age_verified' => 'boolean',
        'weight_verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function species()
    {
        return $this->belongsTo(Species::class);
    }
}
