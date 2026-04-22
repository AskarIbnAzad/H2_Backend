<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $table = 'saved_searches';

    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'search_data',
    ];
}
