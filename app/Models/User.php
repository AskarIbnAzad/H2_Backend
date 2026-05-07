<?php

// ============================================================================
// FILE: app/Models/User.php (Enhanced)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Articles as reviewer
    public function reviewingArticles()
    {
        return $this->hasMany(Article::class, 'reviewer_id');
    }

    // Articles where this user is the reviewer
    public function reviewerArticles()
    {
        return $this->hasMany(Article::class, 'reviewer_id');
    }

    // Articles verified by user
    public function verifiedArticles()
    {
        return $this->hasMany(Article::class, 'verified_by');
    }

    // Articles added by user
    public function addedArticles()
    {
        return $this->hasMany(Article::class, 'added_by');
    }

    // Revisions made by user
    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class, 'changed_by');
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
}
