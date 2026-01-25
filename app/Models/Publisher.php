<?php
// ============================================================================
// FILE: app/Models/Publisher.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publisher extends Model
{
    protected $fillable = ['name'];

    public function articles()
    {
        return $this->hasMany(ArticlePublicationDetail::class);
    }
}