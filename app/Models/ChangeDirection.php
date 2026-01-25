<?php
// ============================================================================
// FILE: app/Models/ChangeDirection.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeDirection extends Model
{
    protected $fillable = ['name', 'direction'];

    public function articleBiomarkers()
    {
        return $this->hasMany(ArticleBiomarker::class, 'change_direction_id');
    }

    // Scopes
    public function scopeIncreased($query)
    {
        return $query->where('direction', 'increased');
    }

    public function scopeDecreased($query)
    {
        return $query->where('direction', 'decreased');
    }
}