<?php
// ============================================================================
// FILE: app/Models/System.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(System::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(System::class, 'parent_id');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_systems')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}