<?php
// ============================================================================
// FILE: app/Models/AdministrationMethod.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdministrationMethod extends Model
{
    protected $fillable = ['name', 'status'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_administration_methods')
                    ->withPivot('verified')
                    ->withTimestamps();
    }
}