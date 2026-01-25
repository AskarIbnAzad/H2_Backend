<?php
// ============================================================================
// FILE: app/Models/ArticleRevision.php (renamed from FinalArticleRevision)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleRevision extends Model
{
    protected $table = 'article_revisions';

    public $timestamps = false;

    protected $fillable = [
        'article_id', 'changed_by', 'changes', 'created_at'
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}