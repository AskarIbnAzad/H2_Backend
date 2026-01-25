<?php
// ============================================================================
// FILE: app/Models/ArticleHighlightInfo.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleHighlightInfo extends Model
{
    protected $table = 'article_highlight_info';

    protected $fillable = [
        'article_id', 'description', 'description_verified'
    ];

    protected $casts = [
        'description_verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}