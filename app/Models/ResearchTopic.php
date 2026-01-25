<?php
// ============================================================================
// FILE: app/Models/ResearchTopic.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchTopic extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(ResearchTopic::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ResearchTopic::class, 'parent_id');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_research_topics')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}