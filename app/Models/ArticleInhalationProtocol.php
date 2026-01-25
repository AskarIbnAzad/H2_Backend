<?php
// ============================================================================
// FILE: app/Models/ArticleInhalationProtocol.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleInhalationProtocol extends Model
{
    protected $fillable = [
        'article_id', 'species_id', 'h2_percentage', 'o2_percentage', 'estimated_fih2',
        'flow_rate_value', 'flow_rate_unit', 'duration_value', 'duration_unit',
        'frequency', 'delivery_method', 'peak_breath_hydrogen_value','was_oxyhydrogen_used',
        'peak_breath_hydrogen_unit', 'verified'
    ];

    protected $casts = [
        'h2_percentage' => 'decimal:2',
        'o2_percentage' => 'decimal:2',
        'estimated_fih2' => 'decimal:2',
        'flow_rate_value' => 'decimal:2',
        'duration_value' => 'decimal:2',
        'peak_breath_hydrogen_value' => 'decimal:2',
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