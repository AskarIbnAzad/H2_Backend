<?php
// ============================================================================
// FILE: app/Models/Disease.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    protected $table = 'diseases';

    protected $fillable = ['name', 'parent_id', 'status', 'short_description', 'description'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(Disease::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Disease::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->hasMany(Disease::class, 'parent_id')->with('childrenRecursive');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_diseases')
                    ->withPivot('disease_model_description', 'verified')
                    ->withTimestamps();
    }

    /**
     * Organs linked to this disease.
     */
    public function organs()
    {
        return $this->belongsToMany(Organ::class, 'disease_organ');
    }
}
