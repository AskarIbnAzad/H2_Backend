<?php
// ============================================================================
// FILE: app/Models/Country.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'parent_id', 'status'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(Country::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Country::class, 'parent_id')->with('children');
    }

    public function childrenRecursive()
    {
        return $this->hasMany(Country::class, 'parent_id')->with('childrenRecursive');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_countries')
                    ->withPivot('country_type', 'verified')
                    ->withTimestamps();
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }
}