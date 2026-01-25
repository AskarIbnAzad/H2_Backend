<?php
// ============================================================================
// FILE: app/Models/Organ.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organ extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

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
}