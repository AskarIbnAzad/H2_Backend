<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BioBridge extends Model
{
    protected $table = 'bio_bridge';

    use HasFactory;

    protected $fillable = [
        'cat_id',
        'sub_id',
    ];
}
