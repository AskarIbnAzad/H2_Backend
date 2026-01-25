<?php
// ============================================================================
// FILE: app/Models/ArticleTopicalProtocol.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleTopicalProtocol extends Model
{
    protected $fillable = [
        'article_id', 'species_id', 'application_method',
        'concentration_description', 'duration_frequency', 'verified'
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function species()
    {
        return $this->belongsTo(Species::class);
    }
}