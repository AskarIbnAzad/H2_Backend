<?php
// ============================================================================
// FILE: app/Models/StudyType.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyType extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(StudyType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(StudyType::class, 'parent_id');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_study_types')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}