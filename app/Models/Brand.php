<?php
// ============================================================================
// FILE: app/Models/Brand.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'name', 'manufacturer', 'country_id', 'website'
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function experimentalDesigns()
    {
        return $this->hasMany(ArticleExperimentalDesign::class);
    }
}