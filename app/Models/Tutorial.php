<?php
// ============================================================================
// FILE: app/Models/Tutorial.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tutorial extends Model
{
    protected $fillable = ['title', 'description', 'video_url'];
}