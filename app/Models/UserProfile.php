<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'photo',
        'designation',
        'institution',
        'department',
        'country',
        'bio',
        'research_interests',
        'skills',
        'personal_website_url',
        'orcid_id',
        'publications',
    ];

    protected $casts = [
        'research_interests' => 'array',
        'skills' => 'array',
        'publications' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
