<?php
// ============================================================================
// FILE: app/Models/GraphData.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraphData extends Model
{
    protected $table = 'graph_data';

    protected $fillable = ['lbl', 'type', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];
}