<?php
// ============================================================================
// FILE: app/Models/ArticleClaim.php (renamed from Claim)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleClaim extends Model
{
    protected $table = 'article_claims';

    protected $fillable = [
        'full_name', 'email', 'affiliation', 'position_title', 'orcid_id',
        'explanation', 'supporting_evidence', 'final_article_id', 'status', 'user_id'
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'final_article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}