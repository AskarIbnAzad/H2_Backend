<?php
// ============================================================================
// FILE: app/Models/StudyCategory.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyCategory extends Model
{
    protected $fillable = ['name', 'category_type', 'parent_id'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(StudyCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(StudyCategory::class, 'parent_id');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_study_categories')
                    ->withPivot('verified')
                    ->withTimestamps();
    }

    // Scopes
    public function scopeInVivo($query)
    {
        return $query->where('category_type', 'in_vivo');
    }

    public function scopeInVitro($query)
    {
        return $query->where('category_type', 'in_vitro');
    }

    public function scopeClinical($query)
    {
        return $query->where('category_type', 'clinical');
    }
}