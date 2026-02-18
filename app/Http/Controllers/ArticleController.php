<?php

// ============================================================================
// FILE: app/Http/Controllers/ArticleController.php
// COMPLETE REWRITE - Using Normalized Structure with Eloquent Relationships
// ============================================================================

namespace App\Http\Controllers;

use App\Models\AdministrationMethod;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\BioBridge;
use App\Models\BioCategory;
use App\Models\BioSub;
use App\Models\Country;
use App\Models\Disease;
use App\Models\Organ;
use App\Models\PortalArticle;
use App\Models\ResearchTopic;
use App\Models\Species;
use App\Models\StudyType;
use App\Models\System;
use App\Models\VerifiedAuthor;
use App\Services\ArticleTransformationService;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    protected $transformService;

    public function __construct(ArticleTransformationService $transformService)
    {
        $this->transformService = $transformService;
    }

    // ============================================================================
    // ARTICLE MANAGEMENT
    // ============================================================================

    /**
     * Toggle trending status
     * POST /api/toggle-trending/{id}
     */
    public function toggleTrending(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $article->is_trending = ! $article->is_trending;
        $article->save();

        return response()->json([
            'message' => $article->is_trending ? 'Article marked as trending' : 'Article removed from trending',
            'is_trending' => $article->is_trending,
            'status' => true,
        ]);
    }

    /**
     * Get all articles with relationships
     * GET /api/get-articles
     */
    public function getArticles()
    {
        $articles = Article::with([
            'publicationDetail.journal',
            'authors' => function ($q) {
                $q->orderBy('article_authors.author_order');
            },
            'species',
            'diseases',
            'organs',
            'systems',
            'cellCultureProtocols',
            'studyTypes',
        ])->get();

        // Transform to JSON format for backward compatibility
        $transformed = $articles->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        return response()->json($transformed);
    }

    /**
     * Filter articles by keywords
     * POST /api/keywords-articles
     */
    public function keywordsArticles(Request $req)
    {
        $keywords = explode(',', $req->keywords);
        $keywords = array_map('trim', $keywords);

        $articles = Article::whereHas('keywords', function ($query) use ($keywords) {
            $query->whereIn('keywords.keyword', $keywords);
        })->with([
            'publicationDetail.journal',
            'authors',
            'keywords',
            'species',
            'studyTypes',
            'cellCultureProtocols',
        ])->get();

        $transformed = $articles->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        return response()->json([
            'status' => true,
            'articles' => $transformed,
        ]);
    }

    /**
     * Filter by year
     * POST /api/filter-by-year
     */
    public function filterByYear(Request $req)
    {
        $query = Article::whereHas('publicationDetail', function ($q) use ($req) {
            $q->where('year', $req->year);
        })->with(['publicationDetail.journal', 'authors', 'species', 'studyTypes', 'cellCultureProtocols']);

        $articles = $query->get();

        $transformed = $articles->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        return response()->json([
            'status' => true,
            'articles' => $transformed,
            'count' => $articles->count(),
        ]);
    }

    /**
     * Complex filtering
     * POST /api/filter-articles
     */
    public function filterArticles(Request $req)
    {
        $query = Article::query();

        // Filter by status
        if ($req->has('status')) {
            $query->where('status', $req->status);
        }

        // Filter by year range
        if ($req->has('year_from') || $req->has('year_to')) {
            $query->whereHas('publicationDetail', function ($q) use ($req) {
                if ($req->year_from) {
                    $q->where('year', '>=', $req->year_from);
                }
                if ($req->year_to) {
                    $q->where('year', '<=', $req->year_to);
                }
            });
        }

        // Filter by species
        if ($req->has('species') && is_array($req->species)) {
            $query->whereHas('species', function ($q) use ($req) {
                $q->whereIn('species.id', $req->species);
            });
        }

        // Filter by disease
        if ($req->has('diseases') && is_array($req->diseases)) {
            $query->whereHas('diseases', function ($q) use ($req) {
                $q->whereIn('diseases.id', $req->diseases);
            });
        }

        // Filter by organs
        if ($req->has('organs') && is_array($req->organs)) {
            $query->whereHas('organs', function ($q) use ($req) {
                $q->whereIn('organs.id', $req->organs);
            });
        }

        // Filter by study type
        if ($req->has('study_types') && is_array($req->study_types)) {
            $query->whereHas('studyTypes', function ($q) use ($req) {
                $q->whereIn('study_types.id', $req->study_types);
            });
        }

        // Filter by country
        if ($req->has('countries') && is_array($req->countries)) {
            $query->whereHas('countries', function ($q) use ($req) {
                $q->whereIn('countries.id', $req->countries);
            });
        }

        // Trending only
        if ($req->has('trending') && $req->trending) {
            $query->where('is_trending', true);
        }

        $articles = $query->with([
            'publicationDetail.journal',
            'authors',
            'species',
            'diseases',
            'organs',
            'studyTypes',
        ])->paginate($req->per_page ?? 20);

        return response()->json($articles);
    }

    /**
     * Get article by ID with full relationships
     * GET /api/get-article/{id}
     */
    public function getArticleById($id)
    {

        $article = Article::with([
            // 'publicationDetail.journal.publisher',
            'publicationDetail.journal',
            'publicationDetail.publisher',
            'authors' => function ($q) {
                $q->orderBy('article_authors.author_order');
            },
            'keywords',
            'countries',
            'pdfFiles',
            'studyTypes',
            'studyCategories',
            'highlightInfo',
            'species.articleDetails',
            'organs',
            'systems',
            'diseases',
            'researchTopics',
            'timingTreatments',
            'outcomeTypes',
            'outcome',
            // 'studyDurations',
            'experimentalDesign.brand',
            'administrationMethods',
            'inhalationProtocols.species',
            'ingestionProtocols.species',
            'cellCultureProtocols',
            'topicalProtocols',
            'biomarkers.biomarker.categories',
            'biomarkers.changeDirection',
            'biomarkers.categories',
            'reviewer',
            'verifiedBy',
            'addedBy',
        ])->find($id);

        if (! $article) {
            return response()->json([
                'status' => false,
                'message' => 'Article not found',
            ], 404);
        }

        // Return in JSON format for backward compatibility
        return response()->json([
            'status' => true,
            'article' => $this->transformService->transformToJson($article),
        ]);
    }

    /**
     * Get article by MHID with related articles
     * GET /api/get-article-mhid/{mhid}
     */
    public function getArticleByMhid($mhid)
    {
//        $article = Article::where('mhid', $mhid)
//            ->with([
//                'publicationDetail.journal',
//                'authors',
//                'keywords',
//                'countries',
//                'species',
//                'diseases',
//                'organs',
//                'systems',
//                'studyTypes',
//                'researchTopics',
//                'administrationMethods',
//                'cellCultureProtocols',
//                'biomarkers.biomarker',
//            ])->firstOrFail();

        $article = Article::where('mhid', $mhid)
            ->with([
                'publicationDetail.journal',
                'authors',
                'keywords',
                'countries',
                'species',
                'diseases',
                'organs',
                'systems',
                'studyTypes',
                'researchTopics',
                'administrationMethods',
                'cellCultureProtocols',
                'biomarkers' => function ($q) {
                    $q->whereHas('biomarker', function ($qq) {
                        $qq->whereNull('parent_id');
                    })->with('biomarker'); // load the BioSub data too
                },
            ])
            ->firstOrFail();


        // Get related articles (same species or diseases)
        $relatedArticles = Article::where('id', '!=', $article->id)
            ->where(function ($query) use ($article) {
                // Same species
                if ($article->species->isNotEmpty()) {
                    $speciesIds = $article->species->pluck('id')->toArray();
                    $query->whereHas('species', function ($q) use ($speciesIds) {
                        $q->whereIn('species.id', $speciesIds);
                    });
                }
                // Or same diseases
                if ($article->diseases->isNotEmpty()) {
                    $diseaseIds = $article->diseases->pluck('id')->toArray();
                    $query->orWhereHas('diseases', function ($q) use ($diseaseIds) {
                        $q->whereIn('diseases.id', $diseaseIds);
                    });
                }
            })
            ->with('publicationDetail')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'article' => $this->transformService->transformToJson($article),
            'relatedArticles' => $relatedArticles->map(function ($a) {
                return $this->transformService->transformToJson($a);
            }),
        ]);
    }

    /**
     * Get article titles
     * POST /api/get-title
     */
    public function getTitle(Request $req)
    {
        $query = Article::with('publicationDetail');

        if ($req->has('search')) {
            $query->whereHas('publicationDetail', function ($q) use ($req) {
                $q->where('title', 'like', '%'.$req->search.'%');
            });
        }

        $articles = $query->limit(50)->get();

        $titles = $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'mhid' => $article->mhid,
                'title' => $article->publicationDetail->title ?? '',
                'year' => $article->publicationDetail->year ?? '',
            ];
        });

        return response()->json($titles);
    }

    // ============================================================================
    // ARTICLE LISTING WITH ADVANCED FILTERING
    // ============================================================================

    /**
     * Main article listing with full filter support (AND/OR logic)
     * POST /api/list-articles-main
     */
    public function listArticlesMain(Request $req)
    {
//        Log::info('$req', $req->suboption);
        // Get parameters
        $page = $req->input('page', 1);
        $perPage = $req->input('per_page', 20);
        $orderBy = $req->input('orderBy', 'DESC'); // ASC or DESC
        $isAnd = $req->input('isAnd', true); // true for AND logic, false for OR
        $searchTerms = $req->input('admin_search', []); // Array of search terms

        // Build query with relationships
        $query = Article::with([
            'publicationDetail.journal',
            'publicationDetail.publisher',
            'authors',
            'species',
            'diseases',
            'organs',
            'systems',
            'studyTypes',
            'researchTopics',
            'countries',
            'administrationMethods',
            'cellCultureProtocols',
        ]);
        if (auth()->check() && auth()->user()->role_id == 2) {
            $query->where('reviewer_id', auth()->id());
        }

        // Apply status filter (default to Verified)
        $status = $req->input('status', 'Verified');
        $query->where('status', $status);

        // Highlighted filter (frontend sends: isHighlightArticle: true)
        if ($req->boolean('isHighlightArticle')) {
            $query->where('is_highlighted', 1);
        }

//        // Apply search if provided
//        if (! empty($searchTerms) && is_array($searchTerms)) {
//            $query->where(function ($q) use ($searchTerms) {
//                foreach ($searchTerms as $searchTerm) {
//                    if (empty($searchTerm)) {
//                        continue;
//                    }
//
//                    $q->where(function ($subQ) use ($searchTerm) {
//                        // Search in MHID, DOI, PMID
//                        $subQ->where('mhid', 'like', "%{$searchTerm}%")
//                            ->orWhere('doi', 'like', "%{$searchTerm}%")
//                            ->orWhere('pmid', 'like', "%{$searchTerm}%")
//
//                            // Search in publication details
//                            ->orWhereHas('publicationDetail', function ($pubQ) use ($searchTerm) {
//                                $pubQ->where('title', 'like', "%{$searchTerm}%")
//                                    ->orWhere('abstract', 'like', "%{$searchTerm}%");
//                            })
//
//                            // Search in authors
//                            ->orWhereHas('authors', function ($authQ) use ($searchTerm) {
//                                $authQ->where('verified_authors.name', 'like', "%{$searchTerm}%");
//                            })
//
//                            // Search in journal
//                            ->orWhereHas('publicationDetail.journal', function ($journalQ) use ($searchTerm) {
//                                $journalQ->where('journals.name', 'like', "%{$searchTerm}%");
//                            });
//                    });
//                }
//            });
//        }

        // Apply search if provided (Title only)
        if (!empty($searchTerms) && is_array($searchTerms)) {
            $query->whereHas('publicationDetail', function ($q) use ($searchTerms) {
                foreach ($searchTerms as $searchTerm) {
                    if (empty($searchTerm)) {
                        continue;
                    }

                    $q->where('title', 'like', "%{$searchTerm}%");
                }
            });
        }

        // Apply filters based on AND/OR logic
        $filters = [
            'studyTypes' => $req->input('studyTypes', []),
            'species' => $req->input('species', []),
            'researchTopics' => $req->input('researchTopics', []),
            'systems' => $req->input('systems', []),
            'organs' => $req->input('organs', []),
            'countries' => $req->input('countries', []),
            'diseases' => $req->input('diseases', []),
            'administrationMethods' => $req->input('administrationMethods', []),
            'biomarkers' => $req->input('biomarkers', []),
            'years' => $req->input('years', []),
            'authors' => $req->input('authors', []),
        ];

        // ---------- NEW: expand parent author -> include all child authors ----------
        if (!empty($filters['authors']) && is_array($filters['authors'])) {
            $authorIds = array_values(array_unique(array_filter($filters['authors'])));

            if (!empty($authorIds)) {
                // One-level children (parent_id in selected authors)
                $childIds = \App\Models\VerifiedAuthor::whereIn('parent_id', $authorIds)
                    ->pluck('id')
                    ->toArray();

                $filters['authors'] = array_values(array_unique(array_merge($authorIds, $childIds)));
            }
        }
        // --------------------------------------------------------------------------

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return ! empty($value) && is_array($value);
        });

        if (! empty($filters)) {
            if ($isAnd) {
                $query = $this->applyAndLogic($query, $filters);
            } else {
                $query = $this->applyOrLogic($query, $filters);
            }
        }

        // Apply ordering
        $query->orderBy('created_at', $orderBy);



        //###################################################################
        // Handle study category suboptions (e.g. "in vivo" => ["Human Study", "Animal Study"])
        $subOptions = $req->suboption ? $req->suboption: [];

        // Map request keys to DB enum values (adjust the right side to match your enum exactly)
        $categoryTypeMap = [
            'in vivo' => 'in_vivo',
            'non-experimental (review)' => 'non_experimental_review', // change to your real enum
        ];

        if (!empty($subOptions) && is_array($subOptions)) {
            Log::info('it works');
            $query->where(function ($articleQ) use ($subOptions, $categoryTypeMap) {
                foreach ($subOptions as $typeLabel => $names) {
                    if (empty($names) || !is_array($names)) {
                        continue;
                    }

                    // If we don't know how to map this label, skip
                    if (!isset($categoryTypeMap[$typeLabel])) {
                        continue;
                    }

                    $categoryType = $categoryTypeMap[$typeLabel];

                    // For each group (e.g. "in vivo") we OR the condition:
                    $articleQ->orWhereHas('studyCategories', function ($scQ) use ($names, $categoryType) {
                        $scQ->where('category_type', $categoryType)
                            ->whereIn('name', $names);
                    });
                }
            });
        }

        //###################################################################

        // Paginate
        $articles = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform articles
        $transformed = $articles->getCollection()->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        return response()->json([
            'status' => true,
            'message' => 'Articles fetched successfully',
            'articles' => $transformed,
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
            'per_page' => $articles->perPage(),
            'total' => $articles->total(),
            'from' => $articles->firstItem(),
            'to' => $articles->lastItem(),
            'logic_type' => $isAnd ? 'AND' : 'OR',
            'total_filters' => count($filters),
            'search_terms' => $searchTerms,
            'isHighlightArticle' => $req->boolean('isHighlightArticle'),

        ]);
    }

    /**
     * Apply AND logic - article must match ALL filter types
     * Example: Must have species AND researchTopic AND system (all required)
     */
    private function applyAndLogic($query, $filters)
    {
        foreach ($filters as $filterType => $values) {
            if (empty($values) || ! is_array($values)) {
                continue;
            }

            // Expand filters with children if applicable
            $values = $this->expandFilterWithChildren($filterType, $values);

            switch ($filterType) {
                case 'studyTypes':
                    $query->whereHas('studyTypes', function ($q) use ($values) {
                        $q->whereIn('study_types.id', $values);
                    });
                    break;

                case 'species':
                    $query->whereHas('species', function ($q) use ($values) {
                        $q->whereIn('species.id', $values);
                    });
                    break;

                case 'diseases':
                    $query->whereHas('diseases', function ($q) use ($values) {
                        $q->whereIn('diseases.id', $values);
                    });
                    break;

                case 'organs':
                    $query->whereHas('organs', function ($q) use ($values) {
                        $q->whereIn('organs.id', $values);
                    });
                    break;

                case 'systems':
                    $query->whereHas('systems', function ($q) use ($values) {
                        $q->whereIn('systems.id', $values);
                    });
                    break;

                case 'researchTopics':
                    $query->whereHas('researchTopics', function ($q) use ($values) {
                        $q->whereIn('research_topics.id', $values);
                    });
                    break;

                case 'countries':
                    $query->whereHas('countries', function ($q) use ($values) {
                        $q->whereIn('countries.id', $values);
                    });
                    break;

                case 'administrationMethods':
                    $query->whereHas('administrationMethods', function ($q) use ($values) {
                        $q->whereIn('administration_methods.id', $values);
                    });
                    break;

                case 'biomarkers':
                    $query->whereHas('biomarkers.biomarker', function ($q) use ($values) {
                        $q->whereIn('bio_sub.id', $values);
                    });
                    break;

                case 'years':
                    $query->whereHas('publicationDetail', function ($q) use ($values) {
                        $q->whereIn('year', $values);
                    });
                    break;

                case 'authors':
                    $query->whereHas('authors', function ($q) use ($values) {
                        $q->whereIn('verified_authors.id', $values);
                    });
                    break;
            }
        }

        return $query;
    }

    /**
     * Apply OR logic - article can match ANY filter from ANY type
     * Example: Can have species OR researchTopic OR system (any one is enough)
     */
    private function applyOrLogic($query, $filters)
    {
        // Wrap everything in a single OR clause
        $query->where(function ($q) use ($filters) {
            $hasCondition = false;

            foreach ($filters as $filterType => $values) {
                if (empty($values) || ! is_array($values)) {
                    continue;
                }

                // Expand filters with children if applicable
                $values = $this->expandFilterWithChildren($filterType, $values);

                switch ($filterType) {
                    case 'studyTypes':
                        if ($hasCondition) {
                            $q->orWhereHas('studyTypes', function ($subQ) use ($values) {
                                $subQ->whereIn('study_types.id', $values);
                            });
                        } else {
                            $q->whereHas('studyTypes', function ($subQ) use ($values) {
                                $subQ->whereIn('study_types.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'species':
                        if ($hasCondition) {
                            $q->orWhereHas('species', function ($subQ) use ($values) {
                                $subQ->whereIn('species.id', $values);
                            });
                        } else {
                            $q->whereHas('species', function ($subQ) use ($values) {
                                $subQ->whereIn('species.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'diseases':
                        if ($hasCondition) {
                            $q->orWhereHas('diseases', function ($subQ) use ($values) {
                                $subQ->whereIn('diseases.id', $values);
                            });
                        } else {
                            $q->whereHas('diseases', function ($subQ) use ($values) {
                                $subQ->whereIn('diseases.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'organs':
                        if ($hasCondition) {
                            $q->orWhereHas('organs', function ($subQ) use ($values) {
                                $subQ->whereIn('organs.id', $values);
                            });
                        } else {
                            $q->whereHas('organs', function ($subQ) use ($values) {
                                $subQ->whereIn('organs.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'systems':
                        if ($hasCondition) {
                            $q->orWhereHas('systems', function ($subQ) use ($values) {
                                $subQ->whereIn('systems.id', $values);
                            });
                        } else {
                            $q->whereHas('systems', function ($subQ) use ($values) {
                                $subQ->whereIn('systems.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'researchTopics':
                        if ($hasCondition) {
                            $q->orWhereHas('researchTopics', function ($subQ) use ($values) {
                                $subQ->whereIn('research_topics.id', $values);
                            });
                        } else {
                            $q->whereHas('researchTopics', function ($subQ) use ($values) {
                                $subQ->whereIn('research_topics.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'countries':
                        if ($hasCondition) {
                            $q->orWhereHas('countries', function ($subQ) use ($values) {
                                $subQ->whereIn('countries.id', $values);
                            });
                        } else {
                            $q->whereHas('countries', function ($subQ) use ($values) {
                                $subQ->whereIn('countries.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'administrationMethods':
                        if ($hasCondition) {
                            $q->orWhereHas('administrationMethods', function ($subQ) use ($values) {
                                $subQ->whereIn('administration_methods.id', $values);
                            });
                        } else {
                            $q->whereHas('administrationMethods', function ($subQ) use ($values) {
                                $subQ->whereIn('administration_methods.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'biomarkers':
                        if ($hasCondition) {
                            $q->orWhereHas('biomarkers.biomarker', function ($subQ) use ($values) {
                                $subQ->whereIn('bio_sub.id', $values);
                            });
                        } else {
                            $q->whereHas('biomarkers.biomarker', function ($subQ) use ($values) {
                                $subQ->whereIn('bio_sub.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'years':
                        if ($hasCondition) {
                            $q->orWhereHas('publicationDetail', function ($subQ) use ($values) {
                                $subQ->whereIn('year', $values);
                            });
                        } else {
                            $q->whereHas('publicationDetail', function ($subQ) use ($values) {
                                $subQ->whereIn('year', $values);
                            });
                            $hasCondition = true;
                        }
                        break;

                    case 'authors':
                        if ($hasCondition) {
                            $q->orWhereHas('authors', function ($subQ) use ($values) {
                                $subQ->whereIn('verified_authors.id', $values);
                            });
                        } else {
                            $q->whereHas('authors', function ($subQ) use ($values) {
                                $subQ->whereIn('verified_authors.id', $values);
                            });
                            $hasCondition = true;
                        }
                        break;
                }
            }
        });

        return $query;
    }

    /**
     * Expand filter values to include children (for hierarchical data)
     */
    private function expandFilterWithChildren($filterType, $values)
    {
        // Only expand for hierarchical filters
        $hierarchicalFilters = ['researchTopics', 'diseases', 'systems', 'organs'];

        if (! in_array($filterType, $hierarchicalFilters)) {
            return $values;
        }

        $expandedValues = $values;

        switch ($filterType) {
            case 'researchTopics':
                foreach ($values as $topicId) {
                    $children = ResearchTopic::where('parent_id', $topicId)->pluck('id')->toArray();
                    $expandedValues = array_merge($expandedValues, $children);
                }
                break;

            case 'diseases':
                foreach ($values as $diseaseId) {
                    $children = Disease::where('parent_id', $diseaseId)->pluck('id')->toArray();
                    $expandedValues = array_merge($expandedValues, $children);
                }
                break;

            case 'systems':
                foreach ($values as $systemId) {
                    $children = System::where('parent_id', $systemId)->pluck('id')->toArray();
                    $expandedValues = array_merge($expandedValues, $children);
                }
                break;

            case 'organs':
                foreach ($values as $organId) {
                    $children = Organ::where('parent_id', $organId)->pluck('id')->toArray();
                    $expandedValues = array_merge($expandedValues, $children);
                }
                break;
        }

        return array_unique($expandedValues);
    }

    private function getSpeciesChildrenIds($species)
    {
        $ids = [];
        foreach ($species->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getSpeciesChildrenIds($child));
        }

        return $ids;
    }

    private function getDiseaseChildrenIds($disease)
    {
        $ids = [];
        foreach ($disease->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDiseaseChildrenIds($child));
        }

        return $ids;
    }

    private function getCountryChildrenIds($country)
    {
        $ids = [];
        foreach ($country->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getCountryChildrenIds($child));
        }

        return $ids;
    }

    /**
     * Simple article listing
     * POST /api/list-articles
     */
    /**
     * List articles for admin with specific filters
     * POST /api/final-article-list-admin
     */
    public function listArticles(Request $req)
    {
        // Get parameters
        $page = $req->input('page', 1);
        $perPage = $req->input('per_page', 25);
        $orderBy = $req->input('orderBy', 'DESC');
        $searchTerm = $req->input('admin_search', '');

        // Build query with relationships
        $query = Article::with([
            'publicationDetail.journal',
            'publicationDetail.publisher',
            'authors',
            'species',
            'diseases',
            'organs',
            'systems',
            'studyTypes',
            'researchTopics',
            'countries',
            'administrationMethods',
            'reviewer',
            'verifiedBy',
            'addedBy',
        ]);

        // Apply status filter (optional, no default)
        if ($req->has('status') && ! empty($req->status)) {
            $query->where('status', $req->status);
        }

        // Apply trending filter
        if ($req->has('trending') && $req->trending) {
            $query->where('is_trending', true);
        }

        // Apply assignment filter (articles assigned to current user)
        if ($req->has('assignment') && $req->assignment) {
            $userId = auth()->id();
            if ($userId) {
                $query->where('reviewer_id', $userId);
            }
        }
        $includeKeywords = $req->has('genericKeywords') && $req->genericKeywords;

        // Apply search if provided
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm, $req) {
                // Search in MHID, DOI, PMID
                $q->where('mhid', 'like', "%{$searchTerm}%")
                    ->orWhere('doi', 'like', "%{$searchTerm}%")
                    ->orWhere('pmid', 'like', "%{$searchTerm}%")

                    // Search in publication details
                    ->orWhereHas('publicationDetail', function ($pubQ) use ($searchTerm) {
                        $pubQ->where('title', 'like', "%{$searchTerm}%")
                            ->orWhere('abstract', 'like', "%{$searchTerm}%");
                    })

                    // Search in authors
                    ->orWhereHas('authors', function ($authQ) use ($searchTerm) {
                        $authQ->where('verified_authors.name', 'like', "%{$searchTerm}%");
                    })

                    // Search in journal
                    ->orWhereHas('publicationDetail.journal', function ($journalQ) use ($searchTerm) {
                        $journalQ->where('journals.name', 'like', "%{$searchTerm}%");
                    })

                    // Search in keywords (if genericKeywords is true)
                    ->when($req->has('genericKeywords') && $req->genericKeywords, function ($subQ) use ($searchTerm) {
                        $subQ->orWhereHas('keywords', function ($keyQ) use ($searchTerm) {
                            $keyQ->where('keywords.name', 'like', "%{$searchTerm}%");
                        });
                    });
            });
        }

        // Apply additional filters if provided (like the main filter function)
        $filters = [
            'studyTypes' => $req->input('studyTypes', []),
            'species' => $req->input('species', []),
            'researchTopics' => $req->input('researchTopics', []),
            'systems' => $req->input('systems', []),
            'organs' => $req->input('organs', []),
            'countries' => $req->input('countries', []),
            'diseases' => $req->input('diseases', []),
            'administrationMethods' => $req->input('administrationMethods', []),
            'biomarkers' => $req->input('biomarkers', []),
            'years' => $req->input('years', []),
            'authors' => $req->input('authors', []),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return ! empty($value) && is_array($value);
        });

        // Apply filters with AND logic
        if (! empty($filters)) {
            $query = $this->applyAndLogic($query, $filters);
        }

        // Apply ordering
        $query->orderBy('created_at', $orderBy);

        // Paginate
        $articles = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform articles
        $transformed = $articles->getCollection()->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        // Get status counts for admin overview
        $statusCounts = Article::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'Articles fetched successfully',
            'articles' => $transformed,
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
            'per_page' => $articles->perPage(),
            'total' => $articles->total(),
            'from' => $articles->firstItem(),
            'to' => $articles->lastItem(),
            'filters_applied' => [
                'status' => $req->input('status', 'all'),
                'trending' => $req->input('trending', false),
                'assignment' => $req->input('assignment', false),
                'search' => $searchTerm,
                'generic_keywords' => $req->input('genericKeywords', false),
            ],
            'status_counts' => $statusCounts,

        ]);
    }

    /**
     * Enhanced listing (alias)
     * POST /api/list-articles-2
     */
    public function listArticles2(Request $req)
    {
        return $this->listArticlesMain($req);
    }

    /**
     * Get researcher articles
     * POST /api/researcher-article
     */
    public function ResearcherArticle(Request $req)
    {
        $query = Article::with([
            'publicationDetail',
            'experimentalDesign.brand',
            'administrationMethods',
            'inhalationProtocols',
            'ingestionProtocols',
            'cellCultureProtocols',
        ]);

        if ($req->has('brand')) {
            $query->whereHas('experimentalDesign', function ($q) use ($req) {
                $q->where('brand_id', $req->brand);
            });
        }

        if ($req->has('administration_method')) {
            $query->whereHas('administrationMethods', function ($q) use ($req) {
                $q->where('administration_methods.id', $req->administration_method);
            });
        }
        $query->where('added_by', $req->user()->id);
        $articles = $query->get();

        return response()->json([
            'status' => true,
            'articles' => $articles->map(function ($a) {
                return $this->transformService->transformToJson($a);
            }),
        ]);
    }

    // ============================================================================
    // HOMEPAGE & STATISTICS
    // ============================================================================

    /**
     * Homepage statistics
     * POST /api/home-page
     */
    public function HomePage(Request $req)
    {
        // Total studies
        $totalStudies = Article::where('status', 'Verified')->count();

        // Human studies count
        $humanStudies = Article::where('status', 'Verified')
            ->whereHas('species', function ($q) {
                $q->where('species.name', 'like', '%human%');
            })->count();

        // Disease models count
        $diseaseModels = Article::where('status', 'Verified')
            ->whereHas('diseases')->count();

        // Years distribution
        $yearsData = Article::where('status', 'Verified')
            ->join('article_publication_details', 'articles.id', '=', 'article_publication_details.article_id')
            ->whereNotNull('article_publication_details.year')
            ->select('article_publication_details.year as year', DB::raw('count(*) as count'))
            ->groupBy('article_publication_details.year')
            ->orderBy('article_publication_details.year', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => (int) $item->year,
                    'count' => $item->count,
                ];
            });

        // Study types distribution - as key-value object
        $studyTypesData = StudyType::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Species distribution - as key-value object
        $speciesData = Species::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Organs distribution - as array with name and count
        $organsData = Organ::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->map(function ($organ) {
                return [
                    'name' => $organ->name,
                    'count' => $organ->articles_count,
                ];
            })
            ->values();

        // Research topics distribution - as key-value object
        $topicsData = ResearchTopic::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Latest articles - 5 most recent
        $latestArticles = Article::where('status', 'Verified')
            ->with([
                'publicationDetail.journal',
                'publicationDetail.publisher',
                'authors',
                'species',
                'diseases',
                'organs',
                'systems',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($article) {
                return $this->transformService->transformToJson($article);
            });

        // Trending articles - 5 trending articles
        $trendingArticles = Article::where('status', 'Verified')
            ->where('is_trending', true)
            ->with([
                'publicationDetail.journal',
                'publicationDetail.publisher',
                'authors',
                'species',
                'diseases',
                'organs',
                'systems',
                'cellCultureProtocols'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($article) {
                return $this->transformService->transformToJson($article);
            });

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => [
                'totalStudies' => $totalStudies,
                'humanStudies' => $humanStudies,
                'diseaseModels' => $diseaseModels,
                'yearsGraph' => $yearsData,
                'studyTypes' => $studyTypesData,
                'species' => $speciesData,
                'organs' => $organsData,
                'researchTopics' => $topicsData,
                'latest_articles' => $latestArticles,
                'trending_articles' => $trendingArticles,
            ],
        ]);
    }

    // ============================================================================
    // FILTERS METADATA
    // ============================================================================

    /**
     * Get all filter options with counts
     * POST /api/get-filters
     */
    public function getFilters(Request $req)
    {
        // Species with article counts (hierarchical)
        $species = Species::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $speciesTree = $this->buildSpeciesTree($species);

        // Diseases with article counts (hierarchical)
        $diseases = Disease::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $diseaseTree = $this->buildDiseaseTree($diseases);

        // Countries with article counts (hierarchical)
        $countries = Country::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $countryTree = $this->buildCountryTree($countries);

        // Organs
        $organs = Organ::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->having('articles_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Systems
        $systems = System::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->having('articles_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Study Types
        $studyTypes = StudyType::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->having('articles_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Research Topics
        $researchTopics = ResearchTopic::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->having('articles_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Administration Methods
        $methods = AdministrationMethod::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where('status', 'Active')
            ->having('articles_count', '>', 0)
            ->orderBy('name')
            ->get();

        // Biomarkers
        $biomarkers = BioSub::withCount(['articleBiomarkers' => function ($q) {
            $q->whereHas('article', function ($subQ) {
                $subQ->where('status', 'Verified');
            });
        }])
            ->where('status', 'Approved')
            ->having('article_biomarkers_count', '>', 0)
            ->with('categories')
            ->orderBy('name')
            ->get();

        // Years
        $years = Article::where('status', 'Verified')
            ->join('article_publication_details', 'articles.id', '=', 'article_publication_details.article_id')
            ->whereNotNull('article_publication_details.year')
            ->select('article_publication_details.year')
            ->groupBy('article_publication_details.year')
            ->orderBy('article_publication_details.year', 'desc')
            ->pluck('year');

        $authors = VerifiedAuthor::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->where(function ($q) {
                $q->whereNull('parent_id') // 1) parent_id is null
                ->orWhere(function ($q) {
                    $q->whereNotNull('parent_id') // 2) parent is not null
                    ->where('is_featured', 1);  //    and author is featured
                });
            })
            ->having('articles_count', '>', 0) // 3) keep previous logic
            ->orderBy('name')
            ->get();


        return response()->json([
            'status' => true,
            'filters' => [
                'species' => $speciesTree,
                'diseases' => $diseaseTree,
                'countries' => $countryTree,
                'organs' => $organs->take(5),
                'systems' => $systems->take(5),
                'studyTypes' => $studyTypes,
                'researchTopics' => $researchTopics->take(5),
                'administrationMethods' => $methods->take(5),
                'biomarkers' => $biomarkers->take(5),
                'years' => $years->take(5),
                'authors' => $authors->take(5), // Limit to top 100
            ],
        ]);
    }

    private function buildSpeciesTree($species)
    {
        $tree = [];
        $lookup = [];

        // First pass: create lookup
        foreach ($species as $item) {
            $lookup[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
                'count' => $item->articles_count,
                'children' => [],
            ];
        }

        // Second pass: build tree
        foreach ($species as $item) {
            if ($item->parent_id === null) {
                $tree[] = &$lookup[$item->id];
            } else {
                if (isset($lookup[$item->parent_id])) {
                    $lookup[$item->parent_id]['children'][] = &$lookup[$item->id];
                }
            }
        }

        return $tree;
    }

    protected function buildDiseaseTree($diseases)
    {
        $tree = [];
        $lookup = [];

        foreach ($diseases as $item) {
            $lookup[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
                'count' => $item->articles_count,
                'children' => [],
            ];
        }

        foreach ($diseases as $item) {
            if ($item->parent_id === null) {
                $tree[] = &$lookup[$item->id];
            } else {
                if (isset($lookup[$item->parent_id])) {
                    $lookup[$item->parent_id]['children'][] = &$lookup[$item->id];
                }
            }
        }

        return $tree;
    }

    private function buildCountryTree($countries)
    {
        $tree = [];
        $lookup = [];

        foreach ($countries as $item) {
            $lookup[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
                'count' => $item->articles_count,
                'children' => [],
            ];
        }

        foreach ($countries as $item) {
            if ($item->parent_id === null) {
                $tree[] = &$lookup[$item->id];
            } else {
                if (isset($lookup[$item->parent_id])) {
                    $lookup[$item->parent_id]['children'][] = &$lookup[$item->id];
                }
            }
        }

        return $tree;
    }

    // ============================================================================
    // BOT INTEGRATION
    // ============================================================================

    public function getBotResponse(Request $req)
    {
        if (! $req->has('url')) {
            return response()->json(['status' => false, 'message' => 'URL is missing']);
        }

        $apiUrl = 'http://192.158.232.113/extract';
        $postData = json_encode(['url' => $req->url]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            return response()->json(['status' => false, 'message' => 'cURL Error: '.curl_error($ch)]);
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            return response()->json(['status' => false, 'message' => "API error: HTTP $httpCode"]);
        }

        return response()->json(['status' => true, 'data' => json_decode($response, true)]);
    }

    public function getBotResponse2(Request $req)
    {
        return $this->getBotResponse($req);
    }

    public function getBotResponse3(Request $req)
    {
        return $this->getBotResponse($req);
    }

    public function pdfbot(Request $req)
    {
        // Validate the request
        if (! $req->hasFile('file')) {
            return response()->json(['status' => false, 'message' => 'File is missing'], 400);
        }

        $file = $req->file('file');

        if ($file->getClientOriginalExtension() !== 'pdf') {
            return response()->json(['status' => false, 'message' => 'Only PDF files are allowed'], 400);
        }

        $apiUrl = 'http://54.89.48.111:8000/upload';

        try {
            // Configure HTTP client with longer timeout and retry logic
            $response = Http::timeout(1000) // 5 minutes timeout
                ->retry(3, 5000) // Retry 3 times with 5 seconds between attempts
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Connection' => 'keep-alive',
                ])
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post($apiUrl);

            // Check for HTTP errors
            if ($response->failed()) {
                throw new \Exception('API Error: '.$response->body());
            }

            $rsp = $response->json('data');

            // Fix specieDetails → speciesDetails
            if (isset($rsp['articleGeneralData']['specieDetails']) && count($rsp['articleGeneralData']['specieDetails']) > 0) {
                $rsp['articleGeneralData']['speciesDetails'] = $rsp['articleGeneralData']['specieDetails'];
            }

            // Fix "In Vivo" → "in Vivo"
            if (isset($rsp['articleGeneralData']['studyType']) && count($rsp['articleGeneralData']['studyType']) > 0) {
                foreach ($rsp['articleGeneralData']['studyType'] as $key => $value) {
                    if ($value['name'] == 'In Vivo') {
                        $value['name'] = 'in Vivo';
                        $rsp['articleGeneralData']['studyType'][$key] = $value;
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => $response->json('message', 'File processed successfully'),
                'data' => $rsp,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('PDF Upload Connection Error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'The PDF processing service is taking longer than expected. Please try again later.',
                'error' => $e->getMessage(),
            ], 504); // Gateway Timeout

        } catch (\Exception $e) {
            Log::error('PDF Upload Error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to process PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function processAndSubmitPdf(Request $req)
    {
        // Validate request
        $validator = \Validator::make($req->all(), [
            'extracted_data' => 'required|array',
            'extracted_data.publicData' => 'required|array',
            'extracted_data.articleGeneralData' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $extractedData = $req->extracted_data;

            // Check for duplicate DOI or PMID
            if (! empty($extractedData['publicData']['doi'])) {
                $existingByDoi = Article::where('doi', $extractedData['publicData']['doi'])->first();
                if ($existingByDoi) {
                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Article with this DOI already exists',
                        'existing_article_id' => $existingByDoi->id,
                        'existing_mhid' => $existingByDoi->mhid,
                    ], 409);
                }
            }

            if (! empty($extractedData['publicData']['pmid'])) {
                $existingByPmid = Article::where('pmid', $extractedData['publicData']['pmid'])->first();
                if ($existingByPmid) {
                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Article with this PMID already exists',
                        'existing_article_id' => $existingByPmid->id,
                        'existing_mhid' => $existingByPmid->mhid,
                    ], 409);
                }
            }

            // Prepare data structure for transformation service
            $oldArticleData = (object) [
                'mhid' => $this->generateMhid(),
                'doi' => $extractedData['publicData']['doi'] ?? null,
                'pmid' => $extractedData['publicData']['pmid'] ?? null,
                'status' => 'Draft',
                'publicData' => $extractedData['publicData'],
                'articleGeneralData' => $extractedData['articleGeneralData'],
                'researcherData' => $extractedData['researcherData'] ?? [],
                'biomaker' => $extractedData['biomaker'] ?? [],
                'reviewer_id' => null,
                'verified_by' => null,
                'addedBy' => auth()->id() ?? 1,
                'is_trending' => false,
            ];

            // Transform to normalized structure - THIS SAVES TO ALL 54+ TABLES
            $article = $this->transformService->transformToNormalized($oldArticleData);

            // Track revision with source info
            ArticleRevision::create([
                'article_id' => $article->id,
                'changed_by' => auth()->id() ?? 1,
                'changes' => [
                    'action' => 'created_from_pdf',
                    'source' => 'PDF Upload',
                    'timestamp' => now()->toIso8601String(),
                    'user_id' => auth()->id() ?? 1,
                    'extracted_data_keys' => array_keys($extractedData),
                ],
                'created_at' => now(),
            ]);

            DB::commit();

            // Load all relationships for response
            $article = $article->fresh([
                'publicationDetail.journal',
                'publicationDetail.publisher',
                'authors' => function ($q) {
                    $q->orderBy('article_authors.author_order');
                },
                'keywords',
                'countries',
                'pdfFiles',
                'studyTypes',
                'studyCategories',
                'highlightInfo',
                'species.articleDetails',
                'organs',
                'systems',
                'diseases',
                'researchTopics',
                'timingTreatments',
                'outcomeTypes',
                'outcome',
                'studyDurations',
                'experimentalDesign.brand',
                'administrationMethods',
                'inhalationProtocols.species',
                'ingestionProtocols.species',
                'cellCultureProtocols',
                'topicalProtocols',
                'biomarkers.biomarker.categories',
                'biomarkers.changeDirection',
                'biomarkers.categories',
                'reviewer',
                'verifiedBy',
                'addedBy',
            ]);

            // Transform back to JSON format for backward compatibility
            $articleData = $this->transformService->transformToJson($article);

            return response()->json([
                'status' => true,
                'article' => $articleData,
                'article_id' => $article->id,
                'mhid' => $article->mhid,
                'message' => 'Article created successfully from PDF',
                'tables_populated' => [
                    'articles' => 1,
                    'article_publication_details' => 1,
                    'authors' => $article->authors->count(),
                    'keywords' => $article->keywords->count(),
                    'species' => $article->species->count(),
                    'diseases' => $article->diseases->count(),
                    'organs' => $article->organs->count(),
                    'systems' => $article->systems->count(),
                    'study_types' => $article->studyTypes->count(),
                    'research_topics' => $article->researchTopics->count(),
                    'administration_methods' => $article->administrationMethods->count(),
                    'biomarkers' => $article->biomarkers->count(),
                    'total_relationships' => $article->authors->count() +
                        $article->keywords->count() +
                        $article->species->count() +
                        $article->diseases->count() +
                        $article->organs->count() +
                        $article->systems->count() +
                        $article->studyTypes->count() +
                        $article->researchTopics->count() +
                        $article->administrationMethods->count() +
                        $article->biomarkers->count(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PDF article submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to create article from PDF',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function bulkArticleSubmit(Request $req)
    {
        // Validate that we have an array of articles
        if (! $req->has('articles') || ! is_array($req->articles)) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide an array of articles in the "articles" key',
            ], 400);
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $totalArticles = count($req->articles);

        Log::info("Starting bulk article submission for {$totalArticles} articles");

        // Loop through each article
        foreach ($req->articles as $index => $articleData) {
            $articleNumber = $index + 1;

            try {
                Log::info("Processing article {$articleNumber}/{$totalArticles}");

                // Create a new request with this article's data
                $singleArticleRequest = new Request($articleData);

                // Call your existing articleSubmit method
                $response = $this->articleSubmit($singleArticleRequest);

                // Get the response data
                $responseData = $response->getData(true);

                if ($responseData['status'] ?? false) {
                    $successCount++;
                    $results[] = [
                        'index' => $index,
                        'article_number' => $articleNumber,
                        'status' => 'success',
                        'mhid' => $responseData['article']['mhid'] ?? null,
                        'article_id' => $responseData['article']['id'] ?? null,
                        'title' => $responseData['article']['publicData']['title']['name'] ?? 'Untitled',
                        'doi' => $responseData['article']['doi'] ?? null,
                        'pmid' => $responseData['article']['pmid'] ?? null,
                    ];

                    Log::info("Article {$articleNumber} submitted successfully - MHID: ".($responseData['article']['mhid'] ?? 'N/A'));
                } else {
                    $failureCount++;
                    $results[] = [
                        'index' => $index,
                        'article_number' => $articleNumber,
                        'status' => 'failed',
                        'error' => $responseData['message'] ?? 'Unknown error',
                    ];

                    Log::error("Article {$articleNumber} failed: ".($responseData['message'] ?? 'Unknown error'));
                }

            } catch (\Exception $e) {
                $failureCount++;
                $results[] = [
                    'index' => $index,
                    'article_number' => $articleNumber,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::error("Article {$articleNumber} exception: ".$e->getMessage());
            }
        }

        Log::info("Bulk submission completed: {$successCount} success, {$failureCount} failed out of {$totalArticles}");

        return response()->json([
            'status' => true,
            'message' => "Processed {$totalArticles} articles: {$successCount} successful, {$failureCount} failed",
            'summary' => [
                'total' => $totalArticles,
                'successful' => $successCount,
                'failed' => $failureCount,
                'success_rate' => $totalArticles > 0 ? round(($successCount / $totalArticles) * 100, 2) : 0,
            ],
            'results' => $results,
        ]);
    }

    public function pdfUploadComplete(Request $req)
    {
        // Step 1: Extract data using pdfbot
        $extractResponse = $this->pdfbot($req);
        $extractData = json_decode($extractResponse->content(), true);

        if (! $extractData['status']) {
            return $extractResponse;
        }

        // Step 2: Create article with extracted data
        $submitRequest = new Request([
            'extracted_data' => $extractData['data'],
        ]);

        // Merge auth from original request
        $submitRequest->setUserResolver($req->getUserResolver());

        return $this->processAndSubmitPdf($submitRequest);
    }

    private function extractPdfData($pdfUrl)
    {
        try {
            $apiUrl = 'http://192.158.232.113/pdf-extract';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pdf_url' => $pdfUrl]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($response, true);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('PDF extraction helper failed: '.$e->getMessage());

            return null;
        }
    }

    public function fetchArticleDetails(Request $request)
    {
        $pmid = $request->input('pmid');
        $doi = $request->input('doi');

        if (! $pmid && ! $doi) {
            return response()->json(['status' => false, 'message' => 'PMID or DOI required']);
        }

        // Fetch from external API
        try {
            $response = Http::get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi', [
                'db' => 'pubmed',
                'id' => $pmid,
                'retmode' => 'json',
            ]);

            $data = $response->json();

            return response()->json(['status' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    // ============================================================================
    // FILE UPLOAD
    // ============================================================================

    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            try {
                $uploadedFile = Cloudinary::upload($request->file('file')->getRealPath());
                $url = $uploadedFile->getSecurePath();

                return response()->json([
                    'status' => true,
                    'url' => $url,
                    'message' => 'File uploaded successfully',
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Upload failed: '.$e->getMessage(),
                ], 500);
            }
        }

        return response()->json(['status' => false, 'message' => 'No file provided'], 400);
    }

    public function getPMID(Request $req, $pmid)
    {
        $exists = Article::where('pmid', $pmid)->exists();

        return response()->json([
            'status' => true,
            'exists' => $exists,
        ]);
    }

    // ============================================================================
    // CONTINUE IN NEXT MESSAGE DUE TO LENGTH...
    // ============================================================================
    // ============================================================================
    // CONTINUING ArticleController.php - MASTER DATA MANAGEMENT
    // ============================================================================

    /**
     * SPECIES MANAGEMENT
     */
    public function AddSpecie(Request $req)
    {
        $species = Species::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'species' => $species,
            'message' => 'Species added successfully',
        ]);
    }

    public function EditSpecie(Request $req, $spid)
    {
        $species = Species::findOrFail($spid);
        $oldName = $species->name;

        $species->name = $req->name;
        if ($req->has('parent_id')) {
            $species->parent_id = $req->parent_id;
        }
        $species->save();

        return response()->json([
            'status' => true,
            'species' => $species,
            'message' => 'Species updated successfully',
        ]);
    }

    public function deleteSpecie(Request $req, $id)
    {
        $species = Species::findOrFail($id);

        // Check if has children
        if ($species->children()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete species with children. Please delete children first.',
            ], 400);
        }

        // Check if used in articles
        if ($species->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete species that is used in articles.',
            ], 400);
        }

        $species->delete();

        return response()->json([
            'status' => true,
            'message' => 'Species deleted successfully',
        ]);
    }

    public function ViewSpecie(Species $specie)
    {
        $specie->load(['parent', 'children', 'articles']);

        return response()->json([
            'status' => true,
            'species' => $specie,
        ]);
    }

    public function allSpecies()
    {
        $species = Species::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        // Build hierarchical structure
        $tree = $this->buildSpeciesTree($species);

        // Also get article IDs for each species
        $speciesWithArticles = Species::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($speciesWithArticles as $sp) {
            $articlesMap[$sp->id] = $sp->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'species' => $species,
            'tree' => $tree,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * DISEASE MANAGEMENT
     */
    public function addDisease(Request $req)
    {
        $disease = Disease::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'disease' => $disease,
            'message' => 'Disease added successfully',
        ]);
    }

    public function editDisease(Request $req, $id)
    {
        $disease = Disease::findOrFail($id);

        $disease->name = $req->name;
        if ($req->has('parent_id')) {
            $disease->parent_id = $req->parent_id;
        }
        $disease->save();

        return response()->json([
            'status' => true,
            'disease' => $disease,
            'message' => 'Disease updated successfully',
        ]);
    }

    public function deleteDisease(Request $req, $id)
    {
        $disease = Disease::findOrFail($id);

        if ($disease->children()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete disease with children.',
            ], 400);
        }

        if ($disease->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete disease that is used in articles.',
            ], 400);
        }

        $disease->delete();

        return response()->json([
            'status' => true,
            'message' => 'Disease deleted successfully',
        ]);
    }

    public function viewDisease(Disease $disease)
    {
        $disease->load(['parent', 'children', 'articles']);

        return response()->json([
            'status' => true,
            'disease' => $disease,
        ]);
    }

    public function allDiseases()
    {
        $diseases = Disease::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $tree = $this->buildDiseaseTree($diseases);

        $diseasesWithArticles = Disease::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($diseasesWithArticles as $dis) {
            $articlesMap[$dis->id] = $dis->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'diseases' => $diseases,
            'tree' => $tree,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * STUDY TYPE MANAGEMENT
     */
    public function addStudyType(Request $req)
    {
        $studyType = StudyType::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'studyType' => $studyType,
            'message' => 'Study type added successfully',
        ]);
    }

    public function editStudyType(Request $req, $id)
    {
        $studyType = StudyType::findOrFail($id);

        $studyType->name = $req->name;
        if ($req->has('parent_id')) {
            $studyType->parent_id = $req->parent_id;
        }
        $studyType->save();

        return response()->json([
            'status' => true,
            'studyType' => $studyType,
            'message' => 'Study type updated successfully',
        ]);
    }

    public function deleteStudyType(Request $req, $id)
    {
        $studyType = StudyType::findOrFail($id);

        if ($studyType->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete study type that is used in articles.',
            ], 400);
        }

        $studyType->delete();

        return response()->json([
            'status' => true,
            'message' => 'Study type deleted successfully',
        ]);
    }

    public function getStudyType()
    {
        $studyTypes = StudyType::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $studyTypesWithArticles = StudyType::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($studyTypesWithArticles as $st) {
            $articlesMap[$st->id] = $st->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'studyTypes' => $studyTypes,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * RESEARCH TOPIC MANAGEMENT
     */
    public function addResearchTopic(Request $req)
    {
        $topic = ResearchTopic::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'researchTopic' => $topic,
            'message' => 'Research topic added successfully',
        ]);
    }

    public function editResearchTopic(Request $req, $id)
    {
        $topic = ResearchTopic::findOrFail($id);

        $topic->name = $req->name;
        if ($req->has('parent_id')) {
            $topic->parent_id = $req->parent_id;
        }
        $topic->save();

        return response()->json([
            'status' => true,
            'researchTopic' => $topic,
            'message' => 'Research topic updated successfully',
        ]);
    }

    public function deleteResearchTopic(Request $req, $id)
    {
        $topic = ResearchTopic::findOrFail($id);

        if ($topic->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete research topic that is used in articles.',
            ], 400);
        }

        $topic->delete();

        return response()->json([
            'status' => true,
            'message' => 'Research topic deleted successfully',
        ]);
    }

    public function getResearchTopic()
    {
        $topics = ResearchTopic::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $topicsWithArticles = ResearchTopic::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($topicsWithArticles as $topic) {
            $articlesMap[$topic->id] = $topic->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'researchTopics' => $topics,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * ORGAN MANAGEMENT
     */
    public function addOrgans(Request $req)
    {
        $organ = Organ::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'organ' => $organ,
            'message' => 'Organ added successfully',
        ]);
    }

    public function editOrgans(Request $req, $id)
    {
        $organ = Organ::findOrFail($id);

        $organ->name = $req->name;
        if ($req->has('parent_id')) {
            $organ->parent_id = $req->parent_id;
        }
        $organ->save();

        return response()->json([
            'status' => true,
            'organ' => $organ,
            'message' => 'Organ updated successfully',
        ]);
    }

    public function deleteOrgan(Request $req, $id)
    {
        $organ = Organ::findOrFail($id);

        if ($organ->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete organ that is used in articles.',
            ], 400);
        }

        $organ->delete();

        return response()->json([
            'status' => true,
            'message' => 'Organ deleted successfully',
        ]);
    }

    public function getOrgans()
    {
        $organs = Organ::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $organsWithArticles = Organ::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($organsWithArticles as $organ) {
            $articlesMap[$organ->id] = $organ->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'organs' => $organs,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * SYSTEM MANAGEMENT
     */
    public function addSystem(Request $req)
    {
        $system = System::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'system' => $system,
            'message' => 'System added successfully',
        ]);
    }

    public function editSystem(Request $req, $id)
    {
        $system = System::findOrFail($id);

        $system->name = $req->name;
        if ($req->has('parent_id')) {
            $system->parent_id = $req->parent_id;
        }
        $system->save();

        return response()->json([
            'status' => true,
            'system' => $system,
            'message' => 'System updated successfully',
        ]);
    }

    public function deletesystems(Request $req, $id)
    {
        $system = System::findOrFail($id);

        if ($system->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete system that is used in articles.',
            ], 400);
        }

        $system->delete();

        return response()->json([
            'status' => true,
            'message' => 'System deleted successfully',
        ]);
    }

    public function getSystems()
    {
        $systems = System::withCount(['articles'])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $systemsWithArticles = System::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($systemsWithArticles as $system) {
            $articlesMap[$system->id] = $system->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'systems' => $systems,
            'articlesMap' => $articlesMap,
        ]);
    }

    /**
     * ADMINISTRATION METHODS MANAGEMENT
     */
    public function addMethods(Request $req)
    {
        $method = AdministrationMethod::create([
            'name' => $req->name,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'methods' => $method,
            'message' => 'Method added successfully',
        ]);
    }

    public function editMethods(Request $req, $id)
    {
        $method = AdministrationMethod::findOrFail($id);
        $method->name = $req->name;
        $method->save();

        return response()->json([
            'status' => true,
            'methods' => $method,
            'message' => 'Method updated successfully',
        ]);
    }

    public function deleteMethods(Request $req, $id)
    {
        $method = AdministrationMethod::findOrFail($id);

        if ($method->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete method that is used in articles.',
            ], 400);
        }

        $method->delete();

        return response()->json([
            'status' => true,
            'methods' => $method,
            'message' => 'Method deleted successfully',
        ]);
    }

    public function getMethods()
    {
        $methods = AdministrationMethod::withCount(['articles'])
            ->where('status', 'Active')
            ->orderBy('id', 'DESC')
            ->get();

        // Get detailed usage for each method
        $methodsWithDetails = [];
        foreach ($methods as $method) {
            $articles = $method->articles()->get();

            // Get species used with this method
            $speciesUsed = [];
            foreach ($articles as $article) {
                foreach ($article->species as $species) {
                    if (! isset($speciesUsed[$species->name])) {
                        $speciesUsed[$species->name] = 0;
                    }
                    $speciesUsed[$species->name]++;
                }
            }

            // Get co-occurring methods
            $coOccurringMethods = [];
            foreach ($articles as $article) {
                foreach ($article->administrationMethods as $coMethod) {
                    if ($coMethod->id !== $method->id) {
                        if (! isset($coOccurringMethods[$coMethod->name])) {
                            $coOccurringMethods[$coMethod->name] = 0;
                        }
                        $coOccurringMethods[$coMethod->name]++;
                    }
                }
            }

            $methodsWithDetails[] = [
                'id' => $method->id,
                'name' => $method->name,
                'article_count' => $method->articles_count,
                'species_used' => $speciesUsed,
                'co_occurring_methods' => $coOccurringMethods,
                'article_ids' => $articles->pluck('id')->toArray(),
            ];
        }

        return response()->json([
            'status' => true,
            'methods' => $methods,
            'detailed_usage' => $methodsWithDetails,
        ]);
    }

    // ============================================================================
    // BIOMARKER MANAGEMENT
    // ============================================================================

    public function updateMarkerFromFront(Request $req)
    {
        $biomarker = BioSub::findOrFail($req->id);
        $biomarker->name = $req->name;
        $biomarker->save();

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker,
            'message' => 'Biomarker updated successfully',
        ]);
    }

    public function getBiomakers()
    {
        $biomarkers = BioSub::with('categories')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'biomakers' => $biomarkers,
        ]);
    }

    public function editBioMaker(Request $req, $id)
    {
        $biomarker = BioSub::findOrFail($id);
        $biomarker->name = $req->name;
        $biomarker->status = $req->status ?? $biomarker->status;
        $biomarker->save();

        // Update categories if provided
        if ($req->has('categories')) {
            $categoryIds = BioCategory::whereIn('name', $req->categories)->pluck('id');
            BioBridge::where('sub_id', $biomarker->id)->delete();

            foreach ($categoryIds as $catId) {
                BioBridge::create([
                    'cat_id' => $catId,
                    'sub_id' => $biomarker->id,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker->load('categories'),
            'message' => 'Biomarker updated successfully',
        ]);
    }

    public function managedMakers(Request $req)
    {
        $query = BioSub::with('categories');

        if ($req->has('status')) {
            $query->where('status', $req->status);
        }

        $biomarkers = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'sub' => $biomarkers,
        ]);
    }

    public function RejectApproveMakers(Request $req)
    {
        $biomarker = BioSub::findOrFail($req->id);
        $biomarker->status = $req->status; // 'Approved', 'Pending', 'Deleted'
        $biomarker->save();

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker,
            'message' => 'Biomarker status updated successfully',
        ]);
    }

    public function BioSubWithCatList()
    {
        $biomarkers = BioSub::with('categories')
            ->orderBy('name')
            ->get();

        // Get usage count for each biomarker
        $biomarkersWithUsage = [];
        foreach ($biomarkers as $biomarker) {
            $articleCount = $biomarker->articleBiomarkers()
                ->whereHas('article')->count();

            $articleIds = $biomarker->articleBiomarkers()
                ->whereHas('article')
                ->pluck('article_id')
                ->toArray();

            $biomarkersWithUsage[] = [
                'id' => $biomarker->id,
                'name' => $biomarker->name,
                'parent_id' => $biomarker->parent_id,
                'categories' => $biomarker->categories->pluck('name')->toArray(),
                'article_count' => $articleCount,
                'article_ids' => $articleIds,
                'created_at' => $biomarker->created_at,
                'status' => $biomarker->status,
                'updated_at' => $biomarker->updated_at,
            ];
        }

        // Get category usage statistics
        $categories = BioCategory::withCount(['biomarkers', 'articleBiomarkers'])
            ->orderBy('name')
            ->get();

        // Find unmapped biomarkers
        $unmappedBiomarkers = BioSub::whereDoesntHave('categories')
            ->get();

        return response()->json([
            'status' => true,
            'sub' => $biomarkersWithUsage,
            'categories' => $categories,
            'unmapped_biomarkers' => $unmappedBiomarkers,
        ]);
    }

    public function addBioMarker(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'sub' => 'required|unique:bio_sub,name',
            'categoryName' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $biomarker = BioSub::create([
            'name' => $req->sub,
            'status' => 'Pending',
            'parent_id' => $req->parent_id ?? null,
        ]);

        // Add categories if provided
        if ($req->has('categoryName') && is_array($req->categoryName)) {
            $categoryIds = BioCategory::whereIn('name', $req->categoryName)->pluck('id');

            foreach ($categoryIds as $catId) {
                BioBridge::create([
                    'cat_id' => $catId,
                    'sub_id' => $biomarker->id,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker->load('categories'),
            'message' => 'Biomarker added successfully',
        ]);
    }

    public function editBioMarker(Request $req, $id)
    {
        $biomarker = BioSub::findOrFail($id);

        // Update biomarker details
        $biomarker->update([
            'name' => $req->name ?? $biomarker->name,
            'status' => $req->status ?? $biomarker->status,
            'parent_id' => $req->parent_id ?? $biomarker->parent_id,
        ]);

        // Update categories if provided
        if ($req->has('categories') && is_array($req->categories)) {
            // Delete existing category relationships
            BioBridge::where('sub_id', $biomarker->id)->delete();

            // Add new categories
            $categoryIds = BioCategory::whereIn('name', $req->categories)->pluck('id');

            foreach ($categoryIds as $catId) {
                BioBridge::create([
                    'cat_id' => $catId,
                    'sub_id' => $biomarker->id,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker->load('categories'),
            'message' => 'Biomarker updated successfully',
        ]);
    }

    public function viewMarker(Request $req, $id)
    {
        $biomarker = BioSub::with(['categories', 'articleBiomarkers.article.publicationDetail'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'biomarker' => $biomarker,
        ]);
    }

    // ============================================================================
    // ARTICLE SUBMISSION
    // ============================================================================

    public function newArticle(Request $req)
    {
        DB::beginTransaction();

        try {
            // Create portal article (legacy)
            $portalArticle = PortalArticle::create([
                'title' => $req->title,
                'authors' => $req->authors,
                'year' => $req->year,
                'country' => $req->country,
                'pmid' => $req->pmid,
                'doi' => $req->doi,
                'abstract' => $req->abstract,
                'publisher' => $req->publisher,
                'journal' => $req->journal,
                'volume' => $req->volume,
                'pages' => $req->pages,
                'outcome' => $req->outcome,
                'admin_approval' => 'Pending',
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'article' => $portalArticle,
                'message' => 'Article submitted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to submit article: '.$e->getMessage(),
            ], 500);
        }
    }

   public function articleSubmit(Request $req)
    {
        DB::beginTransaction();

        try {
            $isUpdate = $req->has('article_id') && $req->article_id;
            $article = null;

            if ($isUpdate) {
                $article = Article::with([
                    'inhalationProtocols',
                    'ingestionProtocols',
                    'cellCultureProtocols',
                    'topicalProtocols',
                    'species',
                    'administrationMethods',
                    'speciesDetails',
                    'publicationDetail',
                    'experimentalDesign',
                    // 'studyDuration',
                    'highlightInfo',
                    'outcomes',
                    'biomarkers',
                    'claims',
                    'pdfFiles' // ✅ ADD THIS
                ])->findOrFail($req->article_id);

            } else {
                $article = Article::create([
                    'mhid' => $this->generateMhid(),
                    'doi' => $req->publicData['doi']['name'] ?? null,
                    'pmid' => $req->publicData['pmid']['name'] ?? null,
                    'status' => 'Draft',
                    'added_by' => auth()->id() ?? 1,
                ]);
            }

            if ($req->has('publicData') || $req->has('articleGeneralData') ||
                $req->has('researcherData') || $req->has('biomaker')) {

                $oldArticleData = (object) [
                    'mhid' => $article->mhid,
                    'doi' => $article->doi,
                    'pmid' => $article->pmid,
                    'status' => $article->status,
                    'publicData' => $req->publicData ?? [],
                    'articleGeneralData' => $req->articleGeneralData ?? [],
                    'researcherData' => $req->researcherData ?? [],
                    'biomaker' => $req->biomaker ?? [],
                    'reviewer_id' => $article->reviewer_id,
                    'verified_by' => $article->verified_by,
                    'addedBy' => $article->added_by,
                    'is_trending' => $article->is_trending,
                ];

                if ($isUpdate) {
                    // ✅ Store important data before deletion
                    $articleId = $article->id;
                    $articleMhid = $article->mhid;

                    // Delete all protocol relationships
                    $article->inhalationProtocols()->delete();
                    $article->ingestionProtocols()->delete();
                    $article->cellCultureProtocols()->delete();
                    $article->topicalProtocols()->delete();
                    $article->speciesDetails()->delete();
                    $article->outcomes()->delete();
                    $article->biomarkers()->delete();
                    $article->claims()->delete();

                    // Detach many-to-many relationships
                    $article->species()->detach();
                    $article->administrationMethods()->detach();
                    $article->keywords()->detach();
                    $article->organs()->detach();
                    $article->systems()->detach();
                    $article->diseases()->detach();
                    $article->researchTopics()->detach();


                    // Delete one-to-one relationships
                    if ($article->publicationDetail) {
                        $article->publicationDetail->delete();
                    }
                    if ($article->experimentalDesign) {
                        $article->experimentalDesign->delete();
                    }
                    if ($article->studyDuration) {
                        $article->studyDuration->delete();
                    }
                    if ($article->highlightInfo) {
                        $article->highlightInfo->delete();
                    }

                    // ✅ DON'T DELETE THE ARTICLE!
                    // Pass existing article to transformation
                    $article = $this->transformService->transformToNormalized($oldArticleData, $article);

                    // ✅ VERIFY article ID didn't change
                    if ($article->id !== $articleId) {
                        throw new \Exception("Critical error: Article ID changed during update from {$articleId} to {$article->id}");
                    }



                } else {
                    // CREATE MODE
                    $article->delete();
                    $article = $this->transformService->transformToNormalized($oldArticleData);


                }
            }

            // Track revision
            ArticleRevision::create([
                'article_id' => $article->id,
                'changed_by' => auth()->id() ?? 1,
                'changes' => $req->all(),
                'revision_type' => $isUpdate ? 'update' : 'create',
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'article' => $this->transformService->transformToJson($article),
                'message' => $isUpdate ? 'Article updated successfully' : 'Article created successfully',
                'is_update' => $isUpdate,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();



            return response()->json([
                'status' => false,
                'message' => 'Failed to save article: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function getPArticles()
    {
        $articles = PortalArticle::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'articles' => $articles,
        ]);
    }

    public function getData(Request $request)
    {
        $article = Article::with([
            'publicationDetail',
            'authors',
            'keywords',
            'species',
            'diseases',
            'cellCultureProtocols',
        ])->findOrFail($request->article_id);

        return response()->json([
            'status' => true,
            'data' => $this->transformService->transformToJson($article),
        ]);
    }

    public function updateAllArticlesWithMhid()
    {
        $articles = Article::whereNull('mhid')->get();

        foreach ($articles as $article) {
            $article->mhid = $this->generateMhid();
            $article->save();
        }

        return response()->json([
            'status' => true,
            'message' => "Updated {$articles->count()} articles with MHID",
        ]);
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    private function generateMhid()
    {
        $lastArticle = Article::orderBy('id', 'desc')->first();
        $nextNumber = $lastArticle ? $lastArticle->id + 1 : 1;

        return 'MHID-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Compare articles - returns two arrays: with and without the specified filter
     * POST /api/compare-articles-by-filter
     */
    public function compareArticlesByFilter(Request $req)
    {
        // Get parameters
        $orderBy = $req->input('orderBy', 'DESC');

        // Determine filter type and IDs
        $filterType = null;
        $filterIds = [];

        $possibleFilters = [
            'studyTypes' => 'studyTypes',
            'species' => 'species',
            'diseases' => 'diseases',
            'organs' => 'organs',
            'systems' => 'systems',
            'researchTopics' => 'researchTopics',
            'countries' => 'countries',
            'administrationMethods' => 'administrationMethods',
            'authors' => 'authors',
            'keywords' => 'keywords',
            'biomarkers' => 'biomarkers',
            'biomarkerCategories' => 'biomarkerCategories',
        ];

        // Find which filter was provided
        foreach ($possibleFilters as $key => $value) {
            if ($req->has($key) && ! empty($req->$key)) {
                $filterType = $value;
                $filterIds = is_array($req->$key) ? $req->$key : [$req->$key];
                break;
            }
        }

        if (! $filterType) {
            return response()->json([
                'status' => false,
                'message' => 'No filter provided. Please provide at least one filter.',
            ], 400);
        }

        // Get articles WITH the filter
        $articlesWithFilter = $this->getArticlesByFilter($filterType, $filterIds, true, $orderBy);

        // Get articles WITHOUT the filter
        $articlesWithoutFilter = $this->getArticlesByFilter($filterType, $filterIds, false, $orderBy);

        return response()->json([
            'status' => true,
            'message' => 'Articles comparison fetched successfully',
            'data' => [
                'including' => $articlesWithFilter,
                'excluding' => $articlesWithoutFilter,
                'filter_applied' => [
                    'type' => $filterType,
                    'ids' => $filterIds,
                ],
                'counts' => [
                    'with_filter' => count($articlesWithFilter),
                    'without_filter' => count($articlesWithoutFilter),
                    'total' => count($articlesWithFilter) + count($articlesWithoutFilter),
                ],
            ],
        ]);
    }

    /**
     * Get articles by filter (with or without)
     */
    private function getArticlesByFilter($filterType, $filterIds, $hasFilter = true, $orderBy = 'DESC')
    {
        $query = Article::with(['publicationDetail'])
            ->orderBy('created_at', $orderBy);

        switch ($filterType) {
            case 'studyTypes':
                if ($hasFilter) {
                    $query->whereHas('studyTypes', function ($q) use ($filterIds) {
                        $q->whereIn('study_types.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('studyTypes', function ($q) use ($filterIds) {
                        $q->whereIn('study_types.id', $filterIds);
                    });
                }
                break;

            case 'species':
                if ($hasFilter) {
                    $query->whereHas('species', function ($q) use ($filterIds) {
                        $q->whereIn('species.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('species', function ($q) use ($filterIds) {
                        $q->whereIn('species.id', $filterIds);
                    });
                }
                break;

            case 'diseases':
                if ($hasFilter) {
                    $query->whereHas('diseases', function ($q) use ($filterIds) {
                        $q->whereIn('diseases.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('diseases', function ($q) use ($filterIds) {
                        $q->whereIn('diseases.id', $filterIds);
                    });
                }
                break;

            case 'organs':
                if ($hasFilter) {
                    $query->whereHas('organs', function ($q) use ($filterIds) {
                        $q->whereIn('organs.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('organs', function ($q) use ($filterIds) {
                        $q->whereIn('organs.id', $filterIds);
                    });
                }
                break;

            case 'systems':
                if ($hasFilter) {
                    $query->whereHas('systems', function ($q) use ($filterIds) {
                        $q->whereIn('systems.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('systems', function ($q) use ($filterIds) {
                        $q->whereIn('systems.id', $filterIds);
                    });
                }
                break;

            case 'researchTopics':
                if ($hasFilter) {
                    $query->whereHas('researchTopics', function ($q) use ($filterIds) {
                        $q->whereIn('research_topics.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('researchTopics', function ($q) use ($filterIds) {
                        $q->whereIn('research_topics.id', $filterIds);
                    });
                }
                break;

            case 'countries':
                if ($hasFilter) {
                    $query->whereHas('countries', function ($q) use ($filterIds) {
                        $q->whereIn('countries.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('countries', function ($q) use ($filterIds) {
                        $q->whereIn('countries.id', $filterIds);
                    });
                }
                break;

            case 'administrationMethods':
                if ($hasFilter) {
                    $query->whereHas('administrationMethods', function ($q) use ($filterIds) {
                        $q->whereIn('administration_methods.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('administrationMethods', function ($q) use ($filterIds) {
                        $q->whereIn('administration_methods.id', $filterIds);
                    });
                }
                break;

            case 'authors':
                if ($hasFilter) {
                    $query->whereHas('authors', function ($q) use ($filterIds) {
                        $q->whereIn('verified_authors.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('authors', function ($q) use ($filterIds) {
                        $q->whereIn('verified_authors.id', $filterIds);
                    });
                }
                break;

            case 'keywords':
                if ($hasFilter) {
                    $query->whereHas('keywords', function ($q) use ($filterIds) {
                        $q->whereIn('keywords.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('keywords', function ($q) use ($filterIds) {
                        $q->whereIn('keywords.id', $filterIds);
                    });
                }
                break;

            case 'biomarkers':
                if ($hasFilter) {
                    $query->whereHas('biomarkers.biomarker', function ($q) use ($filterIds) {
                        $q->whereIn('bio_sub.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('biomarkers.biomarker', function ($q) use ($filterIds) {
                        $q->whereIn('bio_sub.id', $filterIds);
                    });
                }
                break;

            case 'biomarkerCategories':
                if ($hasFilter) {
                    $query->whereHas('biomarkers.category', function ($q) use ($filterIds) {
                        $q->whereIn('bio_categories.id', $filterIds);
                    });
                } else {
                    $query->whereDoesntHave('biomarkers.category', function ($q) use ($filterIds) {
                        $q->whereIn('bio_categories.id', $filterIds);
                    });
                }
                break;
        }

        // Get all articles and format
        return $query->get()->map(function ($article) {
            return [
                'id' => $article->id,
                'mhid' => $article->mhid,
                'doi' => $article->doi,
                'title' => $article->publicationDetail->title ?? null,
                'status' => $article->status,
            ];
        })->values()->toArray();
    }
}
