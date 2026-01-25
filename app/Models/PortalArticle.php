<?php
// ============================================================================
// FILE: app/Models/PortalArticle.php (Legacy - keep for migration)
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalArticle extends Model
{
    protected $fillable = [
        'title', 'authors', 'year', 'country', 'grant_country', 'research_country',
        'pmid', 'doi', 'abstract', 'publisher', 'journal', 'journal_url',
        'volume', 'pages', 'impact_factor', 'h_index', 'sci_mago', 'outcome',
        'admin_approval'
    ];
}