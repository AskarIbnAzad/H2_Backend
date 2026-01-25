<?php
// ============================================================================
// FILE: app/Models/ArticleIngestionProtocol.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleIngestionProtocol extends Model
{
    protected $fillable = [
        'article_id', 'species_id', 'volume_value', 'volume_unit',
        'concentration_value', 'concentration_unit',
        'absolute_dose_value', 'absolute_dose_unit',
        'relative_dose_value', 'relative_dose_unit',
        'duration_value', 'duration_unit', 'frequency', 'verified','administration_method',
        'peak_breath_hydrogen_value', 'peak_breath_hydrogen_unit',
    ];

    protected $casts = [
        'volume_value' => 'decimal:2',
        'concentration_value' => 'string',
        'absolute_dose_value' => 'decimal:4',
        'relative_dose_value' => 'decimal:4',
        'duration_value' => 'decimal:2',
        'verified' => 'boolean',
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