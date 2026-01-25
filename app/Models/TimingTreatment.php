<?php
// ============================================================================
// FILE: app/Models/TimingTreatment.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimingTreatment extends Model
{
    protected $fillable = ['name', 'context'];

    // Articles
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_timing_treatments')
                    ->withPivot('verified')
                    ->withTimestamps();
    }

    // Scopes
    public function scopeInVivo($query)
    {
        return $query->where('context', 'in_vivo');
    }

    public function scopeInVitro($query)
    {
        return $query->where('context', 'in_vitro');
    }

    public function scopeExVivo($query)
    {
        return $query->where('context', 'ex_vivo');
    }
}