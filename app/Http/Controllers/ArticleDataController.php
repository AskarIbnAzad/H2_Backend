<?php

// ============================================================================
// FILE: app/Http/Controllers/ArticleDataController.php
// COMPLETE REWRITE - Using Normalized Structure
// ============================================================================

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\BioSub;
use App\Models\Country;
use App\Models\Disease;
use App\Models\Keyword;
use App\Models\Organ;
use App\Models\ResearchTopic;
use App\Models\Species;
use App\Models\StudyType;
use App\Models\VerifiedAuthor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleDataController extends Controller
{
    /**
     * Add data to article (normalized structure)
     * POST /api/article-data/add
     */
    public function addData(Request $request)
    {
        $request->validate([
            'article_id' => 'required|array',
            'article_id.*' => 'required|exists:articles,id',
            'field' => 'required|string',
            'identifier' => 'required',
        ]);

        $articleIds = $request->article_id;
        $field = $request->field;
        $identifier = $request->identifier;

        DB::beginTransaction();
        try {
            // Process each article
            foreach ($articleIds as $articleId) {
                $article = Article::findOrFail($articleId);
                $this->processFieldAddition($article, $field, $identifier);
            }

            DB::commit();

            $count = count($articleIds);

            return response()->json([
                'status' => true,
                'message' => ucfirst($field).' added successfully to '.$count.' article(s)',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to add data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove data from article
     * POST /api/article-data/remove
     */
    public function removeData(Request $request)
    {
        $request->validate([
            'article_id' => 'required|array',
            'article_id.*' => 'required|exists:articles,id',
            'field' => 'required|string',
            'identifier' => 'required',
        ]);

        $articleIds = $request->article_id;
        $field = $request->field;
        $identifier = $request->identifier;

        DB::beginTransaction();
        try {
            // Process each article
            foreach ($articleIds as $articleId) {
                $article = Article::findOrFail($articleId);
                $this->processFieldRemoval($article, $field, $identifier);
            }

            DB::commit();

            $count = count($articleIds);

            return response()->json([
                'status' => true,
                'message' => ucfirst($field).' removed successfully from '.$count.' article(s)',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to remove data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific field data from article
     * POST /api/article-data/get
     */
    public function getData(Request $request)
    {
        $request->validate([
            'article_id' => 'required|exists:articles,id',
            'field' => 'required|string',
        ]);

        $article = Article::findOrFail($request->article_id);
        $field = $request->field;

        try {
            $data = $this->getFieldData($article, $field);

            return response()->json([
                'status' => true,
                'field' => $field,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to get data: '.$e->getMessage(),
            ], 500);
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - ADDITION
    // ============================================================================

    private function processFieldAddition($article, $field, $identifier)
    {
        switch ($field) {
            case 'species':
              
                
                if (!$article->species()->where('species.id', $identifier)->exists()) {
                    $article->species()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'organs':
                if (! $article->organs()->where('organs.id', $identifier)->exists()) {
                    $article->organs()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'systems':
                if (! $article->systems()->where('systems.id', $identifier)->exists()) {
                    $article->systems()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'researchTopics':
                if (! $article->researchTopics()->where('research_topics.id', $identifier)->exists()) {
                    $article->researchTopics()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'diseases':
                if (! $article->diseases()->where('diseases.id', $identifier)->exists()) {
                    $article->diseases()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'authors':
                if (! $article->authors()->where('verified_authors.id', $identifier)->exists()) {
                    // Get next author order
                    $maxOrder = $article->authors()->max('author_order') ?? 0;
                    $article->authors()->attach($identifier, [
                        'author_order' => $maxOrder + 1,
                        'is_corresponding' => false,
                        'verified' => true,
                    ]);
                }
                break;

            case 'countries':
                // Check if country with publication type already exists
                $exists = $article->countries()
                    ->wherePivot('country_id', $identifier)
                    ->wherePivot('country_type', 'publication')
                    ->exists();

                if (! $exists) {
                    $article->countries()->attach($identifier, [
                        'country_type' => 'publication',
                        'verified' => true,
                    ]);
                }
                break;

            case 'researchCountries':
                $exists = $article->countries()
                    ->wherePivot('country_id', $identifier)
                    ->wherePivot('country_type', 'research')
                    ->exists();

                if (! $exists) {
                    $article->countries()->attach($identifier, [
                        'country_type' => 'research',
                        'verified' => true,
                    ]);
                }
                break;

            case 'grantCountries':
                $exists = $article->countries()
                    ->wherePivot('country_id', $identifier)
                    ->wherePivot('country_type', 'grant')
                    ->exists();

                if (! $exists) {
                    $article->countries()->attach($identifier, [
                        'country_type' => 'grant',
                        'verified' => true,
                    ]);
                }
                break;

            case 'keywords':
                if (! $article->keywords()->where('keywords.id', $identifier)->exists()) {
                    $article->keywords()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'biomarkers':
                // Check if biomarker already exists for this article
                $exists = \App\Models\ArticleBiomarker::where('article_id', $article->id)
                    ->where('biomarker_id', $identifier)
                    ->exists();

                if (! $exists) {
                    \App\Models\ArticleBiomarker::create([
                        'article_id' => $article->id,
                        'biomarker_id' => $identifier,
                        'biomarker_verified' => true,
                    ]);
                }
                break;

            case 'studyTypes':
                if (! $article->studyTypes()->where('study_types.id', $identifier)->exists()) {
                    $article->studyTypes()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'administrationMethods':
                if (! $article->administrationMethods()->where('administration_methods.id', $identifier)->exists()) {
                    $article->administrationMethods()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'outcomeTypes':
                if (! $article->outcomeTypes()->where('outcome_types.id', $identifier)->exists()) {
                    $article->outcomeTypes()->attach($identifier, ['verified' => true]);
                }
                break;

            case 'timingTreatments':
                if (! $article->timingTreatments()->where('timing_treatments.id', $identifier)->exists()) {
                    $article->timingTreatments()->attach($identifier, ['verified' => true]);
                }
                break;

            default:
                throw new \Exception("Unknown field: {$field}");
        }
    }

    private function addSpecies($article, $data)
    {
        $speciesId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($speciesId)) {
            $species = Species::find($speciesId);
        } else {
            $species = Species::firstOrCreate(['name' => $speciesId, 'status' => 'Active']);
        }

        if ($species && ! $article->species()->where('species.id', $species->id)->exists()) {
            $article->species()->attach($species->id, ['verified' => false]);
        }
    }

    private function addOrgan($article, $data)
    {
        $organId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($organId)) {
            $organ = Organ::find($organId);
        } else {
            $organ = Organ::firstOrCreate(['name' => $organId, 'status' => 'Active']);
        }

        if ($organ && ! $article->organs()->where('organs.id', $organ->id)->exists()) {
            $article->organs()->attach($organ->id, ['verified' => false]);
        }
    }

    private function addSystem($article, $data)
    {
        $systemId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($systemId)) {
            $system = \App\Models\System::find($systemId);
        } else {
            $system = \App\Models\System::firstOrCreate(['name' => $systemId, 'status' => 'Active']);
        }

        if ($system && ! $article->systems()->where('systems.id', $system->id)->exists()) {
            $article->systems()->attach($system->id, ['verified' => false]);
        }
    }

    private function addResearchTopic($article, $data)
    {
        $topicId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($topicId)) {
            $topic = ResearchTopic::find($topicId);
        } else {
            $topic = ResearchTopic::firstOrCreate(['name' => $topicId, 'status' => 'Active']);
        }

        if ($topic && ! $article->researchTopics()->where('research_topics.id', $topic->id)->exists()) {
            $article->researchTopics()->attach($topic->id, ['verified' => false]);
        }
    }

    private function addDisease($article, $data)
    {
        // Only one disease allowed (business rule)
        $diseaseId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($diseaseId)) {
            $disease = Disease::find($diseaseId);
        } else {
            $disease = Disease::firstOrCreate(['name' => $diseaseId, 'status' => 'Active']);
        }

        if ($disease) {
            // Remove existing diseases
            $article->diseases()->detach();

            // Add new disease
            $article->diseases()->attach($disease->id, ['verified' => false]);
        }
    }

    private function addAuthor($article, $data)
    {
        $authorName = is_array($data) ? ($data['name'] ?? $data) : $data;
        $affiliation = is_array($data) ? ($data['affiliation'] ?? null) : null;

        $author = VerifiedAuthor::firstOrCreate(['name' => $authorName]);

        if (! $article->authors()->where('verified_authors.id', $author->id)->exists()) {
            // Get next author order
            $maxOrder = $article->authors()->max('article_authors.author_order') ?? 0;

            $article->authors()->attach($author->id, [
                'author_order' => $maxOrder + 1,
                'affiliation' => $affiliation,
                'is_corresponding' => false,
                'verified' => false,
            ]);
        }
    }

    private function addCountry($article, $data, $type)
    {
        $countryId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($countryId)) {
            $country = Country::find($countryId);
        } else {
            $country = Country::firstOrCreate(['name' => $countryId, 'status' => 'Active']);
        }

        if ($country) {
            // Check if already exists for this type
            $exists = $article->countries()
                ->where('countries.id', $country->id)
                ->where('article_countries.country_type', $type)
                ->exists();

            if (! $exists) {
                $article->countries()->attach($country->id, [
                    'country_type' => $type,
                    'verified' => false,
                ]);
            }
        }
    }

    private function addKeywords($article, $data)
    {
        $keywordString = is_array($data) ? ($data['keyword'] ?? $data) : $data;

        // Handle comma-separated keywords
        $keywords = array_map('trim', explode(',', $keywordString));

        foreach ($keywords as $keywordName) {
            if (empty($keywordName)) {
                continue;
            }

            $keyword = Keyword::firstOrCreate(['keyword' => $keywordName, 'status' => 'Active']);

            if (! $article->keywords()->where('keywords.id', $keyword->id)->exists()) {
                $article->keywords()->attach($keyword->id, ['verified' => false]);
            }
        }
    }

    private function addBiomarker($article, $data)
    {
        $biomarkerId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($biomarkerId)) {
            $biomarker = BioSub::find($biomarkerId);
        } else {
            $biomarker = BioSub::firstOrCreate(['name' => $biomarkerId, 'status' => 'Pending']);
        }

        if ($biomarker) {
            // Create article biomarker record
            $exists = $article->biomarkers()
                ->where('biomarker_id', $biomarker->id)
                ->exists();

            if (! $exists) {
                $article->biomarkers()->create([
                    'biomarker_id' => $biomarker->id,
                    'is_measured' => true,
                    'verified' => false,
                ]);
            }
        }
    }

    private function addStudyType($article, $data)
    {
        $studyTypeId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($studyTypeId)) {
            $studyType = StudyType::find($studyTypeId);
        } else {
            $studyType = StudyType::firstOrCreate(['name' => $studyTypeId, 'status' => 'Active']);
        }

        if ($studyType && ! $article->studyTypes()->where('study_types.id', $studyType->id)->exists()) {
            $article->studyTypes()->attach($studyType->id, ['verified' => false]);
        }
    }

    private function addAdministrationMethod($article, $data)
    {
        $methodId = is_array($data) ? ($data['id'] ?? $data['name']) : $data;

        if (is_numeric($methodId)) {
            $method = \App\Models\AdministrationMethod::find($methodId);
        } else {
            $method = \App\Models\AdministrationMethod::firstOrCreate(['name' => $methodId, 'status' => 'Active']);
        }

        if ($method && ! $article->administrationMethods()->where('administration_methods.id', $method->id)->exists()) {
            $article->administrationMethods()->attach($method->id, ['verified' => false]);
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - REMOVAL
    // ============================================================================

    private function processFieldRemoval($article, $field, $identifier)
    {
        switch ($field) {
            case 'species':
                $article->species()->detach($identifier);
                break;

            case 'organs':
                $article->organs()->detach($identifier);
                break;

            case 'systems':
                $article->systems()->detach($identifier);
                break;

            case 'researchTopics':
            case 'researchtopics':
                $article->researchTopics()->detach($identifier);
                break;

            case 'diseases':
                $article->diseases()->detach($identifier);
                break;

            case 'authors':
                $article->authors()->detach($identifier);
                break;

            case 'countries':
                $article->countries()->detach($identifier);
                break;

            case 'keywords':
                $article->keywords()->detach($identifier);
                break;

            case 'biomarkers':
                \App\Models\ArticleBiomarker::where('article_id', $article->id)
                    ->where('biomarker_id', $identifier)
                    ->delete();
                break;

            case 'studyTypes':
                // FIXED: Changed from studyType() to studyTypes()
                $article->studyTypes()->detach($identifier);
                break;

            case 'administrationMethods':
                $article->administrationMethods()->detach($identifier);
                break;

            case 'outcomeTypes':
                $article->outcomeTypes()->detach($identifier);
                break;

            case 'timingTreatments':
                $article->timingTreatments()->detach($identifier);
                break;

            default:
                throw new \Exception("Unknown field: {$field}");
        }
    }

    // ============================================================================
    // PRIVATE HELPER METHODS - DATA RETRIEVAL
    // ============================================================================

    private function getFieldData($article, $field)
    {
        switch ($field) {
            case 'species':
                return $article->species;

            case 'organ':
                return $article->organs;

            case 'system':
                return $article->systems;

            case 'researchTopic':
            case 'researchtopic':
                return $article->researchTopics;

            case 'disease':
                return $article->diseases;

            case 'authors':
                return $article->authors()->orderBy('article_authors.author_order')->get();

            case 'countries':
                return $article->countries;

            case 'keywords':
                return $article->keywords;

            case 'biomarkers':
                return $article->biomarkers()->with('biomarker', 'changeDirection')->get();

            case 'studyType':
                return $article->studyTypes;

            case 'administrationMethod':
                return $article->administrationMethods;

            case 'publicationDetail':
                return $article->publicationDetail;

            default:
                throw new \Exception("Unknown field: {$field}");
        }
    }
}
