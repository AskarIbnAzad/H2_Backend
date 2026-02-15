<?php
// ============================================================================
// FILE: app/Models/BioSub.php (Enhanced - Biomarker)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BioSub extends Model
{
    protected $table = 'bio_sub';

    protected $fillable = ['name', 'status', 'parent_id'];

    // Many-to-Many with Categories
    public function categories()
    {
        return $this->belongsToMany(BioCategory::class, 'bio_bridge', 'sub_id', 'cat_id');
    }

    // Articles using this biomarker
    public function articleBiomarkers()
    {
        return $this->hasMany(ArticleBiomarker::class, 'biomarker_id');
    }
}
