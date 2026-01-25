<?php
// ============================================================================
// FILE: app/Models/Journal.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $fillable = [
        'name', 'url', 'impact_factor', 'h_index', 'scimago_quartile', 'issn'
    ];

    protected $casts = [
        'impact_factor' => 'decimal:3',
        'h_index' => 'integer',
    ];

    public function articles()
    {
        return $this->hasMany(ArticlePublicationDetail::class);
    }
}