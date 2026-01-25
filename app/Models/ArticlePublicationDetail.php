<?php
// ============================================================================
// FILE: app/Models/ArticlePublicationDetail.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticlePublicationDetail extends Model
{
    protected $fillable = [
        'article_id', 'title', 'abstract', 'year', 'volume', 'issue', 'pages',
        'journal_id', 'publisher_id', 'title_verified', 'abstract_verified',
        'year_verified', 'volume_verified', 'pages_verified'
    ];

    protected $casts = [
        'year' => 'integer',
        'title_verified' => 'boolean',
        'abstract_verified' => 'boolean',
        'year_verified' => 'boolean',
        'volume_verified' => 'boolean',
        'pages_verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }
}