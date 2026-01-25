<?php
// ============================================================================
// FILE: app/Models/Species.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Species extends Model
{
    protected $table = 'species';

    protected $fillable = ['name', 'parent_id', 'status'];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(Species::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Species::class, 'parent_id');
    }

    public function childrenRecursive()
    {
        return $this->hasMany(Species::class, 'parent_id')->with('childrenRecursive');
    }

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_species')
                    ->withPivot('verified')
                    ->withTimestamps();
    }

    public function articleDetails()
    {
        return $this->hasMany(ArticleSpeciesDetail::class);
    }

    public function inhalationProtocols()
    {
        return $this->hasMany(ArticleInhalationProtocol::class);
    }

    public function ingestionProtocols()
    {
        return $this->hasMany(ArticleIngestionProtocol::class);
    }
}