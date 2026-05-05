<?php
// ============================================================================
// FILE: app/Models/Organ.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organ extends Model
{
    protected $fillable = ['name', 'parent_id', 'status', 'image', 'short_description', 'description',];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(Organ::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Organ::class, 'parent_id');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_organs')
                    ->withPivot('verified')
                    ->withTimestamps();
    }

    /**
     * Diseases linked to this organ.
     */
    public function diseases()
    {
        return $this->belongsToMany(Disease::class, 'disease_organ');
    }
}
