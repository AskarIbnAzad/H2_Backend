<?php
// ============================================================================
// FILE: app/Models/BioCategory.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BioCategory extends Model
{
    protected $table = 'bio_categories';

    protected $fillable = ['name', 'status'];

    // Many-to-Many with BioSub (biomarkers)
    public function biomarkers()
    {
        return $this->belongsToMany(BioSub::class, 'bio_bridge', 'cat_id', 'sub_id');
    }

    public function articleBiomarkers()
    {
        return $this->belongsToMany(ArticleBiomarker::class, 'article_biomarker_categories', 'bio_category_id', 'article_biomarker_id');
    }
}