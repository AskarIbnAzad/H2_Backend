<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleFeedback extends Model
{
    protected $table = 'article_feedback';

    protected $fillable = [
        'user', 
        'article_id', 
        'page_url',
        'feedback', 
        'status'
    ];

    // IMPORTANT: Cast JSON fields to arrays
    protected $casts = [
        'user' => 'array',        // This will auto-decode JSON to array
        'feedback' => 'array',    // This will auto-decode JSON to array
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}