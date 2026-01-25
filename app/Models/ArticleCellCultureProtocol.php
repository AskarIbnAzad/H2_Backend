<?php
// ============================================================================
// FILE: app/Models/ArticleCellCultureProtocol.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleCellCultureProtocol extends Model
{
    protected $fillable = [
        'article_id', 'cell_tissue_type', 'cell_line',
        'h2_concentration_value', 'h2_concentration_unit',
        'duration_value', 'duration_unit', 'frequency', 'verified'
    ];

    protected $casts = [
        // 'h2_concentration_value' => 'decimal:4',
        // 'duration_value' => 'decimal:2',
        'verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}