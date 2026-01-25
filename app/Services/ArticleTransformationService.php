<?php

// ============================================================================
// FILE: app/Services/ArticleTransformationService.php
// Handles conversion between old JSON format and new normalized structure
// ============================================================================

namespace App\Services;

use App\Models\AdministrationMethod;
use App\Models\Article;
use App\Models\ArticlePublicationDetail;
use App\Models\BioCategory;
use App\Models\BioSub;
use App\Models\ChangeDirection;
use App\Models\Country;
use App\Models\Disease;
use App\Models\Journal;
use App\Models\Keyword;
use App\Models\Organ;
use App\Models\Publisher;
use App\Models\ResearchTopic;
use App\Models\Species;
use App\Models\StudyType;
use App\Models\System;
use App\Models\VerifiedAuthor;
use Illuminate\Support\Facades\DB;

class ArticleTransformationService
{
    /**
     * Transform old JSON article to new normalized structure
     */
    public function transformToNormalized($oldArticle, $existingArticle = null)
    {
        DB::beginTransaction();

        try {
            // Decode JSON columns
            $publicData = is_string($oldArticle->publicData) ? json_decode($oldArticle->publicData, true) : $oldArticle->publicData;
            $articleGeneralData = is_string($oldArticle->articleGeneralData) ? json_decode($oldArticle->articleGeneralData, true) : $oldArticle->articleGeneralData;
            $researcherData = is_string($oldArticle->researcherData) ? json_decode($oldArticle->researcherData, true) : $oldArticle->researcherData;
            $biomaker = is_string($oldArticle->biomaker) ? json_decode($oldArticle->biomaker, true) : $oldArticle->biomaker;

            // ✅ 1. Handle Article Creation vs Update
            if ($existingArticle) {
                // UPDATE MODE: Use existing article
                $article = $existingArticle;

                $article->update([
                    'doi' => $oldArticle->doi,
                    'pmid' => $oldArticle->pmid,
                    'reviewer_id' => $oldArticle->reviewer_id,
                    'verified_by' => $oldArticle->verified_by,
                    'status' => $oldArticle->status,
                    'is_trending' => $oldArticle->is_trending ?? false,
                    'is_highlighted' => $this->parseBool($articleGeneralData['HighlightArticle']['name'] ?? false),
                    'rank_score' => $articleGeneralData['rankThisArticle']['name'] ?? null,
                    // Don't update: mhid, added_by (keep original)
                ]);

            } else {
                // CREATE MODE: Create new article
                $article = Article::create([
                    'mhid' => $oldArticle->mhid,
                    'doi' => $oldArticle->doi,
                    'pmid' => $oldArticle->pmid,
                    'reviewer_id' => $oldArticle->reviewer_id,
                    'verified_by' => $oldArticle->verified_by,
                    'added_by' => $oldArticle->addedBy ?? 1,
                    'status' => $oldArticle->status,
                    'is_trending' => $oldArticle->is_trending ?? false,
                    'is_highlighted' => $this->parseBool($articleGeneralData['HighlightArticle']['name'] ?? false),
                    'rank_score' => $articleGeneralData['rankThisArticle']['name'] ?? null,
                ]);

            }

            // 2. Publication Details
            $this->createPublicationDetails($article, $publicData);

            // 3. Authors
            $this->attachAuthors($article, $publicData['authors'] ?? []);

            // 4. Keywords
            $this->attachKeywords($article, $publicData['keywords']['name'] ?? '');

            // 5. Countries
            $this->attachCountries($article, $publicData, $articleGeneralData);

            // 6. PDF Files (don't recreate on update if they exist)
            if (! $existingArticle || $article->pdfFiles->isEmpty()) {
                $this->createPdfFiles($article, $publicData['pdf_url'] ?? []);
            }

            // 7. Study Types
            $this->attachStudyTypes($article, $articleGeneralData['studyType'] ?? []);

            // 8. Study Categories
            $this->attachStudyCategories($article, $articleGeneralData);

            // 9. Highlight Info
            $this->createHighlightInfo($article, $articleGeneralData);

            // 10. Species
            $this->attachSpecies($article, $articleGeneralData['species'] ?? [], $articleGeneralData['speciesDetails'] ?? [], $researcherData);

            // 11. Organs
            $this->attachOrgans($article, $articleGeneralData['organ'] ?? []);

            // 12. Systems
            $this->attachSystems($article, $articleGeneralData['system'] ?? []);

            // 13. Diseases
            $this->attachDiseases($article, $articleGeneralData);

            // 14. Research Topics
            $this->attachResearchTopics($article, $articleGeneralData['researchtopic'] ?? []);

            // 15. Timing Treatments
            $this->attachTimingTreatments($article, $articleGeneralData);

            // 16. Outcome Types
            $this->attachOutcomeTypes($article, $articleGeneralData['outcomeType'] ?? []);

            // 17. Outcome Description
            $this->createOutcome($article, $articleGeneralData['outcome'] ?? null);

            // 18. Study Durations
            $this->createStudyDurations($article, $articleGeneralData);

            // 19. Experimental Design
            $this->createExperimentalDesign($article, $researcherData);

            // 20. Administration Methods
            $this->attachAdministrationMethods($article, $researcherData);

            // 21. Protocols (Inhalation, Ingestion, Cell Culture, Topical)
            $this->createProtocols($article, $researcherData, $articleGeneralData);

            // 22. Biomarkers
            $this->createBiomarkers($article, $biomaker ?? []);

            DB::commit();

            return $article->load([
                'publicationDetail', 
                'authors', 
                'keywords', 
                'countries',
                'studyTypes', 
                'species', 
                'organs', 
                'systems', 
                'diseases',
                'researchTopics', 
                'administrationMethods', 
                'biomarkers',
                'studyDurations',
                'experimentalDesign',
                'highlightInfo',
                'outcomes',
                'claims',
                'inhalationProtocols',
                'ingestionProtocols',
                'cellCultureProtocols',
                'topicalProtocols',
                'speciesDetails'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Transform normalized article back to JSON format (for backward compatibility)
     */
    public function transformToJson(Article $article)
    {
        $publicData = $this->buildPublicData($article);
        $articleGeneralData = $this->buildArticleGeneralData($article);
        $researcherData = $this->buildResearcherData($article);
        $biomaker = $this->buildBiomakerData($article);

        return [
            'id' => $article->id,
            'mhid' => $article->mhid,
            'doi' => $article->doi,
            'pmid' => $article->pmid,
            'status' => $article->status,
            'publicData' => $publicData,
            'articleGeneralData' => $articleGeneralData,
            'researcherData' => $researcherData,
            'biomaker' => $biomaker,
            'reviewer_id' => $article->reviewer_id,
            'verified_by' => $article->verified_by,
            'reviewer' => $article->reviewer ?? null,
            'addedBy' => $article->added_by,
            'is_trending' => $article->is_trending,
            'created_at' => $article->created_at,
            'updated_at' => $article->updated_at,
            'genericKeywords' => true,

        ];
    }

    // ==================== Private Helper Methods ====================

    private function buildSpeciesData($article)
    {
        $speciesData = [];

        // Get all species attached to this article
        $articleSpecies = $article->species;

        foreach ($articleSpecies as $species) {
            $speciesName = $species->name;

            // Get species-specific protocols with reset keys
            $inhalationProtocols = $article->inhalationProtocols->where('species_id', $species->id)->values();
            $ingestionProtocols = $article->ingestionProtocols->where('species_id', $species->id)->values();
            $topicalProtocols = $article->topicalProtocols->where('species_id', $species->id)->values();
            $cellCultureProtocols = $article->cellCultureProtocols;

            // Get species details
            $speciesDetail = $article->speciesDetails->where('species_id', $species->id)->first();

            // Get actual administration methods
            $methods = $article->administrationMethods->pluck('name')->toArray();

            // ✅ Build methodsData structure (NEW FORMAT)
            $methodsData = [];

            // ========== INHALATION PROTOCOLS ==========
            // Group inhalation protocols by delivery_method
            $inhalationByMethod = $inhalationProtocols->groupBy('delivery_method');
            foreach ($inhalationByMethod as $deliveryMethod => $protocols) {
                $methodName = $deliveryMethod ?: 'Inhalation';
                $methodsData[$methodName] = $protocols->map(function ($protocol) {
                    return [
                        'wasOxyhydrogenUsed' => [
                            'value' => $protocol->was_oxyhydrogen_used ?? '', // ✅ ADDED
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'deliveryMethod' => [
                            'value' => $protocol->delivery_method ?? '', // ✅ ADDED
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'percentPurity' => [
                            'value' => (string) ($protocol->h2_percentage ?? ''), // ✅ NO number_format - keep as is
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'flowRate' => [
                            'value' => (string) ($protocol->flow_rate_value ?? ''), // ✅ NO number_format
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                            'unit' => $protocol->flow_rate_unit ?? 'mL/min',
                        ],
                        'estimatedFiH2' => [
                            'value' => $protocol->estimated_fih2 ? number_format((float) $protocol->estimated_fih2, 2) : '0.00',
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'frequency' => [
                            'value' => (string) ($protocol->frequency ?? ''),
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'duration' => [
                            'value' => (string) ($protocol->duration_value ?? ''), // ✅ NO number_format
                            'unit' => $protocol->duration_unit ?? 'minutes',
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'inhalationDuration' => [
                            'value' => (string) ($protocol->duration_value ?? ''), // ✅ NO number_format
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                    ];
                })->values()->toArray();
            }

            // ========== INGESTION PROTOCOLS ==========
            // ✅ Group by administration_method field
            $ingestionByMethod = $ingestionProtocols->groupBy(function ($protocol) {
                // Handle null or empty administration_method
                return $protocol->administration_method ?: 'Gavage';
            });

            foreach ($ingestionByMethod as $methodName => $protocols) {
                // Check if it's a direct ingestion method (has volume/concentration)
                $firstProtocol = $protocols->first();
                $isDirect = $firstProtocol->volume_value !== null || $firstProtocol->concentration_value !== null;

                if ($isDirect) {
                    // Direct ingestion methods (Gavage, Oral Hydrogen Water, Hydrogen-rich Saline, etc.)
                    $methodsData[$methodName] = $protocols->map(function ($protocol) {
                        return [
                            'volume' => [
                                'value' => (string) ($protocol->volume_value ?? ''), // ✅ NO number_format
                                'unit' => $protocol->volume_unit ?? 'mL',
                                'status' => $protocol->verified ? 'Verified' : 'Unverified',
                            ],
                            'concentration' => [
                                'value' => (string) ($protocol->concentration_value ?? ''),
                                'unit' => $protocol->concentration_unit ?? 'mg/L',
                                'status' => $protocol->verified ? 'Verified' : 'Unverified',
                            ],
                            'absoluteDose' => [
                                'value' => (string) ($protocol->absolute_dose_value ?? ''),
                                'unit' => $protocol->absolute_dose_unit ?? 'mg/day',
                                'status' => $protocol->verified ? 'Verified' : 'Unverified',
                            ],
                            'relativeDose' => [
                                'value' => (string) ($protocol->relative_dose_value ?? ''),
                                'unit' => $protocol->relative_dose_unit ?? 'mg/kg/day',
                                'status' => $protocol->verified ? 'Verified' : 'Unverified',
                            ],
                        ];
                    })->values()->toArray();
                } else {
                    // Indirect ingestion methods (single object, not array)
                    $protocol = $protocols->first();
                    $methodsData[$methodName] = [
                        'peakBreathHydrogen' => [ // ✅ ADDED
                            'value' => (string) ($protocol->peak_breath_hydrogen_value ?? ''),
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'frequency' => [
                            'value' => (string) ($protocol->frequency ?? ''),
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                        'duration' => [
                            'value' => (string) ($protocol->duration_value ?? ''),
                            'unit' => $protocol->duration_unit ?? 'minutes',
                            'status' => $protocol->verified ? 'Verified' : 'Unverified',
                        ],
                    ];
                }
            }

            // ========== CELL CULTURE PROTOCOLS ==========
            if ($cellCultureProtocols->isNotEmpty()) {
                $cellProtocol = $cellCultureProtocols->first();

                // Determine which method name to use
                $cellMethodName = 'Cell Culture / Tissues';
                if ($cellProtocol->cell_line === 'Cell free system') {
                    $cellMethodName = 'Cell free system';
                }

                $methodsData[$cellMethodName] = [
                    'concentrationOfHydrogenForMedium' => [
                        'value' => (string) ($cellProtocol->h2_concentration_value ?? ''),
                        'status' => $cellProtocol->verified ? 'Verified' : 'Unverified',
                    ],
                    'volumeOfMedium' => [
                        'value' => '1', // TODO: Add this field to DB or extract from somewhere
                        'unit' => 'mL',
                        'status' => 'Unverified',
                    ],
                    'exposureDuration' => [
                        'value' => (string) ($cellProtocol->duration_value ?? ''), // ✅ NO number_format
                        'unit' => $cellProtocol->duration_unit ?? 'minutes',
                        'status' => $cellProtocol->verified ? 'Verified' : 'Unverified',
                    ],
                ];
            }

            // ========== TOPICAL PROTOCOLS ==========
            if ($topicalProtocols->isNotEmpty()) {
                $topicalProtocol = $topicalProtocols->first();
                $methodsData['Topical applications'] = [
                    'topicalMethod' => [
                        'value' => $topicalProtocol->application_method ?? '',
                        'status' => $topicalProtocol->verified ? 'Verified' : 'Unverified',
                    ],
                ];
            }

            // ========== BASE STRUCTURE ==========
            $speciesData[$speciesName] = [
                'isOpen' => true,
                'isInhalationOpen' => $inhalationProtocols->isNotEmpty(),
                'isCellTissueOpen' => $cellCultureProtocols->isNotEmpty(),
                'isIngestionOpen' => $ingestionProtocols->isNotEmpty(),
                'methods' => $methods,
                'weight' => [
                    'name' => $speciesDetail && $speciesDetail->weight_value ? (int) $speciesDetail->weight_value : null, // ✅ null instead of 75
                    'unit' => $speciesDetail ? ($speciesDetail->weight_unit ?? 'kg') : 'kg',
                    'status' => $speciesDetail && $speciesDetail->weight_verified ? 'Verified' : 'Unverified',
                ],
                'methodsData' => $methodsData,
            ];

            // ========== ADD COUNTS PER METHOD ==========
            foreach ($inhalationByMethod as $methodName => $protocols) {
                $key = 'numInhalationConcentrations-'.($methodName ?: 'Inhalation');
                $speciesData[$speciesName][$key] = [
                    'name' => (string) $protocols->count(),
                    'status' => 'Unverified',
                    'value' => (string) $protocols->count(),
                ];
            }

            // ✅ Add counts for each actual ingestion method
            foreach ($ingestionByMethod as $methodName => $protocols) {
                $firstProtocol = $protocols->first();
                $isDirect = $firstProtocol->volume_value !== null || $firstProtocol->concentration_value !== null;

                if ($isDirect) {
                    // Only direct methods get HowManyConcentrations
                    $speciesData[$speciesName]["HowManyConcentrations-{$methodName}"] = [
                        'name' => $protocols->count(),
                        'status' => 'Unverified',
                    ];
                }
            }
        }

        return $speciesData;
    }

    private function createPublicationDetails($article, $publicData)
    {
        if (empty($publicData)) {
            return;
        }

        // Find or create journal
        $journal = null;
        if (! empty($publicData['journal']['name'])) {
            $journal = Journal::firstOrCreate(
                ['name' => $this->arrayToString($publicData['journal']['name'] ?? '')],
                [
                    'url' => $this->arrayToString($publicData['journalURL'] ?? null),
                    'impact_factor' => $this->arrayToFloat($publicData['impactFactor'] ?? null),
                    'h_index' => $this->arrayToInt($publicData['HIndex'] ?? null),
                    'scimago_quartile' => $this->arrayToString($publicData['sciMAGO'] ?? null),
                ]
            );
        }

        // Find or create publisher
        $publisher = null;
        if (! empty($publicData['publisher']['name'])) {
            $publisher = Publisher::firstOrCreate([
                'name' => $this->arrayToString($publicData['publisher']['name'] ?? $publicData['publisher'] ?? ''),
            ]);
        }

        // Prepare title, abstract, year

        $title = $this->arrayToString($publicData['title'] ?? '');
        $abstract = $this->arrayToString($publicData['abstract'] ?? '');
        $year = $this->arrayToInt($publicData['year'] ?? null);

        // Extract and clean all publication data

        $volume = $this->arrayToString($publicData['volume'] ?? '');
        $issue = $this->arrayToString($publicData['issue'] ?? '');
        $pages = $this->arrayToString($publicData['pages'] ?? '');

        // Extract verification statuses
        $titleVerified = $this->getVerificationStatus($publicData['title'] ?? false);
        $abstractVerified = $this->getVerificationStatus($publicData['abstract'] ?? false);
        $yearVerified = $this->getVerificationStatus($publicData['year'] ?? false);

        // Ensure we have at least a title
        if (empty($title)) {
            $title = 'Untitled Article';
        }

        ArticlePublicationDetail::updateOrCreate(
            ['article_id' => $article->id],
            [

                'article_id' => $article->id,
                'title' => $title,
                'abstract' => $abstract,
                'year' => $year,
                'volume' => $volume,
                'issue' => $issue,
                'pages' => $pages,
                'journal_id' => $journal->id ?? null,
                'publisher_id' => $publisher->id ?? null,
                'title_verified' => $titleVerified,
                'abstract_verified' => $abstractVerified,
                'year_verified' => $yearVerified,
            ]);
    }

    private function attachAuthors($article, $authors)
    {
        if (empty($authors) || !is_array($authors)) {
            return;
        }

        // ✅ CHANGED: Build pivot data array for sync
        $authorPivotData = [];
        
        foreach ($authors as $index => $authorData) {
            if (is_string($authorData)) {
                $authorData = ['name' => $authorData];
            }

            $author = VerifiedAuthor::firstOrCreate(
                ['name' => $authorData['name']],
                ['institution_affiliation' => $authorData['affiliation'] ?? null]
            );

            // ✅ CHANGED: Build pivot data array
            $authorPivotData[$author->id] = [
                'author_order' => $index + 1,
                'affiliation' => $authorData['affiliation'] ?? null,
                'is_corresponding' => false,
                'verified' => ($authorData['status'] ?? 'Unverified') === 'Verified',
            ];
        }

        // ✅ CHANGED: Use sync instead of attach
        $article->authors()->sync($authorPivotData);
    }

    private function attachKeywords($article, $keywordsString)
    {
        if (empty($keywordsString)) {
            return;
        }

        $keywordsArray = array_map('trim', explode(',', $keywordsString));
        
        // ✅ CHANGED: Build pivot data array for sync
        $keywordPivotData = [];

        foreach ($keywordsArray as $keywordName) {
            if (empty($keywordName)) {
                continue;
            }

            $keyword = Keyword::firstOrCreate(['keyword' => $keywordName]);
            
            // ✅ CHANGED: Build pivot data array
            $keywordPivotData[$keyword->id] = ['verified' => false];
        }
        
        // ✅ CHANGED: Use sync instead of attach
        $article->keywords()->sync($keywordPivotData);
    }

    private function attachCountries($article, $publicData, $articleGeneralData)
    {
        // Publication countries
        if (! empty($publicData['country'])) {
            $countries = is_array($publicData['country']) ? $publicData['country'] : [$publicData['country']];
            foreach ($countries as $countryData) {
                $countryName = is_array($countryData) ? ($countryData['name'] ?? '') : $countryData;
                if (empty($countryName)) {
                    continue;
                }

                $country = Country::firstOrCreate(['name' => $countryName]);
                $article->countries()->attach($country->id, [
                    'country_type' => 'publication',
                    'verified' => is_array($countryData) ? (($countryData['status'] ?? 'Unverified') === 'Verified') : false,
                ]);
            }
        }

        // Grant countries
        if (! empty($publicData['grantCountry']['name'])) {
            $country = Country::firstOrCreate(['name' => $publicData['grantCountry']['name']]);
            $article->countries()->attach($country->id, [
                'country_type' => 'grant',
                'verified' => ($publicData['grantCountry']['status'] ?? 'Unverified') === 'Verified',
            ]);
        }

        // Research countries
        if (! empty($publicData['researchCountry'])) {
            $countries = is_array($publicData['researchCountry']) ? $publicData['researchCountry'] : [$publicData['researchCountry']];
            foreach ($countries as $countryData) {
                $countryName = is_array($countryData) ? ($countryData['name'] ?? '') : $countryData;
                if (empty($countryName)) {
                    continue;
                }

                $country = Country::firstOrCreate(['name' => $countryName]);
                $article->countries()->attach($country->id, [
                    'country_type' => 'research',
                    'verified' => is_array($countryData) ? (($countryData['status'] ?? 'Unverified') === 'Verified') : false,
                ]);
            }
        }
    }

    private function createPdfFiles($article, $pdfUrls)
    {
        if (empty($pdfUrls) || ! is_array($pdfUrls)) {
            return;
        }

        foreach ($pdfUrls as $pdfData) {
            $article->pdfFiles()->create([
                'url' => $pdfData['name'] ?? $pdfData,
                'is_paywall' => $pdfData['isPaywall'] ?? false,
                'verified' => ($pdfData['status'] ?? 'Unverified') === 'Verified',
            ]);
        }
    }

    private function attachStudyTypes($article, $studyTypes)
    {
        if (empty($studyTypes) || ! is_array($studyTypes)) {
            return;
        }

        foreach ($studyTypes as $typeData) {
            $typeName = is_array($typeData) ? ($typeData['name'] ?? '') : $typeData;
            if (empty($typeName)) {
                continue;
            }

            $studyType = StudyType::firstOrCreate(['name' => $typeName]);
            $article->studyTypes()->attach($studyType->id, [
                'verified' => is_array($typeData) ? (($typeData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);
        }
    }

    private function attachStudyCategories($article, $articleGeneralData)
    {
        // Get existing category IDs to prevent duplicates
        $existingCategoryIds = $article->studyCategories()->pluck('study_category_id')->toArray();

        // Map various study category fields
        $categoryMappings = [
            'clinicalTrialDesign' => 'clinical',
            'observationalStudy' => 'clinical',           // ✅ Add this
            'observationalStudyObj' => 'clinical',        // ✅ Keep for backward compatibility
            'inVivo' => 'in_vivo',
            'humanStudyType' => 'in_vivo',
            'NonExperimentalSelect' => 'non_experimental',
        ];

        foreach ($categoryMappings as $field => $categoryType) {
            if (empty($articleGeneralData[$field])) {
                continue;
            }

            $categories = is_array($articleGeneralData[$field]) ? $articleGeneralData[$field] : [$articleGeneralData[$field]];

            foreach ($categories as $categoryData) {
                $categoryName = is_array($categoryData) ? ($categoryData['name'] ?? $categoryData) : $categoryData;
                if (empty($categoryName)) {
                    continue;
                }

                $studyCategory = \App\Models\StudyCategory::firstOrCreate([
                    'name' => $categoryName,
                    'category_type' => $categoryType,
                ]);

                // Only attach if not already attached
                if (! in_array($studyCategory->id, $existingCategoryIds)) {
                    $article->studyCategories()->attach($studyCategory->id, [
                        'verified' => is_array($categoryData) ? (($categoryData['status'] ?? 'Unverified') === 'Verified') : false,
                    ]);

                    // Add to tracking array to prevent duplicates within same request
                    $existingCategoryIds[] = $studyCategory->id;
                }
            }
        }
    }

    private function createHighlightInfo($article, $articleGeneralData)
    {
        if (!empty($articleGeneralData['descHighArt']['name'])) {
            // ✅ CHANGED: Use updateOrCreate instead of create
            \App\Models\ArticleHighlightInfo::updateOrCreate(
                ['article_id' => $article->id],  // ✅ Find by article_id
                [
                    'description' => $articleGeneralData['descHighArt']['name'],
                    'description_verified' => ($articleGeneralData['descHighArt']['status'] ?? 'Unverified') === 'Verified',
                ]
            );
        }
    }

    private function attachSpecies($article, $speciesArray, $speciesDetails, $researcherData = [])
    {
        if (empty($speciesArray) || ! is_array($speciesArray)) {
            return;
        }

        foreach ($speciesArray as $speciesData) {
            $speciesName = is_array($speciesData) ? ($speciesData['name'] ?? '') : $speciesData;
            if (empty($speciesName)) {
                continue;
            }

            $species = Species::firstOrCreate(['name' => $speciesName]);
            $article->species()->attach($species->id, [
                'verified' => is_array($speciesData) ? (($speciesData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);

            // Get species details for this specific species
            $speciesDetail = $speciesDetails[$speciesName] ?? null;

            // Skip if no details OR if details is just {"name": "", "status": "Unverified"}
            if (empty($speciesDetail) ||
                (isset($speciesDetail['name']) && empty($speciesDetail['name']) && count($speciesDetail) <= 2)) {
                continue;
            }

            // Extract data from the nested structure
            $description = $this->arrayToString($speciesDetail['DescribeSpecies']['name'] ?? null) ?: null;
            $numberOfSubjects = $this->arrayToInt($speciesDetail['subjects']['name'] ?? null);
            $healthStatus = $this->arrayToString($speciesDetail['health']['name'] ?? null) ?: null;
            $gender = $this->arrayToString($speciesDetail['gender']['name'] ?? null) ?: null;
            $averageAge = $this->arrayToDecimal($speciesDetail['averageAge']['name'] ?? null);
            $averageWeight = $this->arrayToDecimal($speciesDetail['averageWeight']['name'] ?? null);
            $weightUnit = $this->arrayToString($speciesDetail['averageWeight']['unit'] ?? null) ?: 'kg';

            // Determine data sources based on statusConcentration
            $ageDataSource = $speciesDetail['averageAge']['statusConcentration'] ?? 'estimated';
            $genderDataSource = $speciesDetail['gender']['statusConcentration'] ?? 'estimated';
            $subjectsDataSource = $speciesDetail['subjects']['statusConcentration'] ?? 'estimated';
            $weightDataSource = $speciesDetail['averageWeight']['statusConcentration'] ?? 'estimated';
            $healthDataSource = $speciesDetail['health']['statusConcentration'] ?? 'estimated';

            // Only create if we have at least some meaningful data
            if ($description || $numberOfSubjects || $healthStatus || $gender || $averageAge || $averageWeight) {
                \App\Models\ArticleSpeciesDetail::create([
                    'article_id' => $article->id,
                    'species_id' => $species->id,
                    'description' => $description,
                    'average_age' => $averageAge,
                    'age_unit' => $averageAge ? 'years' : null,
                    'age_data_source' => $ageDataSource,
                    'gender' => $gender,
                    'gender_data_source' => $genderDataSource,
                    'number_of_subjects' => $numberOfSubjects,
                    'subjects_data_source' => $subjectsDataSource,
                    'average_weight' => $averageWeight,
                    'weight_unit' => $weightUnit,
                    'weight_data_source' => $weightDataSource,
                    'health_status' => $healthStatus,
                    'health_status_data_source' => $healthDataSource,
                    'subjects_verified' => false,
                    'health_verified' => false,
                    'gender_verified' => false,
                    'age_verified' => false,
                    'weight_verified' => false,
                ]);
            }
        }
    }

    private function attachOrgans($article, $organs)
    {
        if (empty($organs) || ! is_array($organs)) {
            return;
        }

        foreach ($organs as $organData) {
            $organName = is_array($organData) ? ($organData['name'] ?? '') : $organData;
            if (empty($organName)) {
                continue;
            }

            $organ = Organ::firstOrCreate(['name' => $organName]);
            $article->organs()->attach($organ->id, [
                'verified' => is_array($organData) ? (($organData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);
        }
    }

    private function attachSystems($article, $systems)
    {
        if (empty($systems) || ! is_array($systems)) {
            return;
        }

        foreach ($systems as $systemData) {
            $systemName = is_array($systemData) ? ($systemData['name'] ?? '') : $systemData;
            if (empty($systemName)) {
                continue;
            }

            $system = System::firstOrCreate(['name' => $systemName]);
            $article->systems()->attach($system->id, [
                'verified' => is_array($systemData) ? (($systemData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);
        }
    }

    private function attachDiseases($article, $articleGeneralData)
    {
        if (empty($articleGeneralData['disease'])) {
            return;
        }

        $diseases = is_array($articleGeneralData['disease']) ? $articleGeneralData['disease'] : [$articleGeneralData['disease']];

        foreach ($diseases as $diseaseData) {
            $diseaseName = is_array($diseaseData) ? ($diseaseData['name'] ?? '') : $diseaseData;
            if (empty($diseaseName)) {
                continue;
            }

            // Extract and normalize status
            $statusFromPayload = is_array($diseaseData) ? ($diseaseData['status'] ?? 'Active') : 'Active';

            // Map 'Pending', 'Unverified', or any non-Active status to 'Inactive'
            $normalizedStatus = (strtolower($statusFromPayload) === 'verified' || strtolower($statusFromPayload) === 'active')
                ? 'Active'
                : 'Inactive';

            // Create or find disease with normalized status
            $disease = Disease::firstOrCreate(
                ['name' => $diseaseName],
                ['status' => $normalizedStatus]  // Only set on CREATE, not on find
            );

            $article->diseases()->attach($disease->id, [
                'disease_model_description' => $articleGeneralData['diseaseModel']['name'] ?? null,
                'verified' => is_array($diseaseData) ? (($diseaseData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);
        }
    }

    private function attachResearchTopics($article, $topics)
    {
        if (empty($topics) || ! is_array($topics)) {
            return;
        }

        foreach ($topics as $topicData) {
            $topicName = is_array($topicData) ? ($topicData['name'] ?? '') : $topicData;
            if (empty($topicName)) {
                continue;
            }

            $topic = ResearchTopic::firstOrCreate(['name' => $topicName]);
            $article->researchTopics()->attach($topic->id, [
                'verified' => is_array($topicData) ? (($topicData['status'] ?? 'Unverified') === 'Verified') : false,
            ]);
        }
    }

    private function attachTimingTreatments($article, $articleGeneralData)
    {
        $timingFields = [
            'timingTreatmentInVivo' => 'in_vivo',
            'timingTreatmentInVitro' => 'in_vitro',
            'timingTreatmentExVivo' => 'ex_vivo',
        ];

        foreach ($timingFields as $field => $context) {
            if (empty($articleGeneralData[$field])) {
                continue;
            }

            $timings = is_array($articleGeneralData[$field]) ? $articleGeneralData[$field] : [$articleGeneralData[$field]];

            foreach ($timings as $timingData) {
                $timingName = is_array($timingData) ? ($timingData['name'] ?? '') : $timingData;
                if (empty($timingName)) {
                    continue;
                }

                $timing = \App\Models\TimingTreatment::firstOrCreate([
                    'name' => $timingName,
                    'context' => $context,
                ]);

                $article->timingTreatments()->attach($timing->id, [
                    'verified' => is_array($timingData) ? (($timingData['status'] ?? 'Unverified') === 'Verified') : false,
                ]);
            }
        }
    }

    private function attachOutcomeTypes($article, $outcomeTypes)
    {
        if (empty($outcomeTypes) || ! is_array($outcomeTypes)) {
            return;
        }

        foreach ($outcomeTypes as $typeName) {
            if (empty($typeName)) {
                continue;
            }

            $outcomeType = \App\Models\OutcomeType::firstOrCreate(['name' => $typeName]);
            $article->outcomeTypes()->attach($outcomeType->id, ['verified' => false]);
        }
    }

    private function createOutcome($article, $outcomeData)
    {
        if (empty($outcomeData)) {
            return;
        }

        $outcomeDescription = is_array($outcomeData) ? ($outcomeData['name'] ?? '') : $outcomeData;

        if (!empty($outcomeDescription)) {
            \App\Models\ArticleOutcome::updateOrCreate(
                ['article_id' => $article->id],  // ✅ Find by article_id
                [
                    'outcome_description' => $outcomeDescription,
                    'outcome_verified' => is_array($outcomeData) ? (($outcomeData['status'] ?? 'Unverified') === 'Verified') : false,
                ]
            );
        }
    }

    private function createStudyDurations($article, $articleGeneralData)
    {
        $durationMappings = [
            'durationOfStudy' => 'overall',
            'durationOfStudyinVitro' => 'in_vitro',
            'durationOfStudyExVivo' => 'ex_vivo',
        ];

        // Get and normalize the duration unit once
        $durationUnit = $this->normalizeDurationUnit(
            $this->arrayToString($articleGeneralData['studyDurationUnit'] ?? 'days')
        );

        foreach ($durationMappings as $field => $context) {
            if (empty($articleGeneralData[$field]['name'])) {
                continue;
            }

            // Extract duration value
            $durationValue = $this->arrayToString($articleGeneralData[$field]['name']);

            // Skip if empty or not numeric
            if (empty($durationValue) || !is_numeric($durationValue)) {
                continue;
            }

            // ✅ CHANGED: Use updateOrCreate instead of create
            \App\Models\ArticleStudyDuration::updateOrCreate(
                [
                    'article_id' => $article->id,  // ✅ Find by article_id AND context
                    'context' => $context,
                ],
                [
                    'duration_value' => (int) $durationValue,
                    'duration_unit' => $durationUnit,
                    'verified' => ($articleGeneralData[$field]['status'] ?? 'Unverified') === 'Verified',
                ]
            );
        }
    }

    private function normalizeDurationUnit($unit)
    {
        if (empty($unit)) {
            return 'days'; // default
        }

        $unit = strtolower(trim($unit));

        // Map singular to plural
        $unitMap = [
            'second' => 'seconds',
            'minute' => 'minutes',
            'hour' => 'hours',
            'day' => 'days',
            'week' => 'weeks',
            'month' => 'months',
            'year' => 'years',
        ];

        // If already plural, return as is
        if (in_array($unit, array_values($unitMap))) {
            return $unit;
        }

        // If singular, convert to plural
        if (isset($unitMap[$unit])) {
            return $unitMap[$unit];
        }

        // Default to 'days'
        return 'days';
    }

    private function createExperimentalDesign($article, $researcherData)
    {
        if (empty($researcherData)) {
            return;
        }

        // Find or create brand
        $brandId = null;
        if (!empty($researcherData['brandName']['name'])) {
            $brand = \App\Models\Brand::firstOrCreate([
                'name' => $this->arrayToString($researcherData['brandName']['name']),
            ]);
            $brandId = $brand->id;
        }

        // ✅ CHANGED: Use updateOrCreate instead of create
        \App\Models\ArticleExperimentalDesign::updateOrCreate(
            ['article_id' => $article->id],  // ✅ Find by article_id
            [
                'brand_id' => $brandId,
                'is_commercial_product' => ($researcherData['commercialProduct']['name'] ?? 'No') === 'Yes',
                'has_pharmacokinetics' => $this->parseBool($researcherData['pharmacokinetics']['name'] ?? false),
                'pharmacokinetics_description' => $this->arrayToString($researcherData['pharmacokineticsDescription'] ?? null) ?: null,
                'has_dose_comparison' => $this->parseBool($researcherData['doseComparison']['name'] ?? false),
                'dose_comparison_description' => $this->arrayToString($researcherData['doseComparisonDesc'] ?? null) ?: null,
                'has_dose_dependent_effect' => $this->parseBool($researcherData['doseDependentEffect']['name'] ?? false),
                'has_drug_comparison' => $this->parseBool($researcherData['drugComparison']['name'] ?? false),
                'drug_comparison_description' => $this->arrayToString($researcherData['comparisonDetail'] ?? null) ?: null,
                'has_method_admin_comparison' => $this->parseBool($researcherData['CompMethodAdmin']['name'] ?? false),
                'method_admin_comparison_description' => $this->arrayToString($researcherData['CompMethodAdminDesc'] ?? null) ?: null,
                'is_erw' => $this->parseBool($researcherData['isERW']['name'] ?? false),
                'erw_comparison_description' => $this->arrayToString($researcherData['erwCompared'] ?? null) ?: null,
                'ph_value' => $this->arrayToDecimal($researcherData['ph'] ?? null),
                'uses_oxyhydrogen' => $this->parseBool($researcherData['wasOxyhydrogenUsed']['name']['name'] ?? false),
                'has_safety_focus' => $this->parseBool($researcherData['safetyProfile']['name'] ?? false),
                'safety_profile_description' => $this->arrayToString($researcherData['safety_profile_description'] ?? null) ?: null,
                'has_adverse_effects' => $this->parseBool($researcherData['adverseEffects']['name'] ?? false),
                'adverse_effects_description' => $this->arrayToString($researcherData['adverseEffectsDescription']['name'] ?? null) ?: null,
                'includes_pregnant_breastfeeding' => $this->parseBool($researcherData['pregnantBreastfeeding']['name'] ?? false),
                'has_responder_difference' => $this->parseBool($researcherData['responderDifference']['name'] ?? false),
                'has_sex_difference' => $this->parseBool($researcherData['sexDifference']['name'] ?? false),
                'has_gene_expression_data' => $this->parseBool($researcherData['geneExpression']['name'] ?? false),
                'gene_expression_description' => $this->arrayToString($researcherData['geneExpressionDesc']['name'] ?? null) ?: null,
                'has_mechanistic_insights' => $this->parseBool($researcherData['mechanisticInsights']['name'] ?? false),
                'study_url' => $this->arrayToString($researcherData['PasteUrl']['name'] ?? null) ?: null,
                'video_webpage_url' => $this->arrayToString($researcherData['Video_WebpageLink']['name'] ?? null) ?: null,
                'verified' => false,
            ]
        );
    }

    private function attachAdministrationMethods($article, $researcherData)
    {
        $methods = [];

        // 1. Check root level methodOfAdmin
        if (! empty($researcherData['methodOfAdmin'])) {
            $methods = is_array($researcherData['methodOfAdmin'])
                ? $researcherData['methodOfAdmin']
                : [$researcherData['methodOfAdmin']];
        }

        // 2. Check speciesData.General.methods (YOUR CASE)
        if (empty($methods) && ! empty($researcherData['speciesData']['General']['methods'])) {
            $methods = $researcherData['speciesData']['General']['methods'];
        }

        // 3. If still empty, check all species for methods
        if (empty($methods) && ! empty($researcherData['speciesData'])) {
            foreach ($researcherData['speciesData'] as $speciesName => $speciesData) {
                if (is_array($speciesData) && ! empty($speciesData['methods'])) {
                    $methods = array_merge($methods, $speciesData['methods']);
                }
            }
        }

        if (empty($methods)) {
            return;
        }

        // ✅ FIXED: Manual deduplication that works with both strings and arrays
        $processedMethodNames = [];

        foreach ($methods as $methodData) {
            // Extract method name
            $methodName = is_array($methodData) ? ($methodData['name'] ?? '') : $methodData;

            if (empty($methodName) || in_array($methodName, $processedMethodNames)) {
                continue; // Skip empty or already processed methods
            }

            // Track this method name
            $processedMethodNames[] = $methodName;

            $method = AdministrationMethod::firstOrCreate(['name' => $methodName]);

            // Prevent duplicate attachments
            if (! $article->administrationMethods()->where('administration_method_id', $method->id)->exists()) {
                $article->administrationMethods()->attach($method->id, [
                    'verified' => is_array($methodData) ? (($methodData['status'] ?? 'Unverified') === 'Verified') : false,
                ]);
            }
        }
    }

    private function createProtocols($article, $researcherData, $articleGeneralData = [])
    {
        // Extract cell culture data from multiple possible locations (OLD FORMAT FALLBACK)
        $generalData = $researcherData['speciesData']['General'] ?? [];

        $whatKindCell = $articleGeneralData['WhatKindCell']['name'] ?? null;
        $whatCellTissueUsed = $articleGeneralData['WhatCellTissueUsed']['name'] ?? null;

        // Try root level first, then General section (OLD FORMAT)
        $concentrationOfHydrogen = $researcherData['concentrationOfHydrogenForMedium']['name']
            ?? $generalData['concentrationOfHydrogenForMedium']['name']
            ?? $generalData['concentrationOfHydrogenForMedium']['value']
            ?? null;

        $durationFrequency = $researcherData['DurationFrequencyCellCultureTissues']['name']
            ?? $generalData['DurationFrequencyCellCultureTissues']['value']
            ?? null;

        $frequency = $researcherData['FrequencyCellCultureTissues']['name']
            ?? $researcherData['FrequencyCellCultureTissues']
            ?? $generalData['FrequencyCellCultureTissues']['value']
            ?? null;

        // Process ALL species-specific protocols
        if (! empty($researcherData['speciesData'])) {
            foreach ($researcherData['speciesData'] as $speciesName => $speciesData) {

                $species = Species::where('name', $speciesName)->first();
                if (! $species) {
                    \Log::warning("Species not found in database: {$speciesName}");

                    continue;
                }

                // ✅ NEW: Check if using NEW methodsData structure
                $methodsData = $speciesData['methodsData'] ?? null;

                if ($methodsData && ! empty($methodsData)) {
                    // ========== NEW STRUCTURE: methodsData exists ==========
                    \Log::info("Processing NEW format for species: {$speciesName}");

                    // ✅ Process ALL INHALATION-BASED methods
                    $allInhalationMethods = [
                        'Inhalation',
                        'Inhalation of Concentrations',
                        'Hyperbaric H2',
                        'Extracorporeal circulation system',
                    ];

                    foreach ($allInhalationMethods as $methodName) {
                        if (! empty($methodsData[$methodName]) && is_array($methodsData[$methodName])) {
                            foreach ($methodsData[$methodName] as $protocol) {
                                \App\Models\ArticleInhalationProtocol::create([
                                    'article_id' => $article->id,
                                    'species_id' => $species->id,
                                    'h2_percentage' => $protocol['percentPurity']['value'] ?? null,
                                    'o2_percentage' => null,
                                    'estimated_fih2' => $protocol['estimatedFiH2']['value'] ?? null,
                                    'flow_rate_value' => $protocol['flowRate']['value'] ?? null,
                                    'flow_rate_unit' => $protocol['flowRate']['unit'] ?? 'mL/min',
                                    'duration_value' => $protocol['duration']['value'] ?? $protocol['inhalationDuration']['value'] ?? null,
                                    'duration_unit' => $protocol['duration']['unit'] ?? 'minutes',
                                    'frequency' => $protocol['frequency']['value'] ?? null,
                                    'delivery_method' => $methodName, // ✅ SAVE deliveryMethod
                                    'delivery_device' => $protocol['deliveryMethod']['value'] ?? null, // The actual device (mask, nasal cannula, etc.)
                                    'was_oxyhydrogen_used' => $protocol['wasOxyhydrogenUsed']['value'] ?? null, // ✅ SAVE wasOxyhydrogenUsed
                                    'peak_breath_hydrogen_value' => null,
                                    'verified' => ($protocol['percentPurity']['status'] ?? 'Unverified') === 'Verified',
                                ]);
                                \Log::info("Created inhalation protocol for {$speciesName} - {$methodName}");
                            }
                        }
                    }

                    // ✅ Process ALL DIRECT INGESTION methods (with volume/concentration/doses)
                    $directIngestionMethods = [
                        'Gavage',
                        'Oral Hydrogen Water',
                        'Hydrogen-rich Saline',
                        'Intraperitoneal injection of Hydrogen-Rich Solution',
                        'Subcutaneous injection of hydrogen',
                        'Intraperitoneal Infusion (Dialysis)',
                    ];

                    foreach ($directIngestionMethods as $methodName) {
                        if (! empty($methodsData[$methodName]) && is_array($methodsData[$methodName])) {
                            foreach ($methodsData[$methodName] as $protocol) {
                                \App\Models\ArticleIngestionProtocol::create([
                                    'article_id' => $article->id,
                                    'species_id' => $species->id,
                                    'administration_method' => $methodName, // ✅ SAVE METHOD NAME
                                    'volume_value' => $protocol['volume']['value'] ?? null,
                                    'volume_unit' => $protocol['volume']['unit'] ?? 'mL',
                                    'concentration_value' => $protocol['concentration']['value'] ?? null,
                                    'concentration_unit' => $protocol['concentration']['unit'] ?? 'mg/L',
                                    'absolute_dose_value' => $protocol['absoluteDose']['value'] ?? null,
                                    'absolute_dose_unit' => $protocol['absoluteDose']['unit'] ?? 'mg/day',
                                    'relative_dose_value' => $protocol['relativeDose']['value'] ?? null,
                                    'relative_dose_unit' => $protocol['relativeDose']['unit'] ?? 'mg/kg/day',
                                    'duration_value' => null,
                                    'duration_unit' => 'days',
                                    'frequency' => null,
                                    'peak_breath_hydrogen_value' => null, // Not applicable for direct methods
                                    'verified' => ($protocol['volume']['status'] ?? 'Unverified') === 'Verified',
                                ]);
                                \Log::info("Created direct ingestion protocol for {$speciesName} - {$methodName}");
                            }
                        }
                    }

                    // ✅ Process ALL INDIRECT INGESTION methods (bacteria, dietary fibres, etc.)
                    $indirectIngestionMethods = [
                        'Ingestion of (Si)-based hydrogen-producing antioxidant',
                        'Ingestion of Dietary fibres',
                        'Ingestion of H2 producing Bacteria',
                        'Ingestion of calcium and magnesium',
                        'Hydrogen Donors',
                        'Nanoparticles (PdH)',
                    ];

                    foreach ($indirectIngestionMethods as $methodName) {
                        if (! empty($methodsData[$methodName])) {
                            $protocol = $methodsData[$methodName];

                            // Only create if it's not an array (single object)
                            if (! is_array($protocol) || ! isset($protocol[0])) {
                                \App\Models\ArticleIngestionProtocol::create([
                                    'article_id' => $article->id,
                                    'species_id' => $species->id,
                                    'administration_method' => $methodName, // ✅ SAVE METHOD NAME
                                    'volume_value' => null,
                                    'volume_unit' => null,
                                    'concentration_value' => null,
                                    'concentration_unit' => null,
                                    'absolute_dose_value' => null,
                                    'absolute_dose_unit' => null,
                                    'relative_dose_value' => null,
                                    'relative_dose_unit' => null,
                                    'duration_value' => $protocol['duration']['value'] ?? null,
                                    'duration_unit' => $protocol['duration']['unit'] ?? 'minutes',
                                    'frequency' => $protocol['frequency']['value'] ?? null,
                                    'peak_breath_hydrogen_value' => $protocol['peakBreathHydrogen']['value'] ?? null, // ✅ SAVE peakBreathHydrogen
                                    'peak_breath_hydrogen_unit' => 'ppm',
                                    'verified' => ($protocol['duration']['status'] ?? 'Unverified') === 'Verified',
                                ]);
                                \Log::info("Created indirect ingestion protocol for {$speciesName} - {$methodName}");
                            }
                        }
                    }

                    // ✅ Process CELL CULTURE / TISSUES
                    if (! empty($methodsData['Cell Culture / Tissues'])) {
                        $cellData = $methodsData['Cell Culture / Tissues'];

                        \App\Models\ArticleCellCultureProtocol::create([
                            'article_id' => $article->id,
                            'cell_line' => $whatKindCell,
                            'cell_tissue_type' => $whatCellTissueUsed,
                            'h2_concentration_value' => $this->toDecimalOrNull($cellData['concentrationOfHydrogenForMedium']['value'] ?? null),
                            'h2_concentration_unit' => 'μM/L',
                            'duration_value' => $cellData['exposureDuration']['value'] ?? null,
                            'duration_unit' => $cellData['exposureDuration']['unit'] ?? 'minutes',
                            'frequency' => null,
                            'verified' => ($cellData['concentrationOfHydrogenForMedium']['status'] ?? 'Unverified') === 'Verified',
                        ]);
                        \Log::info("Created cell culture protocol for {$speciesName}");
                    }

                    // ✅ Process CELL FREE SYSTEM
                    if (! empty($methodsData['Cell free system'])) {
                        $cellFreeData = $methodsData['Cell free system'];

                        \App\Models\ArticleCellCultureProtocol::create([
                            'article_id' => $article->id,
                            'cell_line' => 'Cell free system',
                            'cell_tissue_type' => $whatCellTissueUsed,
                            'h2_concentration_value' => $this->toDecimalOrNull($cellFreeData['concentrationOfHydrogenForMedium']['value'] ?? null),
                            'h2_concentration_unit' => 'μM/L',
                            'duration_value' => $cellFreeData['exposureDuration']['value'] ?? null,
                            'duration_unit' => $cellFreeData['exposureDuration']['unit'] ?? 'minutes',
                            'frequency' => null,
                            'verified' => ($cellFreeData['concentrationOfHydrogenForMedium']['status'] ?? 'Unverified') === 'Verified',
                        ]);
                        \Log::info("Created cell free system protocol for {$speciesName}");
                    }

                    // ✅ Process TOPICAL APPLICATIONS
                    if (! empty($methodsData['Topical applications'])) {
                        $topicalData = $methodsData['Topical applications'];

                        \App\Models\ArticleTopicalProtocol::create([
                            'article_id' => $article->id,
                            'species_id' => $species->id,
                            'application_method' => $topicalData['topicalMethod']['value'] ?? null,
                            'concentration_value' => null,
                            'concentration_unit' => null,
                            'volume_applied_value' => null,
                            'volume_applied_unit' => null,
                            'application_area' => null,
                            'duration_value' => null,
                            'duration_unit' => null,
                            'frequency' => null,
                            'verified' => ($topicalData['topicalMethod']['status'] ?? 'Unverified') === 'Verified',
                        ]);
                        \Log::info("Created topical protocol for {$speciesName}");
                    }

                } else {
                    // ========== OLD STRUCTURE: Use existing logic ==========
                    \Log::info("Processing OLD format for species: {$speciesName}");

                    // Check if this species has cell culture data
                    $hasSpeciesCellCulture = ! empty($speciesData['concentrationOfHydrogenForMedium']) ||
                                            ! empty($speciesData['DurationFrequencyCellCultureTissues']) ||
                                            ! empty($speciesData['FrequencyCellCultureTissues']);

                    // Inhalation protocols (OLD FORMAT)
                    if (! empty($speciesData['inhalationConcentrations'])) {
                        foreach ($speciesData['inhalationConcentrations'] as $protocol) {
                            \App\Models\ArticleInhalationProtocol::create([
                                'article_id' => $article->id,
                                'species_id' => $species->id,
                                'h2_percentage' => $protocol['percentPurity']['name'] ?? $protocol['percent_purity'] ?? null,
                                'o2_percentage' => null,
                                'estimated_fih2' => $protocol['estimatedFiH2']['name'] ?? null,
                                'flow_rate_value' => $protocol['flowRate']['name'] ?? $protocol['flow_rate']['value'] ?? null,
                                'flow_rate_unit' => $this->arrayToString($protocol['flow_rate']['unit'] ?? 'mL/min'),
                                'duration_value' => $protocol['duration']['name'] ?? $protocol['duration_per_frequency'] ?? null,
                                'duration_unit' => $this->arrayToString($protocol['unitDuration']['name'] ?? $protocol['unitDuration'] ?? 'hours'),
                                'frequency' => $this->arrayToString($protocol['frequency']['name'] ?? $protocol['frequency'] ?? null),
                                'delivery_method' => $this->arrayToString(
                                    $speciesData['deliveryMethod']['name'] ??
                                    $speciesData['deliveryMethod']['value'] ??
                                    $speciesData['deliveryMethod'] ??
                                    null
                                ),
                                'was_oxyhydrogen_used' => $speciesData['wasOxyhydrogenUsed']['name'] ?? null,
                                'peak_breath_hydrogen_value' => $this->toDecimalOrNull(
                                    $speciesData['Peakbreathhydrogen']['name'] ??
                                    $speciesData['Peakbreathhydrogen']['value'] ??
                                    null
                                ),
                                'peak_breath_hydrogen_unit' => $speciesData['Peakbreathhydrogen']['unit'] ?? 'ppm',
                                'verified' => false,
                            ]);
                        }
                    }

                    // Ingestion protocols (OLD FORMAT)
                    if (! empty($speciesData['volumes']) || ! empty($speciesData['concentrations'])) {
                        \App\Models\ArticleIngestionProtocol::create([
                            'article_id' => $article->id,
                            'species_id' => $species->id,
                            'administration_method' => 'Gavage', // ✅ DEFAULT for OLD format
                            'volume_value' => $speciesData['volumes'][0]['value'] ?? null,
                            'volume_unit' => $this->arrayToString($speciesData['volumes'][0]['unit'] ?? 'mL'),
                            'concentration_value' => $speciesData['concentrations'][0]['value'] ?? null,
                            'concentration_unit' => $this->arrayToString($speciesData['concentrations'][0]['unit'] ?? 'mM'),
                            'absolute_dose_value' => $speciesData['absoluteDoses'][0]['value'] ?? null,
                            'absolute_dose_unit' => $this->arrayToString($speciesData['absoluteDoses'][0]['unit'] ?? 'mg'),
                            'relative_dose_value' => $speciesData['relativeDoses'][0]['value'] ?? null,
                            'relative_dose_unit' => $this->arrayToString($speciesData['relativeDoses'][0]['unit'] ?? 'mg/kg'),
                            'duration_value' => $speciesData['IngestionDurationfrequency']['value'] ??
                                            $speciesData['IngestionDurationfrequency']['name'] ??
                                            null,
                            'duration_unit' => $speciesData['IngestionDurationfrequency']['unit'] ?? 'days',
                            'frequency' => $this->arrayToString(
                                $speciesData['Frequency']['name'] ??
                                $speciesData['Frequency']['value'] ??
                                $speciesData['Frequency'] ??
                                null
                            ),
                            'peak_breath_hydrogen_value' => null,
                            'verified' => false,
                        ]);
                    }

                    // Topical protocols (OLD FORMAT)
                    if (! empty($speciesData['topicalMethod'])) {
                        \App\Models\ArticleTopicalProtocol::create([
                            'article_id' => $article->id,
                            'species_id' => $species->id,
                            'application_method' => $this->arrayToString(
                                $speciesData['topicalMethod']['name'] ??
                                $speciesData['topicalMethod']['value'] ??
                                null
                            ),
                            'concentration_value' => null,
                            'concentration_unit' => null,
                            'volume_applied_value' => null,
                            'volume_applied_unit' => null,
                            'application_area' => null,
                            'duration_value' => null,
                            'duration_unit' => null,
                            'frequency' => null,
                            'verified' => false,
                        ]);
                    }

                    // Cell culture protocols (OLD FORMAT)
                    if ($hasSpeciesCellCulture) {
                        $speciesCellH2Concentration = $speciesData['concentrationOfHydrogenForMedium']['name']
                            ?? $speciesData['concentrationOfHydrogenForMedium']['value']
                            ?? null;
                        $speciesCellDuration = $speciesData['DurationFrequencyCellCultureTissues']['value']
                            ?? $speciesData['DurationFrequencyCellCultureTissues']['name']
                            ?? null;
                        $speciesCellFrequency = $speciesData['FrequencyCellCultureTissues']['value']
                            ?? $speciesData['FrequencyCellCultureTissues']['name']
                            ?? null;

                        \App\Models\ArticleCellCultureProtocol::create([
                            'article_id' => $article->id,
                            'cell_line' => $this->arrayToString($whatKindCell),
                            'cell_tissue_type' => $this->arrayToString($whatCellTissueUsed),
                            'h2_concentration_value' => $this->toDecimalOrNull($speciesCellH2Concentration),
                            'h2_concentration_unit' => 'mM',
                            'duration_value' => is_numeric($speciesCellDuration) ? (int) $speciesCellDuration : null,
                            'duration_unit' => $speciesData['DurationFrequencyCellCultureTissues']['unit'] ?? 'minutes',
                            'frequency' => is_numeric($speciesCellFrequency) ? (int) $speciesCellFrequency : $this->arrayToString($speciesCellFrequency),
                            'verified' => false,
                        ]);
                    }
                }
            }
        }

        // Fallback: Create generic cell culture protocol (OLD FORMAT)
        if (! empty($whatKindCell) || ! empty($whatCellTissueUsed) || ! empty($concentrationOfHydrogen)) {
            $existingCellCulture = \App\Models\ArticleCellCultureProtocol::where('article_id', $article->id)->exists();

            if (! $existingCellCulture) {
                \App\Models\ArticleCellCultureProtocol::create([
                    'article_id' => $article->id,
                    'cell_line' => $this->arrayToString($whatKindCell),
                    'cell_tissue_type' => $this->arrayToString($whatCellTissueUsed),
                    'h2_concentration_value' => $this->toDecimalOrNull($concentrationOfHydrogen),
                    'h2_concentration_unit' => 'mM',
                    'duration_value' => is_numeric($durationFrequency) ? (int) $durationFrequency : null,
                    'duration_unit' => 'minutes',
                    'frequency' => is_numeric($frequency) ? (int) $frequency : $this->arrayToString($frequency),
                    'verified' => false,
                ]);
            }
        }
    }

    private function createBiomarkers($article, $biomarkers)
    {
        if (empty($biomarkers) || ! is_array($biomarkers)) {
            return;
        }

        foreach ($biomarkers as $biomarkerData) {
            $markerName = $biomarkerData['marker'] ?? '';
            if (empty($markerName)) {
                continue;
            }

            // Find or create biomarker
            $biomarker = BioSub::firstOrCreate(['name' => $markerName]);

            // Find change direction
            $changeDirectionId = null;
            if (! empty($biomarkerData['Change'])) {
                $changes = is_array($biomarkerData['Change']) ? $biomarkerData['Change'] : [$biomarkerData['Change']];
                $changeName = $changes[0] ?? '';
                if (! empty($changeName)) {
                    $changeDirection = ChangeDirection::where('name', $changeName)->first();
                    $changeDirectionId = $changeDirection->id ?? null;
                }
            }

            // Create article biomarker
            $articleBiomarker = \App\Models\ArticleBiomarker::create([
                'article_id' => $article->id,
                'biomarker_id' => $biomarker->id,
                'is_measured' => $biomarkerData['is_measured'] ?? true,
                'change_direction_id' => $changeDirectionId,
                'protein_name' => $biomarkerData['Protein'] ?? $biomarkerData['protein'] ?? null,
                'verified' => ($biomarkerData['status'] ?? 'Unverified') === 'Verified',
            ]);

            // Attach categories
            if (! empty($biomarkerData['category'])) {
                $categories = is_array($biomarkerData['category']) ? $biomarkerData['category'] : [$biomarkerData['category']];
                foreach ($categories as $categoryName) {
                    if (empty($categoryName)) {
                        continue;
                    }
                    $category = BioCategory::where('name', $categoryName)->first();
                    if ($category) {
                        $articleBiomarker->categories()->attach($category->id);
                    }
                }
            }
        }
    }

    private function parseBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', 'yes', '1']);
        }

        return (bool) $value;
    }

    // ==================== Build JSON Methods (for backward compatibility) ====================

    private function buildPublicData(Article $article)
    {
        $publication = $article->publicationDetail;
        $authors = $article->authors->map(function ($author) {
            return [
                'name' => $author->name,
                'affiliation' => $author->pivot->affiliation,
                'status' => $author->pivot->verified ? 'Verified' : 'Unverified',
            ];
        })->toArray();

        $keywords = $article->keywords->pluck('keyword')->implode(', ');

        $publicationCountries = $article->publicationCountries()->get()->map(function ($country) {
            return ['name' => $country->name, 'status' => $country->pivot->verified ? 'Verified' : 'Unverified'];
        })->toArray();

        $pdfUrls = $article->pdfFiles->map(function ($pdf) {
            return [
                'name' => $pdf->url,
                'isPaywall' => $pdf->is_paywall,
                'status' => $pdf->verified ? 'Verified' : 'Unverified',
            ];
        })->toArray();

        return [
            'title' => ['name' => $publication->title ?? '', 'status' => $publication->title_verified ? 'Verified' : 'Unverified'],
            'pmid' => ['name' => $article->pmid ?? ''],
            'doi' => ['name' => $article->doi ?? ''],
            'issue' => ['name' => $publication->issue ?? '', 'status' => $publication->issue_verified ? 'Verified' : 'Unverified'],
            'abstract' => ['name' => $publication->abstract ?? '', 'status' => $publication->abstract_verified ? 'Verified' : 'Unverified'],
            'year' => ['name' => $publication->year ?? '', 'status' => $publication->year_verified ? 'Verified' : 'Unverified'],
            'authors' => $authors,
            'journal' => ['name' => $publication->journal->name ?? '', 'status' => 'Verified'],
            'journalURL' => $publication->journal->url ?? '',
            'publisher' => ['name' => $publication->publisher->name ?? '', 'status' => 'Verified'],
            'volume' => ['name' => $publication->volume ?? '', 'status' => 'Verified'],
            'pages' => ['name' => $publication->pages ?? '', 'status' => 'Verified'],
            'impactFactor' => ['name' => $publication->journal->impact_factor ?? '', 'status' => 'Verified'],
            'HIndex' => ['name' => $publication->journal->h_index ?? '', 'status' => 'Verified'],
            'sciMAGO' => ['name' => $publication->journal->scimago_quartile ?? '', 'status' => 'Verified'],
            'keywords' => ['name' => $keywords, 'status' => 'Unverified'],
            'country' => $publicationCountries,
            'grantCountry' => $article->grantCountries()->first() ? ['name' => $article->grantCountries()->first()->name, 'status' => 'Verified'] : null,
            'researchCountry' => $article->researchCountries()->get()->map(function ($c) {
                return ['name' => $c->name, 'status' => 'Verified'];
            })->toArray(),
            'pdf_url' => $pdfUrls,
        ];
    }

    private function buildArticleGeneralData(Article $article)
    {
        return [
            'HighlightArticle' => ['name' => $article->is_highlighted ? 'True' : 'False', 'status' => 'Verified'],
            'descHighArt' => ['name' => $article->highlightInfo->description ?? '', 'status' => 'Unverified'],
            'rankThisArticle' => ['name' => $article->rank_score, 'status' => 'Verified'],
            'studyType' => $article->studyTypes->map(function ($st) {
                return ['name' => $st->name, 'status' => $st->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'species' => $article->species->map(function ($s) {
                return ['name' => $s->name, 'status' => $s->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'organ' => $article->organs->map(function ($o) {
                return ['name' => $o->name, 'status' => $o->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'system' => $article->systems->map(function ($s) {
                return ['name' => $s->name, 'status' => $s->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'disease' => $article->diseases->map(function ($d) {
                return ['name' => $d->name, 'status' => $d->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'researchtopic' => $article->researchTopics->map(function ($rt) {
                return ['name' => $rt->name, 'status' => $rt->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'outcome' => ['name' => $article->outcome->outcome_description ?? '', 'status' => 'Unverified'],
            // 'outcomeType' => $article->outcomeTypes->pluck('name')->toArray(),
            'outcomeType' => $article->outcomeTypes->pluck('name')->map(function ($name) {
                return ucfirst($name);
            })->toArray(),

            // 'clinicalTrialDesign' => $article->studyCategories()->where('category_type', 'clinical')->get()->map(function ($sc) {
            //     return $sc->name;
            // })->toArray(),

            // 'observationalStudyObj' => $article->studyCategories()->where('category_type', 'clinical')->whereIn('name', ['Cohort', 'Case-Control', 'Cross-Sectional', 'Case Series'])->get()->map(function ($sc) {
            //     return ['name' => $sc->name, 'status' => $sc->pivot->verified ? 'Verified' : 'Unverified'];
            // })->toArray(),

            // Clinical Trial Design categories (exclude observational)
            'clinicalTrialDesign' => $article->studyCategories()
                ->where('category_type', 'clinical')
                ->whereNotIn('name', [
                    'Cohort', 'Case-Control', 'Cross-Sectional', 'Case Series',
                    'Longitudinal', 'Case Report', 'Survey', 'Prospective', 'Retrospective',
                ])
                ->get()
                ->pluck('name')
                ->toArray(),

            // Observational Study categories (simple array)
            'observationalStudy' => $article->studyCategories()
                ->where('category_type', 'clinical')
                ->whereIn('name', [
                    'Cohort', 'Case-Control', 'Cross-Sectional', 'Case Series',
                    'Longitudinal', 'Case Report', 'Survey', 'Prospective', 'Retrospective',
                ])
                ->get()
                ->pluck('name')
                ->toArray(),

            // Observational Study with status (for backward compatibility)
            'observationalStudyObj' => $article->studyCategories()
                ->where('category_type', 'clinical')
                ->whereIn('name', [
                    'Cohort', 'Case-Control', 'Cross-Sectional', 'Case Series',
                    'Longitudinal', 'Case Report', 'Survey', 'Prospective', 'Retrospective',
                ])
                ->get()
                ->map(function ($sc) {
                    return ['name' => $sc->name, 'status' => $sc->pivot->verified ? 'Verified' : 'Unverified'];
                })
                ->toArray(),

            'inVivo' => $article->studyCategories()->where('category_type', 'in_vivo')->get()->map(function ($sc) {
                return ['name' => $sc->name, 'status' => $sc->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),

            'NonExperimentalSelect' => $article->studyCategories()->where('category_type', 'non_experimental')->get()->map(function ($sc) {
                return ['name' => $sc->name, 'status' => $sc->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),

            // Individual flags from study categories
            'Hypothesis' => $article->studyCategories()->where('name', 'Hypothesis')->exists() ? ['name' => 'Hypothesis', 'status' => 'Verified'] : null,

            'OpinionPiece' => $article->studyCategories()->where('name', 'Opinion Piece')->exists() ? ['name' => 'Opinion Piece', 'status' => 'Verified'] : null,

            'ReviewStudyType' => $article->studyCategories()->where('name', 'LIKE', '%Review%')->first() ? ['name' => $article->studyCategories()->where('name', 'LIKE', '%Review%')->first()->name, 'status' => 'Verified'] : null,

            // Study Durations
            'durationOfStudy' => $article->studyDurations()->where('context', 'overall')->first() ? ['name' => $article->studyDurations()->where('context', 'overall')->first()->duration_value, 'status' => $article->studyDurations()->where('context', 'overall')->first()->verified ? 'Verified' : 'Unverified'] : null,

            'durationOfStudyinVitro' => $article->studyDurations()->where('context', 'in_vitro')->first() ? ['name' => $article->studyDurations()->where('context', 'in_vitro')->first()->duration_value, 'status' => $article->studyDurations()->where('context', 'in_vitro')->first()->verified ? 'Verified' : 'Unverified'] : null,

            'durationOfStudyExVivo' => $article->studyDurations()->where('context', 'ex_vivo')->first() ? ['name' => $article->studyDurations()->where('context', 'ex_vivo')->first()->duration_value, 'status' => $article->studyDurations()->where('context', 'ex_vivo')->first()->verified ? 'Verified' : 'Unverified'] : null,

            'studyDurationUnit' => $article->studyDurations()->first() ? ['name' => $article->studyDurations()->first()->duration_unit, 'status' => 'Verified'] : null,

            // Timing Treatments by context
            'timingTreatmentInVivo' => $article->timingTreatments()->where('context', 'in_vivo')->get()->map(function ($tt) {
                return ['name' => $tt->name, 'status' => $tt->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),

            'timingTreatmentInVitro' => $article->timingTreatments()->where('context', 'in_vitro')->get()->map(function ($tt) {
                return ['name' => $tt->name, 'status' => $tt->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),

            'timingTreatmentExVivo' => $article->timingTreatments()->where('context', 'ex_vivo')->get()->map(function ($tt) {
                return ['name' => $tt->name, 'status' => $tt->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),

            // Cell Culture Info
            // 'WhatKindCell' => $article->cellCultureProtocols()->first() ? ['name' => $article->cellCultureProtocols()->first()->cell_line, 'status' => $article->cellCultureProtocols()->first()->verified ? 'Verified' : 'Unverified'] : null,
            // 'WhatKindCell' => $this->getCellCultureField($article, 'cell_line'),

            'WhatKindCell' => $article->cellCultureProtocols->first()
    ? ['name' => $article->cellCultureProtocols->first()->cell_line, 'status' => $article->cellCultureProtocols->first()->verified ? 'Verified' : 'Unverified']
: null,

            'WhatCellTissueUsed' => $this->getCellCultureField($article, 'cell_tissue_type'),

            // 'WhatCellTissueUsed' => $article->cellCultureProtocols()->first() ? ['name' => $article->cellCultureProtocols()->first()->cell_tissue_type, 'status' => 'Unverified'] : null,

            // 'UnitOfStudyInVitro' => $article->cellCultureProtocols()->first() ? ['name' => $article->cellCultureProtocols()->first()->cell_line, 'status' => 'Unverified'] : null,

            'UnitOfStudyInVitro' => $article->cellCultureProtocols()->first() ? ['name' => $article->cellCultureProtocols()->first()->cell_line, 'status' => 'Unverified'] : null,

            'UnitOfStudyExVivo' => null, // Can be derived from topical protocols if needed

            // Species Details
            'speciesDetails' => $this->buildOldFormatSpeciesDetails($article),

            // Redundant field (same as studyType)
            // 'selectedStudyTypes' => $article->studyTypes->map(function ($st) {
            //     return ['name' => $st->name, 'status' => $st->pivot->verified ? 'Verified' : 'Unverified'];
            // })->toArray(),

            'selectedStudyTypes' => $this->buildSelectedStudyTypes($article),

            // Fields that don't exist in database
            'diseaseModel' => null,
            'TherapeuticDeliverySystems' => null,
            'Other' => null,
            // Add more mappings as needed...
        ];
    }

    private function getCellCultureField($article, $fieldName)
    {
        $protocol = $article->cellCultureProtocols()->first();

        if (! $protocol || empty($protocol->$fieldName)) {
            return null;
        }

        return [
            'name' => $protocol->$fieldName,
            'status' => $protocol->verified ? 'Verified' : 'Unverified',
        ];
    }

    private function buildSelectedStudyTypes($article)
    {
        $selectedTypes = [];

        // Get all study categories for this article
        $categories = $article->studyCategories;

        // Check for Clinical Trial categories
        $clinicalCategories = [
            'Randomized', 'Double-Blinded', 'Placebo-Controlled', 'Controlled',
            'Cross-over', 'Parallel', 'Single-Blinded', 'Triple-Blinded',
            'Open-Label', 'Multicenter', 'Single-Center',
        ];

        $hasClinicalTrial = $categories->whereIn('name', $clinicalCategories)->isNotEmpty();
        if ($hasClinicalTrial) {
            $selectedTypes[] = 'Clinical Trial';
        }

        // Check for Observational Study categories
        $observationalCategories = [
            'Cohort', 'Case-Control', 'Cross-Sectional', 'Case Series',
            'Prospective', 'Retrospective', 'Longitudinal',
        ];

        $hasObservational = $categories->whereIn('name', $observationalCategories)->isNotEmpty();
        if ($hasObservational) {
            $selectedTypes[] = 'Observational Study';
        }

        // Check for In Vivo categories (only if no clinical/observational)
        if (empty($selectedTypes)) {
            $inVivoCategories = ['Human Study', 'Animal Study', 'Plant Study'];
            $hasInVivo = $categories->where('category_type', 'in_vivo')
                ->whereIn('name', $inVivoCategories)
                ->isNotEmpty();

            if ($hasInVivo) {
                $selectedTypes[] = 'In Vivo';
            }
        }

        // Check for Non-Experimental categories
        $nonExperimentalCategories = [
            'Review', 'Meta-analysis', 'Systematic Review',
            'Opinion Piece', 'Hypothesis', 'Commentary',
        ];

        $hasNonExperimental = $categories->whereIn('name', $nonExperimentalCategories)->isNotEmpty();
        if ($hasNonExperimental) {
            $selectedTypes[] = 'Review'; // or specific type based on which is selected
        }

        // If still empty, check studyTypes as fallback
        if (empty($selectedTypes) && $article->studyTypes->isNotEmpty()) {
            $selectedTypes = $article->studyTypes->pluck('name')->unique()->toArray();
        }

        return $selectedTypes;
    }

    private function buildOldFormatSpeciesDetails($article)
    {
        $speciesDetailsFormatted = [];

        foreach ($article->speciesDetails as $sd) {
            $speciesName = $sd->species->name ?? 'Unknown';

            $speciesDetailsFormatted[$speciesName] = [
                'name' => [
                    'name' => null,
                    'status' => 'Unverified',
                    'statusConcentration' => 'estimated',
                ],
                'status' => [
                    'name' => 'Unverified',
                    'status' => 'Unverified',
                    'statusConcentration' => 'estimated',
                ],
                'DescribeSpecies' => [
                    'name' => $sd->description ?? null,
                    'status' => $sd->subjects_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => 'estimated',
                ],
                'subjects' => [
                    'name' => $sd->number_of_subjects ? (string) $sd->number_of_subjects : null,
                    'status' => $sd->subjects_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => $sd->subjects_data_source ?? 'estimated',
                ],
                'health' => [
                    'name' => $sd->health_status,
                    'status' => $sd->health_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => $sd->health_status_data_source ?? 'estimated',
                ],
                'gender' => [
                    'name' => $sd->gender,
                    'status' => $sd->gender_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => $sd->gender_data_source ?? 'estimated',
                ],
                'averageAge' => [
                    'name' => $sd->average_age ? (string) $sd->average_age : null,
                    'status' => $sd->age_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => $sd->age_data_source ?? 'estimated',
                ],
                'averageWeight' => [
                    'name' => $sd->average_weight ? (string) $sd->average_weight : null,
                    'status' => $sd->weight_verified ? 'Verified' : 'Unverified',
                    'statusConcentration' => $sd->weight_data_source ?? 'estimated',
                    'unit' => $sd->weight_unit ?? 'kg',
                ],
            ];
        }

        return $speciesDetailsFormatted;
    }

    private function buildResearcherData($article)
    {
        $design = $article->experimentalDesign;

        if (! $design) {
            // Return empty structure if no experimental design exists
            return [
                'brandName' => ['name' => '', 'status' => 'Verified'],
                'commercialProduct' => ['name' => 'No', 'status' => 'Verified'],
                'pharmacokinetics' => ['name' => 'False', 'status' => 'Verified'],
                'methodOfAdmin' => [],
                'inhalationConcentrations' => [],
                'numInhalationConcentrations' => ['name' => 0, 'status' => 'Unverified'],
                'volumes' => [],
                'concentrations' => [],
                'absoluteDoses' => [],
                'relativeDoses' => [],
                'speciesData' => [],
            ];
        }

        // Get protocols
        $inhalationProtocols = $article->inhalationProtocols;
        $ingestionProtocols = $article->ingestionProtocols;

        return [
            'brandName' => ['name' => $design->brand->name ?? '', 'status' => 'Verified'],
            'commercialProduct' => ['name' => $design->is_commercial_product ? 'Yes' : 'No', 'status' => 'Verified'],
            'pharmacokinetics' => ['name' => $design->has_pharmacokinetics ? 'True' : 'False', 'status' => 'Verified'],
            'methodOfAdmin' => $article->administrationMethods->map(function ($m) {
                return ['name' => $m->name, 'status' => $m->pivot->verified ? 'Verified' : 'Unverified'];
            })->toArray(),
            'pharmacokineticsDescription' => ['name' => $design->pharmacokinetics_description, 'status' => 'Verified'],
            'doseComparison' => ['name' => $design->has_dose_comparison ? 'True' : 'False', 'status' => 'Verified'],
            'doseComparisonDesc' => ['name' => $design->dose_comparison_description, 'status' => 'Verified'],
            'doseDependentEffect' => ['name' => $design->has_dose_dependent_effect ? 'True' : 'False', 'status' => 'Verified'],
            'drugComparison' => ['name' => $design->has_drug_comparison ? 'True' : 'False', 'status' => 'Verified'],
            'comparisonDetail' => ['name' => $design->drug_comparison_description, 'status' => 'Verified'],
            'CompMethodAdmin' => ['name' => $design->has_method_admin_comparison ? 'True' : 'False', 'status' => 'Verified'],
            'CompMethodAdminDesc' => ['name' => $design->method_admin_comparison_description, 'status' => 'Verified'],
            'isERW' => ['name' => $design->is_erw ? 'True' : 'False', 'status' => 'Verified'],
            'erwCompared' => ['name' => $design->erw_comparison_description, 'status' => 'Verified'],
            'ph' => ['name' => $design->ph_value ? number_format($design->ph_value, 2) : null, 'status' => 'Verified'],

            // ✅ FIX 1: Flatten wasOxyhydrogenUsed (single level)
            'wasOxyhydrogenUsed' => ['name' => $design->uses_oxyhydrogen ? 'Yes' : 'No', 'status' => 'Verified'],

            'safetyProfile' => ['name' => $design->has_safety_focus ? 'True' : 'False', 'status' => 'Verified'],
            'safetyofhydrogen' => ['name' => $design->has_safety_focus ? 'True' : 'False', 'status' => 'Verified'],
            'adverseEffects' => ['name' => $design->has_adverse_effects ? 'True' : 'False', 'status' => 'Verified'],
            'adverseEffectsDescription' => ['name' => $design->adverse_effects_description, 'status' => 'Verified'],
            'pregnantBreastfeeding' => ['name' => $design->includes_pregnant_breastfeeding ? 'True' : 'False', 'status' => 'Verified'],
            'responderDifference' => ['name' => $design->has_responder_difference ? 'True' : 'False', 'status' => 'Verified'],
            'sexDifference' => ['name' => $design->has_sex_difference ? 'True' : 'False', 'status' => 'Verified'],
            'geneExpression' => ['name' => $design->has_gene_expression_data ? 'True' : 'False', 'status' => 'Verified'],
            'geneExpressionDesc' => ['name' => $design->gene_expression_description, 'status' => 'Verified'],
            'mechanisticInsights' => ['name' => $design->has_mechanistic_insights ? 'True' : 'False', 'status' => 'Verified'],
            'PasteUrl' => ['name' => $design->study_url, 'status' => 'Verified'],
            'Video_WebpageLink' => ['name' => $design->video_webpage_url, 'status' => 'Verified'],

            // ✅ FIX 2: Inhalation in OLD FORMAT
            'inhalationConcentrations' => $inhalationProtocols->map(function ($p) {
                return [
                    'percentPurity' => ['name' => $p->h2_percentage, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'flowRate' => ['name' => $p->flow_rate_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'frequency' => ['name' => $p->frequency, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'duration' => ['name' => $p->duration_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'unitFlowRate' => ['name' => $p->flow_rate_unit ?? 'mL/min', 'status' => 'Unverified'],
                    'unitDuration' => ['name' => $p->duration_unit ?? 'hours', 'status' => 'Unverified'],
                ];
            })->toArray(),

            // ✅ FIX 3: numInhalationConcentrations as OBJECT
            'numInhalationConcentrations' => ['name' => $inhalationProtocols->count(), 'status' => 'Unverified'],

            // ✅ FIX 4: Peakbreathhydrogen as OBJECT
            'Peakbreathhydrogen' => ['name' => null, 'status' => 'Unverified'],

            // ✅ FIX 5: volumes in OLD FORMAT (nested objects)
            'volumes' => $ingestionProtocols->map(function ($p) {
                return [
                    'value' => ['name' => $p->volume_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'unit' => ['name' => $p->volume_unit ?? 'mL', 'status' => 'Unverified'],
                ];
            })->toArray(),

            // ✅ FIX 6: concentrations in OLD FORMAT
            'concentrations' => $ingestionProtocols->map(function ($p) {
                return [
                    'value' => ['name' => $p->concentration_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'unit' => ['name' => $p->concentration_unit ?? 'mg/L', 'status' => 'Unverified'],
                ];
            })->toArray(),

            // ✅ FIX 7: HowManyConcentrations as OBJECT
            'HowManyConcentrations' => ['name' => $ingestionProtocols->count(), 'status' => 'Unverified'],

            // ✅ FIX 8: absoluteDoses in OLD FORMAT
            'absoluteDoses' => $ingestionProtocols->map(function ($p) {
                return [
                    'value' => ['name' => $p->absolute_dose_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'unit' => ['name' => $p->absolute_dose_unit ?? 'mg/day', 'status' => 'Unverified'],
                ];
            })->toArray(),

            // ✅ FIX 9: relativeDoses in OLD FORMAT
            'relativeDoses' => $ingestionProtocols->map(function ($p) {
                return [
                    'value' => ['name' => $p->relative_dose_value, 'status' => $p->verified ? 'Verified' : 'Unverified'],
                    'unit' => ['name' => $p->relative_dose_unit ?? 'mg/kg/day', 'status' => 'Unverified'],
                ];
            })->toArray(),

            // ✅ FIX 10: IngestionDurationfrequency as OBJECT
            'IngestionDurationfrequency' => ['name' => $ingestionProtocols->first()->duration_value ?? null, 'status' => 'Unverified'],

            // ✅ FIX 11: Frequency as OBJECT
            'Frequency' => ['name' => $ingestionProtocols->first()->frequency ?? null, 'status' => 'Unverified'],

            // Cell culture
            'concentrationOfHydrogenForMedium' => ['name' => $article->cellCultureProtocols->first()->h2_concentration_value ?? null, 'status' => 'Verified'],
            'DurationFrequencyCellCultureTissues' => ['name' => $article->cellCultureProtocols->first()->duration_value ?? null, 'status' => 'Verified'],
            'FrequencyCellCultureTissues' => $article->cellCultureProtocols->first()->frequency ?? '',

            'unitDuration' => 'hours',
            'topical_how' => ['name' => null, 'status' => 'Unverified'],

            // ✅ FIX 12: bodyWeight as OBJECT
            'bodyWeight' => ['name' => null, 'status' => 'Unverified'],

            'speciesData' => $this->buildSpeciesData($article),
        ];
    }

    private function buildOldFormatInhalationConcentrations($protocols)
    {
        return $protocols->map(function ($protocol) {
            return [
                'percentPurity' => ['name' => $protocol->h2_percentage, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'flowRate' => ['name' => $protocol->flow_rate_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'frequency' => ['name' => $protocol->frequency, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'duration' => ['name' => $protocol->duration_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'unitFlowRate' => ['name' => $protocol->flow_rate_unit ?? 'mL/min', 'status' => 'Unverified'],
                'unitDuration' => ['name' => $protocol->duration_unit ?? 'minutes', 'status' => 'Unverified'],
            ];
        })->toArray();
    }

    private function buildIndividualInhalationFields($protocol)
    {
        if (! $protocol) {
            return [
                'inhalationConcentration_0_percentPurity' => ['name' => null, 'status' => 'Unverified'],
                'inhalationConcentration_0_flowRate' => ['name' => null, 'status' => 'Unverified'],
                'inhalationConcentration_0_frequency' => ['name' => null, 'status' => 'Unverified'],
                'inhalationConcentration_0_duration' => ['name' => null, 'status' => 'Unverified'],
            ];
        }

        return [
            'inhalationConcentration_0_percentPurity' => ['name' => $protocol->h2_percentage, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'inhalationConcentration_0_flowRate' => ['name' => $protocol->flow_rate_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'inhalationConcentration_0_frequency' => ['name' => $protocol->frequency, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'inhalationConcentration_0_duration' => ['name' => $protocol->duration_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
        ];
    }

    private function buildOldFormatVolumes($protocols)
    {
        return $protocols->map(function ($protocol) {
            return [
                'value' => ['name' => $protocol->volume_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'unit' => ['name' => $protocol->volume_unit ?? 'mL', 'status' => 'Unverified'],
            ];
        })->toArray();
    }

    private function buildOldFormatConcentrations($protocols)
    {
        return $protocols->map(function ($protocol) {
            return [
                'value' => ['name' => $protocol->concentration_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'unit' => ['name' => $protocol->concentration_unit ?? 'mg/L', 'status' => 'Unverified'],
            ];
        })->toArray();
    }

    private function buildOldFormatAbsoluteDoses($protocols)
    {
        return $protocols->map(function ($protocol) {
            return [
                'value' => ['name' => $protocol->absolute_dose_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'unit' => ['name' => $protocol->absolute_dose_unit ?? 'mg/day', 'status' => 'Unverified'],
            ];
        })->toArray();
    }

    private function buildOldFormatRelativeDoses($protocols)
    {
        return $protocols->map(function ($protocol) {
            return [
                'value' => ['name' => $protocol->relative_dose_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
                'unit' => ['name' => $protocol->relative_dose_unit ?? 'mg/kg/day', 'status' => 'Unverified'],
            ];
        })->toArray();
    }

    private function buildIndividualIngestionFields($protocol)
    {
        if (! $protocol) {
            return [
                'volume_0_value' => ['name' => null, 'status' => 'Unverified'],
                'concentration_0_value' => ['name' => null, 'status' => 'Unverified'],
                'absoluteDoses_0_value' => ['name' => null, 'status' => 'Unverified'],
                'relativeDoses_0_value' => ['name' => null, 'status' => 'Unverified'],
            ];
        }

        return [
            'volume_0_value' => ['name' => $protocol->volume_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'concentration_0_value' => ['name' => $protocol->concentration_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'absoluteDoses_0_value' => ['name' => $protocol->absolute_dose_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
            'relativeDoses_0_value' => ['name' => $protocol->relative_dose_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'],
        ];
    }

    private function buildIngestionDuration($protocol)
    {
        if (! $protocol) {
            return ['name' => null, 'status' => 'Unverified'];
        }

        return ['name' => $protocol->duration_value, 'status' => $protocol->verified ? 'Verified' : 'Unverified'];
    }

    private function buildIngestionFrequency($protocol)
    {
        if (! $protocol) {
            return ['name' => null, 'status' => 'Unverified'];
        }

        return ['name' => $protocol->frequency, 'status' => $protocol->verified ? 'Verified' : 'Unverified'];
    }

    private function getCellCultureField1($article, $fieldName)
    {
        $protocol = $article->cellCultureProtocols()->first();

        if (! $protocol || empty($protocol->$fieldName)) {
            return ['name' => null, 'status' => 'Verified'];
        }

        return [
            'name' => $protocol->$fieldName,
            'status' => $protocol->verified ? 'Verified' : 'Unverified',
        ];
    }

    private function getCellCultureFieldSimple($article, $fieldName)
    {
        $protocol = $article->cellCultureProtocols()->first();

        if (! $protocol) {
            return '';
        }

        return $protocol->$fieldName ?? '';
    }

    private function buildInhalationData($article)
    {
        return $article->inhalationProtocols->map(function ($protocol) {
            return [
                'h2_percentage' => $protocol->h2_percentage,
                'o2_percentage' => $protocol->o2_percentage,
                'flow_rate' => ['value' => $protocol->flow_rate_value, 'unit' => $protocol->flow_rate_unit],
            ];
        })->toArray();
    }

    private function buildVolumesData($article)
    {
        return $article->ingestionProtocols->map(function ($protocol) {
            return ['value' => $protocol->volume_value, 'unit' => $protocol->volume_unit];
        })->toArray();
    }

    private function buildConcentrationsData($article)
    {
        return $article->ingestionProtocols->map(function ($protocol) {
            return ['value' => $protocol->concentration_value, 'unit' => $protocol->concentration_unit];
        })->toArray();
    }

    private function buildAbsoluteDosesData($article)
    {
        return $article->ingestionProtocols->map(function ($protocol) {
            return ['value' => $protocol->absolute_dose_value, 'unit' => $protocol->absolute_dose_unit];
        })->toArray();
    }

    private function buildRelativeDosesData($article)
    {
        return $article->ingestionProtocols->map(function ($protocol) {
            return ['value' => $protocol->relative_dose_value, 'unit' => $protocol->relative_dose_unit];
        })->toArray();
    }

    private function buildSpeciesProtocolData($article)
    {
        // Build grouped protocol data by species
        // This is complex - would need to combine data from multiple protocol tables
        return [];
    }

    private function buildBiomakerData(Article $article)
    {
        return $article->biomarkers->map(function ($biomarker) {
            return [
                'marker' => $biomarker->biomarker->name,
                'category' => $biomarker->categories->pluck('name')->toArray(),
                'Change' => $biomarker->changeDirection ? [$biomarker->changeDirection->name] : [],
                'Protein' => $biomarker->protein_name,
                'is_measured' => $biomarker->is_measured,
                'status' => $biomarker->verified ? 'Verified' : 'Unverified',
            ];
        })->toArray();
    }

    /**
     * Convert array to string safely - handles ALL possible formats
     */
    private function arrayToString($value)
    {
        // If it's already a string, clean it
        if (is_string($value)) {
            // Remove any "\nVerified" or "\nUnverified" suffixes
            $value = preg_replace('/\n(Verified|Unverified)$/i', '', $value);

            return trim($value);
        }

        // If it's null or empty
        if (empty($value)) {
            return '';
        }

        // If it's an array
        if (is_array($value)) {
            // Check if associative array with 'name' key
            if (isset($value['name'])) {
                $result = (string) $value['name'];
                // Remove any "\nVerified" or "\nUnverified" suffixes
                $result = preg_replace('/\n(Verified|Unverified)$/i', '', $result);

                return trim($result);
            }

            // Check if indexed array (old format)
            if (isset($value[0])) {
                $result = (string) $value[0];
                // Remove any "\nVerified" or "\nUnverified" suffixes
                $result = preg_replace('/\n(Verified|Unverified)$/i', '', $result);

                return trim($result);
            }

            // Empty array
            return '';
        }

        // For any other type, cast to string
        return trim((string) $value);
    }

    /**
     * Extract integer from array or value
     */
    private function arrayToInt($value)
    {
        // If already a number
        if (is_numeric($value)) {
            return (int) $value;
        }

        // If it's an array
        if (is_array($value)) {
            // Check if associative array with 'name' key
            if (isset($value['name'])) {
                $val = $value['name'];
            }
            // Check if indexed array (old format)
            elseif (isset($value[0])) {
                $val = $value[0];
            } else {
                return null;
            }
        } else {
            $val = $value;
        }

        return is_numeric($val) ? (int) $val : null;
    }

    /**
     * Extract verification status from array
     */
    private function getVerificationStatus($value)
    {
        // If it's a boolean already
        if (is_bool($value)) {
            return $value;
        }

        // If it's an array with 'status' key
        if (is_array($value) && isset($value['status'])) {
            return strtolower($value['status']) === 'verified';
        }

        // If it's a string
        if (is_string($value)) {
            return strtolower($value) === 'verified';
        }

        return false;
    }

    private function arrayToDecimal($value)
    {
        // Convert array to string first
        $stringValue = $this->arrayToString($value);

        // If empty string, return null
        if ($stringValue === '' || $stringValue === null) {
            return null;
        }

        // If numeric, return as is
        if (is_numeric($stringValue)) {
            return $stringValue;
        }

        // Otherwise return null
        return null;
    }

    /**
     * Convert value to decimal or null
     * Handles 'N/A', empty strings, and non-numeric values
     */
    private function toDecimalOrNull($value)
    {
        // If already null or empty
        if (empty($value) || $value === null) {
            return null;
        }

        // If it's an array, extract the value
        if (is_array($value)) {
            $value = $value['value'] ?? $value['name'] ?? null;
        }

        // Convert to string and clean
        $value = trim((string) $value);

        // Check for N/A or similar
        if (in_array(strtolower($value), ['n/a', 'na', 'n\\a', 'not applicable', ''])) {
            return null;
        }

        // Check if numeric
        if (! is_numeric($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Convert array/string to float
     */
    private function arrayToFloat($value)
    {
        if (is_array($value)) {
            $value = $value['name'] ?? null;
        }

        if (empty($value)) {
            return null;
        }

        // Remove any non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        return $cleaned ? (float) $cleaned : null;
    }
}
