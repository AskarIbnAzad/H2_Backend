<?php
// ============================================================================
// FILE: app/Models/VerifiedAuthor.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifiedAuthor extends Model
{
    protected $table = 'verified_authors';

    protected $fillable = [
        'name', 'orcid', 'email', 'institution_affiliation', 
        'author_h_index', 'parent_id', 'is_featured'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'author_h_index' => 'integer',
    ];

    // Hierarchical
    public function parent()
    {
        return $this->belongsTo(VerifiedAuthor::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(VerifiedAuthor::class, 'parent_id');
    }


     // Articles - ✅ FIXED: Use 'author_id' instead of default
    public function articles()
    {
        return $this->belongsToMany(
            Article::class, 
            'article_authors',      // pivot table
            'author_id',            // ✅ FIXED: foreign key for VerifiedAuthor
            'article_id'            // foreign key for Article
        )
        ->withPivot('author_order', 'affiliation', 'is_corresponding', 'verified')
        ->withTimestamps();
    }
}