<?php
// ============================================================================
// FILE: app/Models/OutcomeType.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutcomeType extends Model
{
    protected $fillable = ['name'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_outcome_types')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}