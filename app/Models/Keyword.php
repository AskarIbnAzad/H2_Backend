<?php
// ============================================================================
// FILE: app/Models/Keyword.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    protected $fillable = ['keyword', 'status'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_keywords')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}