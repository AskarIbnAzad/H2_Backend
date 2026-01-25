<?php
// ============================================================================
// FILE: app/Models/ArticleStudyDuration.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleStudyDuration extends Model
{
    protected $fillable = [
        'article_id', 'duration_value', 'duration_unit', 'context', 'verified'
    ];

    protected $casts = [
        'duration_value' => 'integer',
        'verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}