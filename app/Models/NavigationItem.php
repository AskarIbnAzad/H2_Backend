<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'type', 'name', 'path',
        'description', 'image', 'has_mega_menu',
        'is_active', 'sort_order'
    ];

    // Self referencing
    public function parent()
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')->orderBy('sort_order');
    }

    // Specific relations — all pointing to same table/model
    public function featured()
    {
        return $this->hasOne(NavigationItem::class, 'parent_id')
            ->where('type', 'featured');
    }

    public function sections()
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')
            ->where('type', 'section')
            ->orderBy('sort_order');
    }

    public function sectionItems()
    {
        return $this->hasMany(NavigationItem::class, 'parent_id')
            ->where('type', 'section_item')
            ->orderBy('sort_order');
    }
}
