<?php
// ============================================================================
// FILE: app/Models/ArticlePdfFile.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticlePdfFile extends Model
{
    protected $fillable = [
        'article_id', 'url', 'is_paywall', 'file_size_mb', 'verified'
    ];

    protected $casts = [
        'is_paywall' => 'boolean',
        'verified' => 'boolean',
        'file_size_mb' => 'decimal:2',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}