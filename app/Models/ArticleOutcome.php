<?php
// ============================================================================
// FILE: app/Models/ArticleOutcome.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleOutcome extends Model
{
    protected $fillable = [
        'article_id', 'outcome_description', 'outcome_verified'
    ];

    protected $casts = [
        'outcome_verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}