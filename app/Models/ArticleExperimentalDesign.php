<?php
// ============================================================================
// FILE: app/Models/ArticleExperimentalDesign.php
// ============================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleExperimentalDesign extends Model
{
    protected $table = 'article_experimental_design';

    protected $fillable = [
        'article_id', 'brand_id', 'is_commercial_product',
        'has_pharmacokinetics', 'pharmacokinetics_description',
        'has_dose_comparison', 'dose_comparison_description',
        'has_dose_dependent_effect',
        'has_drug_comparison', 'drug_comparison_description',
        'has_method_admin_comparison', 'method_admin_comparison_description',
        'is_erw', 'erw_comparison_description', 'ph_value',
        'uses_oxyhydrogen',
        'has_safety_focus', 'safety_profile_description',
        'has_adverse_effects', 'adverse_effects_description',
        'includes_pregnant_breastfeeding',
        'has_responder_difference', 'has_sex_difference',
        'has_gene_expression_data', 'gene_expression_description',
        'has_mechanistic_insights', 'mechanistic_insights_description',
        'study_url', 'video_webpage_url', 'verified'
    ];

    protected $casts = [
        'is_commercial_product' => 'boolean',
        'has_pharmacokinetics' => 'boolean',
        'has_dose_comparison' => 'boolean',
        'has_dose_dependent_effect' => 'boolean',
        'has_drug_comparison' => 'boolean',
        'has_method_admin_comparison' => 'boolean',
        'is_erw' => 'boolean',
        'ph_value' => 'decimal:2',
        'uses_oxyhydrogen' => 'boolean',
        'has_safety_focus' => 'boolean',
        'has_adverse_effects' => 'boolean',
        'includes_pregnant_breastfeeding' => 'boolean',
        'has_responder_difference' => 'boolean',
        'has_sex_difference' => 'boolean',
        'has_gene_expression_data' => 'boolean',
        'has_mechanistic_insights' => 'boolean',
        'verified' => 'boolean',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}