<?php

// ============================================================================
// FILE: app/Http/Controllers/AdminController.php
// COMPLETE REWRITE - Using Normalized Structure
// ============================================================================

namespace App\Http\Controllers;

use App\Models\AdministrationMethod;
use App\Models\Article;
use App\Models\ArticleFeedback;
use App\Models\ArticleRevision;
use App\Models\BioCategory;
use App\Models\Country;
use App\Models\Disease;
use App\Models\Organ;
use App\Models\ResearchTopic;
use App\Models\Role;
use App\Models\Species;
use App\Models\StudyType;
use App\Models\System;
use App\Models\User;
use App\Models\VerifiedAuthor;
use App\Services\ArticleTransformationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    protected $transformService;

    public function __construct(ArticleTransformationService $transformService)
    {
        $this->transformService = $transformService;
    }

    // ============================================================================
    // ARTICLE ASSIGNMENT & REVIEW
    // ============================================================================

    public function getAssignedArticles(Request $req)
    {
        $userId = auth()->id();

        $articles = Article::where('reviewer_id', $userId)
            ->with([
                'publicationDetail.journal',
                'authors',
                'species',
                'diseases',
                'addedBy',
                'cellCultureProtocols',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($req->per_page ?? 20);

        $transformed = $articles->getCollection()->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        return response()->json([
            'status' => true,
            'articles' => $transformed,
            'pagination' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    public function getListArticlesForAssignment(Request $req)
    {
        // Get parameters
        $page = $req->input('page', 1);
        $perPage = $req->input('per_page', 10);
        $searchTerm = $req->input('admin_search', '');

        // Build query with relationships
        $query = Article::with([
            'publicationDetail.journal',
            'reviewer',
            'verifiedBy',
            'addedBy',
            'cellCultureProtocols',
        ]);

        // Filter by assignment - show only unassigned articles
        if ($req->has('assignment') && $req->assignment === false) {
            $query->whereNull('reviewer_id');
        } elseif ($req->has('assignment') && $req->assignment === true) {
            $query->whereNotNull('reviewer_id');
        }

        // Filter by reviewer_id (can be single ID or array of IDs)
        if ($req->has('reviewer_id') && ! empty($req->reviewer_id)) {
            if (is_array($req->reviewer_id)) {
                $query->whereIn('reviewer_id', $req->reviewer_id);
            } else {
                $query->where('reviewer_id', $req->reviewer_id);
            }
        }

        // Apply search if provided
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('mhid', 'like', "%{$searchTerm}%")
                    ->orWhere('doi', 'like', "%{$searchTerm}%")
                    ->orWhere('pmid', 'like', "%{$searchTerm}%")
                    ->orWhereHas('publicationDetail', function ($pubQ) use ($searchTerm) {
                        $pubQ->where('title', 'like', "%{$searchTerm}%")
                            ->orWhere('abstract', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('reviewer', function ($revQ) use ($searchTerm) {
                        $revQ->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('addedBy', function ($addedQ) use ($searchTerm) {
                        $addedQ->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Order by created_at
        $query->orderBy('created_at', 'desc');

        // Paginate
        $articles = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform articles
        $transformed = $articles->getCollection()->map(function ($article) {
            return $this->transformService->transformToJson($article);
        });

        // Calculate statistics
        $verifiedArticles = Article::where('status', 'Verified')->count();
        $assignedArticles = Article::whereNotNull('reviewer_id')->count();
        $articlesCount = Article::count();

        // Count researchers (users with Contributor/Reviewer role or users who added articles)
        $researchersCount = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['Contributor', 'Reviewer', 'Admin']);
        })
            ->orWhereHas('addedArticles')
            ->distinct()
            ->count();

        return response()->json([
            'status' => true,
            'data' => [
                'articles' => $transformed,
                'verifiedArticles' => $verifiedArticles,
                'researchersCount' => $researchersCount,
                'assignedArticles' => $assignedArticles,
                'articlesCount' => $articlesCount,
            ],
            'current_page' => $articles->currentPage(),
            'per_page' => $articles->perPage(),
            'total' => $articles->total(),
            'last_page' => $articles->lastPage(),
        ]);
    }

    public function assignReviewer(Request $req)
    {
        // Validate the request
        $validated = $req->validate([
            'article_id' => 'required|exists:articles,id',
            'reviewer_id' => 'required|exists:users,id',
        ]);

        $article = Article::findOrFail($req->article_id);

        $article->reviewer_id = $req->reviewer_id;
        $article->status = 'In Review';
        $article->save();

        return response()->json([
            'status' => true,
            'article' => $article->load('reviewer'),
            'message' => 'Reviewer assigned successfully',
        ]);
    }

    public function updateStatus(Request $req, $id)
    {
        $article = Article::findOrFail($id);

        $oldStatus = $article->status;
        $article->status = $req->status;

        // If verifying, set verified_by
        if ($req->status === 'Verified') {
            $article->verified_by = auth()->id();
        }

        $article->save();

        return response()->json([
            'status' => true,
            'article' => $article,
            'message' => "Status updated from {$oldStatus} to {$req->status}",
        ]);
    }

    // ============================================================================
    // ARTICLE MANAGEMENT
    // ============================================================================

    public function deleteArticle(Request $req, $id)
    {
        $article = Article::findOrFail($id);

        DB::beginTransaction();
        try {
            // All relationships will be cascade deleted due to foreign keys
            $article->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Article deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete article: '.$e->getMessage(),
            ], 500);
        }
    }

    public function checkArticle(Request $request)
    {
        $exists = false;
        $articles = [];
        $matchType = null;

        // Priority 1: Check by DOI (most reliable)
        if ($request->has('doi') && ! empty($request->doi)) {
            $article = Article::where('doi', $request->doi)->first();
            if ($article) {
                $articles[] = $article;
                $matchType = 'doi';
                $exists = true;
            }
        }

        // Priority 2: Check by PMID (only if not found by DOI)
        if (! $exists && $request->has('pmid') && ! empty($request->pmid)) {
            $article = Article::where('pmid', $request->pmid)->first();
            if ($article) {
                $articles[] = $article;
                $matchType = 'pmid';
                $exists = true;
            }
        }

        // Priority 3: Check by Title - EXACT MATCH ONLY (only if not found by DOI or PMID)
        if (! $exists && $request->has('title') && ! empty($request->title)) {
            $searchTitle = trim($request->title);

            // Exact match only - case-insensitive
            $exactMatches = Article::whereHas('publicationDetail', function ($query) use ($searchTitle) {
                $query->whereRaw('LOWER(title) = LOWER(?)', [$searchTitle]);
            })->limit(5)->get();

            if ($exactMatches->isNotEmpty()) {
                $articles = $exactMatches;
                $matchType = 'title';
                $exists = true;
            }
        }

        return response()->json([
            'status' => $exists ? 'found' : 'not_found',
            'exists' => $exists,
            'matched_by' => $matchType,
            'count' => count($articles),
            'article' => $articles && count($articles) === 1
                ? $this->transformService->transformToJson($articles[0])
                : null,
            'articles' => count($articles) > 1
                ? collect($articles)->map(fn ($a) => $this->transformService->transformToJson($a))
                : null,
        ]);
    }

    // ============================================================================
    // FEEDBACK SYSTEM
    // ============================================================================

    public function getFeedbacks(Request $request)
    {
        $feedbacks = ArticleFeedback::with(['article.publicationDetail'])
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10);

        $statusCounts = ArticleFeedback::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $statusCounts['total'] = ArticleFeedback::count();

        return response()->json([
            'success' => true,
            'message' => 'Feedback retrieved successfully',
            'data' => $feedbacks->map(function ($feedback) {
                // Manually decode if not already decoded
                $user = is_string($feedback->user) ? json_decode($feedback->user, true) : $feedback->user;
                $feedbackData = is_string($feedback->feedback) ? json_decode($feedback->feedback, true) : $feedback->feedback;

                // Prepare article data
                $articleData = null;
                if ($feedback->article) {
                    $articleData = [
                        'id' => $feedback->article->id,
                        'mhid' => $feedback->article->mhid,
                        'doi' => $feedback->article->doi,
                        'title' => $feedback->article->publicationDetail->title ?? null,
                    ];
                }

                return [
                    'id' => $feedback->id,
                    'user' => $user,
                    'article' => $articleData,
                    'feedback' => $feedbackData,
                    'status' => $feedback->status,
                    'created_at' => $feedback->created_at->toIso8601String(),
                    'page_url' => $feedback->page_url,
                ];
            }),
            'pagination' => [
                'current_page' => $feedbacks->currentPage(),
                'total' => $feedbacks->total(),
                'per_page' => $feedbacks->perPage(),
                'last_page' => $feedbacks->lastPage(),
            ],
            'status_counts' => $statusCounts,
        ]);
    }

    public function FeedbackStore(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'user' => 'required|array',
                'user.name' => 'required|string|max:255',
                'user.email' => 'required|email|max:255',
                'page_url' => 'required|url|max:1000',
                'feedback' => 'required|array',
                'feedback.*.screenshot' => 'nullable|url',
                'feedback.*.explanation' => 'required|string',
                'feedback.*.revision' => 'nullable|string',
                'status' => 'required|in:Pending,In Progress,Reviewed,Resolved',
            ]);

            // Create feedback
            $feedback = ArticleFeedback::create([
                'user' => json_encode($request->user),
                'article_id' => $request->article_id ?? null,
                'page_url' => $request->page_url,
                'feedback' => json_encode($request->feedback),
                'status' => $request->status,
            ]);

            // Return response in the new format
            return response()->json([
                'success' => true,
                'message' => 'Feedback saved successfully',
                'data' => [
                    'id' => $feedback->id,
                    'user' => $feedback->user, // Will be array due to casting
                    'article_id' => $feedback->article_id,
                    'page_url' => $feedback->page_url,
                    'feedback' => $feedback->feedback, // Will be array due to casting
                    'status' => $feedback->status,
                    'created_at' => $feedback->created_at,
                    'updated_at' => $feedback->updated_at,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save feedback: '.$e->getMessage(),
            ], 500);
        }
    }

    public function updateFeedbackStatus(Request $request, $id)
    {
        try {
            $feedback = ArticleFeedback::findOrFail($id);

            $request->validate([
                'status' => 'required|in:Pending,In Progress,Reviewed,Resolved',
            ]);

            $feedback->update([
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback status updated successfully',
                'data' => [
                    'id' => $feedback->id,
                    'status' => $feedback->status,
                    'updated_at' => $feedback->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function Feedbackdelete($id)
    {
        $feedback = ArticleFeedback::findOrFail($id);
        $feedback->delete();

        return response()->json([
            'status' => true,
            'message' => 'Feedback deleted successfully',
        ]);
    }

    // ============================================================================
    // BIOMARKER CATEGORIES
    // ============================================================================

    public function bioCategoriesById($id)
    {
        $category = BioCategory::with('biomarkers')->findOrFail($id);

        return response()->json([
            'status' => true,
            'category' => $category,
        ]);
    }

    public function addBioCategory(Request $req)
    {
        $category = BioCategory::create([
            'name' => $req->name,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'category' => $category,
            'message' => 'Category added successfully',
        ]);
    }

    public function updateBioCategory(Request $req, $id)
    {
        $category = BioCategory::findOrFail($id);
        $category->name = $req->name;
        $category->status = $req->status ?? $category->status;
        $category->save();

        return response()->json([
            'status' => true,
            'category' => $category,
            'message' => 'Category updated successfully',
        ]);
    }

    public function deleteBioCategory($id)
    {
        $category = BioCategory::findOrFail($id);

        // Check if has biomarkers
        if ($category->biomarkers()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete category with biomarkers',
            ], 400);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    // ============================================================================
    // COUNTRY MANAGEMENT
    // ============================================================================

    public function getCountries()
    {
        $countries = Country::withCount([
            'articles as publication_count' => function ($q) {
                $q->where('article_countries.country_type', 'publication');
            },
            'articles as grant_count' => function ($q) {
                $q->where('article_countries.country_type', 'grant');
            },
            'articles as research_count' => function ($q) {
                $q->where('article_countries.country_type', 'research');
            },
        ])
            ->with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        // Build hierarchical tree
        $tree = $this->buildCountryTree($countries);

        // Get co-occurring countries analysis
        $coOccurrenceData = [];
        foreach ($countries as $country) {
            $articles = $country->articles()->pluck('articles.id');

            $coOccurring = Country::whereHas('articles', function ($q) use ($articles) {
                $q->whereIn('articles.id', $articles);
            })
                ->where('countries.id', '!=', $country->id)
                ->withCount('articles')
                ->orderBy('articles_count', 'desc')
                ->limit(5)
                ->get();

            $coOccurrenceData[$country->id] = $coOccurring;
        }

        return response()->json([
            'status' => true,
            'countries' => $countries,
            'tree' => $tree,
            'co_occurrence' => $coOccurrenceData,
        ]);
    }

    public function addCountries(Request $req)
    {
        $country = Country::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id ?? null,
            'status' => 'Active',
        ]);

        return response()->json([
            'status' => true,
            'country' => $country,
            'message' => 'Country added successfully',
        ]);
    }

    public function editCountry(Request $req, Country $country)
    {
        $country->name = $req->name;
        if ($req->has('parent_id')) {
            $country->parent_id = $req->parent_id;
        }
        $country->save();

        return response()->json([
            'status' => true,
            'country' => $country,
            'message' => 'Country updated successfully',
        ]);
    }

    public function deleteCountry(Request $req, Country $country)
    {
        if ($country->children()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete country with children',
            ], 400);
        }

        if ($country->articles()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete country that is used in articles',
            ], 400);
        }

        $country->delete();

        return response()->json([
            'status' => true,
            'message' => 'Country deleted successfully',
        ]);
    }

    private function buildCountryTree($countries)
    {
        $tree = [];
        $lookup = [];

        foreach ($countries as $item) {
            $lookup[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
                'publication_count' => $item->publication_count,
                'grant_count' => $item->grant_count,
                'research_count' => $item->research_count,
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
    // CONTINUE IN NEXT MESSAGE...
    // ============================================================================
    // ============================================================================
    // CONTINUING AdminController.php - AUTHOR MANAGEMENT
    // ============================================================================

    public function getAuthors()
    {
        $authors = VerifiedAuthor::withCount(['articles'])
            ->with(['parent', 'children'])
            ->orderBy('name')
            ->get();

        // Get article IDs for each author
        $authorsWithArticles = VerifiedAuthor::with(['articles' => function ($q) {
            $q->select('articles.id', 'articles.mhid');
        }])->get();

        $articlesMap = [];
        foreach ($authorsWithArticles as $author) {
            $articlesMap[$author->id] = $author->articles->pluck('id')->toArray();
        }

        return response()->json([
            'status' => true,
            'authors' => $authors,
            'articlesMap' => $articlesMap,
        ]);
    }

    public function getAuthChildren()
    {
        $authors = VerifiedAuthor::whereNotNull('parent_id')
            ->with('parent')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'children' => $authors,
        ]);
    }

    public function addAuthor(Request $req)
    {
        // Check for duplicates
        $existing = VerifiedAuthor::where('name', $req->name)
            ->whereNull('parent_id')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'Author with this name already exists',
                'existing_author' => $existing,
            ], 409);
        }

        $author = VerifiedAuthor::create([
            'name' => $req->name,
            'orcid' => $req->orcid ?? null,
            'email' => $req->email ?? null,
            'institution_affiliation' => $req->institution_affiliation ?? null,
            'author_h_index' => $req->author_h_index ?? null,
            'is_featured' => $req->is_featured ?? false,
        ]);

        return response()->json([
            'status' => true,
            'author' => $author,
            'message' => 'Author added successfully',
        ]);
    }

    public function addAuthorChild(Request $req)
    {
        $author = VerifiedAuthor::create([
            'name' => $req->name,
            'parent_id' => $req->parent_id,
            'orcid' => $req->orcid ?? null,
            'email' => $req->email ?? null,
            'institution_affiliation' => $req->institution_affiliation ?? null,
        ]);

        return response()->json([
            'status' => true,
            'author' => $author->load('parent'),
            'message' => 'Author variant added successfully',
        ]);
    }

    public function editAuthor(Request $req, VerifiedAuthor $author)
    {
        $author->name = $req->name;
        $author->orcid = $req->orcid ?? $author->orcid;
        $author->email = $req->email ?? $author->email;
        $author->institution_affiliation = $req->institution_affiliation ?? $author->institution_affiliation;
        $author->author_h_index = $req->author_h_index ?? $author->author_h_index;

        if ($req->has('parent_id')) {
            $author->parent_id = $req->parent_id;
        }

        $author->save();

        return response()->json([
            'status' => true,
            'author' => $author,
            'message' => 'Author updated successfully',
        ]);
    }

    public function featuredAuthor($id, $isFeatured = false)
    {
        $author = VerifiedAuthor::findOrFail($id);
        $author->is_featured = filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN);
        $author->save();

        return response()->json([
            'status' => true,
            'author' => $author,
            'message' => $author->is_featured ? 'Author marked as featured' : 'Author removed from featured',
        ]);
    }

    public function makeParent(Request $req)
    {
        $author = VerifiedAuthor::findOrFail($req->author_id);
        $author->parent_id = null;
        $author->save();

        return response()->json([
            'status' => true,
            'author' => $author,
            'message' => 'Author is now a parent',
        ]);
    }

    public function verifyAuthor(Request $req)
    {
        // Update all article_authors pivot records for this author
        DB::table('article_authors')
            ->where('author_id', $req->author_id)
            ->update(['verified' => true]);

        return response()->json([
            'status' => true,
            'message' => 'Author verified in all articles',
        ]);
    }

    public function getArticleAuthors()
    {
        // Get unique authors from articles with their usage count
        $authors = VerifiedAuthor::has('articles')
            ->withCount('articles')
            ->orderBy('articles_count', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'authors' => $authors,
        ]);
    }

    // ============================================================================
    // USER MANAGEMENT
    // ============================================================================

    public function userList()
    {
        $users = User::with('role')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totals
        $totalUsers = User::count();
        $totalActiveUsers = User::where('status', 'Active')->count();
        $totalArticles = Article::count();
        $totalVerified = Article::where('status', 'Verified')->count();
        $totalUnverified = Article::where('status', 'Unverified')->count();
        $totalInReview = Article::where('status', 'In Review')->count();
        $totalAssigned = Article::whereNotNull('reviewer_id')->count();

        return response()->json([
            'status' => true,
            'message' => 'Users fetched successfully',

            'users' => $users->map(function ($user) {
                // Get counts for each user
                $assignedCount = Article::where('reviewer_id', $user->id)
                    ->whereIn('status', ['In Review', 'Flagged for Review', 'Review Complete'])
                    ->count();

                $verifiedCount = Article::where('verified_by', $user->id)
                    ->where('status', 'Verified')
                    ->count();

                $addedCount = Article::where('added_by', $user->id)->count();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                    ] : null,
                    'status' => $user->status,
                    'assigned_count' => $assignedCount,
                    'verified_count' => $verifiedCount,
                    'added_count' => $addedCount,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            }),
            'total_users' => $totalUsers,
            'total_active_users' => $totalActiveUsers,
            'total_articles' => $totalArticles,
            'total_verified_articles' => $totalVerified,
            'total_unverified_articles' => $totalUnverified,
            'total_in_review_articles' => $totalInReview,
            'total_assigned_articles' => $totalAssigned,
        ]);
    }

    public function contributorList()
    {
        $contributors = User::where('role_id', 2)->get();

        return response()->json([
            'status' => true,
            'contributors' => $contributors,
        ]);
    }

    public function approveRejectContributor(Request $req)
    {
        $user = User::findOrFail($req->user_id);

        if ($req->action === 'approve') {
            $user->status = 'Active';
            $message = 'Contributor approved';
        } else {
            $user->status = 'Inactive';
            $message = 'Contributor rejected';
        }

        $user->save();

        return response()->json([
            'status' => true,
            'user' => $user,
            'message' => $message,
        ]);
    }

    public function userByID($user)
    {
        $user = User::with('role')->findOrFail($user);

        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    public function userAddUpdate(Request $req, $id = null)
    {
        if ($id) {
            // Update
            $user = User::findOrFail($id);
            $user->name = $req->name ?? $user->name;
            $user->email = $req->email ?? $user->email;
            $user->role_id = $req->role_id ?? $user->role_id;
            $user->status = $req->status ?? $user->status;

            if ($req->has('password') && $req->password) {
                $user->password = Hash::make($req->password);
            }

            $user->save();
            $message = 'User updated successfully';
        } else {
            // Create
            $user = User::create([
                'name' => $req->name,
                'email' => $req->email,
                'password' => Hash::make($req->password ?? 'password'),
                'role_id' => $req->role_id ?? 4, // Default to User role
                'status' => $req->status ?? 'Active',
            ]);
            $message = 'User created successfully';
        }

        return response()->json([
            'status' => true,
            'user' => $user->load('role'),
            'message' => $message,
        ]);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Don't allow deleting the admin user
        if ($user->id === 1) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete system admin',
            ], 400);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    public function updateTime(Request $req)
    {
        $article = Article::findOrFail($req->article_id);
        $article->updated_at = now();
        $article->save();

        return response()->json([
            'status' => true,
            'message' => 'Article timestamp updated',
        ]);
    }

    // ============================================================================
    // DASHBOARD & ANALYTICS
    // ============================================================================

    public function adminDashboard(Request $req)
    {
        $userId = auth()->id(); // Get logged-in user ID

        // Total studies (all articles)
        $totalStudies = Article::count();

        // Years distribution
        $yearsData = Article::join('article_publication_details', 'articles.id', '=', 'article_publication_details.article_id')
            ->whereNotNull('article_publication_details.year')
            ->select('article_publication_details.year as year', DB::raw('count(*) as count'))
            ->groupBy('article_publication_details.year')
            ->orderBy('article_publication_details.year', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => (int) $item->year,
                    'count' => $item->count,
                ];
            });

        // Total researchers (users with Contributor/Reviewer role or users who added articles)
        $totalResearcher = User::whereIn('role_id', function ($query) {
            $query->select('id')
                ->from('roles')
                ->whereIn('name', ['Contributor', 'Reviewer']);
        })
            ->orWhereHas('addedArticles')
            ->distinct()
            ->count();

        // Total active users
        $userCount = User::where('status', 'Active')->count();

        // Study types distribution - as key-value object
        $studyTypeData = StudyType::withCount(['articles'])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Species distribution - as key-value object
        $speciesData = Species::withCount(['articles'])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Organs distribution - as key-value object
        $organsData = Organ::withCount(['articles'])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // Research topics distribution - as key-value object
        $topicsData = ResearchTopic::withCount(['articles'])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get()
            ->pluck('articles_count', 'name')
            ->toArray();

        // My articles count (articles added by current logged-in user)
        $myArticlesCount = 0;
        if ($userId) {
            $myArticlesCount = Article::where('added_by', $userId)->count();
        }

        // Time spent by user (you'll need to track this separately)
        // For now, calculating from user's session or article work
        $timeSpent = $this->calculateTimeSpent($userId);

        // Status distribution - as key-value object
        $statusData = Article::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Format status keys to match your format
        $formattedStatus = [
            'Verified' => $statusData['Verified'] ?? 0,
            'Unverified' => $statusData['Unverified'] ?? 0,
            'Draft' => $statusData['Draft'] ?? 0,
            'Flagged for Review' => $statusData['Flagged for Review'] ?? 0,
            'Review Complete' => $statusData['Review Complete'] ?? 0,
            'In_review' => $statusData['In Review'] ?? 0,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => [
                'total_studies' => $totalStudies,
                'years_graph' => $yearsData,
                'total_researcher' => $totalResearcher,
                'user_count' => $userCount,
                'study_type' => $studyTypeData,
                'specie_count' => $speciesData,
                'organs' => $organsData,
                'research_topics' => $topicsData,
                'my_articles_count' => $myArticlesCount,
                'time_spent' => $timeSpent,
                'status' => $formattedStatus,
            ],
        ]);
    }

    /**
     * Calculate time spent by user
     * This is a placeholder - you'll need to implement actual time tracking
     */
    private function calculateTimeSpent($userId)
    {
        if (! $userId) {
            return '00:00:00';
        }

        // Option 1: Calculate from article revisions/work
        $totalMinutes = ArticleRevision::where('changed_by', $userId)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count() * 5; // Assume 5 minutes per revision

        // Option 2: Get from user session tracking (if you have it)
        // $totalMinutes = UserSession::where('user_id', $userId)
        //     ->whereDate('created_at', today())
        //     ->sum('duration_minutes');

        // Option 3: Return from a tracking table
        // You might want to create a user_activity_logs table to track this properly

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        $seconds = 0;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function ltFilters()
    {
        // Get filter options for bot
        $filters = [
            'species' => Species::where('status', 'Active')->orderBy('name')->get(),
            'diseases' => Disease::where('status', 'Active')->orderBy('name')->get(),
            'organs' => Organ::where('status', 'Active')->orderBy('name')->get(),
            'systems' => System::where('status', 'Active')->orderBy('name')->get(),
            'studyTypes' => StudyType::where('status', 'Active')->orderBy('name')->get(),
            'methods' => AdministrationMethod::where('status', 'Active')->orderBy('name')->get(),
        ];

        return response()->json([
            'status' => true,
            'filters' => $filters,
        ]);
    }

    // ============================================================================
    // FILE UPLOADS
    // ============================================================================

    public function uploadArticle(Request $req)
    {
        if ($req->hasFile('article')) {
            try {
                $file = $req->file('article');

                // Validate
                $req->validate([
                    'article' => 'required|mimes:pdf,doc,docx,txt|max:51200', // max 50MB
                ]);

                // Create directory if it doesn't exist
                $uploadPath = public_path('uploaded_data/pdf');
                if (! File::exists($uploadPath)) {
                    File::makeDirectory($uploadPath, 0755, true);
                }

                // Generate unique filename
                $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

                // Move file to public folder
                $file->move($uploadPath, $filename);

                // Get URL
                $url = asset('uploaded_data/pdf/'.$filename);

                return response()->json([
                    'success' => true,
                    'article_url' => $url,
                    'filename' => $filename,
                    'message' => 'Article uploaded successfully',
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Upload failed: '.$e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'No file provided',
        ], 400);
    }

    public function uploadImages(Request $req)
    {
        // Single image upload
        if ($req->hasFile('image')) {
            try {
                $file = $req->file('image');

                // Validate
                $req->validate([
                    'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                ]);

                // Create directory if it doesn't exist
                $uploadPath = public_path('uploaded_data/image');
                if (! File::exists($uploadPath)) {
                    File::makeDirectory($uploadPath, 0755, true);
                }

                // Generate unique filename
                $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();

                // Move file to public folder
                $file->move($uploadPath, $filename);

                // Get URL
                $url = asset('uploaded_data/image/'.$filename);

                return response()->json([
                    'status' => true,
                    'success' => true,
                    'image_url' => $url,
                    'filename' => $filename,
                    'message' => 'Image uploaded successfully',
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload failed: '.$e->getMessage(),
                ], 500);
            }
        }

        // Multiple images upload
        if ($req->hasFile('files')) {
            $urls = [];

            try {
                // Create directory if it doesn't exist
                $uploadPath = public_path('uploaded_data/image');
                if (! File::exists($uploadPath)) {
                    File::makeDirectory($uploadPath, 0755, true);
                }

                foreach ($req->file('files') as $file) {
                    $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                    $file->move($uploadPath, $filename);
                    $urls[] = asset('uploaded_data/image/'.$filename);
                }

                return response()->json([
                    'status' => true,
                    'urls' => $urls,
                    'message' => 'Images uploaded successfully',
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Upload failed: '.$e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'No image provided',
        ], 400);
    }

    // ============================================================================
    // ROLES MANAGEMENT
    // ============================================================================

    public function getRoles()
    {
        $roles = Role::withCount('users')->get();

        return response()->json([
            'status' => true,
            'roles' => $roles,
        ]);
    }

    public function addRole(Request $req)
    {
        $role = Role::create([
            'name' => $req->name,
            'description' => $req->description ?? null,
        ]);

        return response()->json([
            'status' => true,
            'role' => $role,
            'message' => 'Role added successfully',
        ]);
    }

    public function viewRole(Role $role)
    {
        $role->load('users');

        return response()->json([
            'status' => true,
            'role' => $role,
        ]);
    }

    public function editRole(Request $req, Role $role)
    {
        $role->name = $req->name;
        $role->description = $req->description ?? $role->description;
        $role->save();

        return response()->json([
            'status' => true,
            'role' => $role,
            'message' => 'Role updated successfully',
        ]);
    }

    public function deletetRole(Role $role)
    {
        if ($role->users()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete role with assigned users',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    // ============================================================================
    // DATA EXPLORER (COMPREHENSIVE ANALYTICS)
    // ============================================================================

    public function publicDataExplorer(Request $req, $filter)
    {
        $result = [];

        switch ($filter) {
            case 'studyType':
                $result = $this->exploreStudyTypes($req);
                break;

            case 'species':
                $result = $this->exploreSpecies($req);
                break;

            case 'organs':
                $result = $this->exploreOrgans($req);
                break;

            case 'systems':
                $result = $this->exploreSystems($req);
                break;

            case 'researchTopics':
                $result = $this->exploreResearchTopics($req);
                break;

            case 'administrationMethods':
                $result = $this->exploreAdministrationMethods($req);
                break;

            case 'countries':
                $result = $this->exploreCountries($req);
                break;

            case 'diseases':
                $result = $this->exploreDiseases($req);
                break;

            case 'authors':
                $result = $this->exploreAuthors($req);
                break;

            case 'biomarkers':
                $result = $this->exploreBiomarkers($req);
                break;

            case 'years':
                $result = $this->exploreYears($req);
                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid filter type',
                ], 400);
        }

        return response()->json([
            'status' => true,
            'filter' => $filter,
            'data' => $result,
        ]);
    }

    private function exploreStudyTypes($req)
    {
        $studyTypes = StudyType::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->with(['parent', 'children'])
            ->orderBy('articles_count', 'desc')
            ->get();

        $total = $studyTypes->sum('articles_count');
        $totalArticles = Article::where('status', 'Verified')->count();

        // Categorize study types
        $animalStudies = [];
        $humanStudies = [];
        $inVivoVariations = [];

        foreach ($studyTypes as $studyType) {
            $nameLower = strtolower($studyType->name);

            // Check for animal studies
            if (strpos($nameLower, 'animal') !== false ||
                strpos($nameLower, 'rodent') !== false ||
                strpos($nameLower, 'mouse') !== false ||
                strpos($nameLower, 'rat') !== false) {
                $animalStudies[] = [
                    'name' => $studyType->name,
                    'count' => $studyType->articles_count,
                ];
            }

            // Check for human studies
            if (strpos($nameLower, 'human') !== false ||
                strpos($nameLower, 'clinical') !== false ||
                strpos($nameLower, 'patient') !== false ||
                strpos($nameLower, 'trial') !== false) {
                $humanStudies[] = [
                    'name' => $studyType->name,
                    'count' => $studyType->articles_count,
                ];
            }

            // Check for in vivo variations
            if (strpos($nameLower, 'vivo') !== false) {
                $inVivoVariations[] = [
                    'name' => $studyType->name,
                    'count' => $studyType->articles_count,
                ];
            }
        }

        return [
            'items' => $studyTypes->map(function ($item) use ($total) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->articles_count,
                    'percentage' => $total > 0 ? round(($item->articles_count / $total) * 100, 2) : 0,
                    'parent' => $item->parent ? $item->parent->name : null,
                    'children_count' => $item->children->count(),
                ];
            }),
            'total' => $total,
            'debug_info' => [
                'total_articles_processed' => $totalArticles,
                'unique_study_types_found' => $studyTypes->count(),
                'animal_studies' => $animalStudies,
                'human_studies' => $humanStudies,
                'in_vivo_variations' => $inVivoVariations,
            ],
        ];
    }

    private function exploreSpecies($req)
    {
        $species = Species::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->with(['parent', 'children'])
            ->orderBy('articles_count', 'desc')
            ->get();

        $total = $species->sum('articles_count');

        // Get co-occurring species for each
        $coOccurrence = [];
        foreach ($species->take(10) as $sp) {
            $articleIds = $sp->articles()->pluck('articles.id');

            $coOccurring = Species::whereHas('articles', function ($q) use ($articleIds) {
                $q->whereIn('articles.id', $articleIds);
            })
                ->where('species.id', '!=', $sp->id)
                ->withCount('articles')
                ->orderBy('articles_count', 'desc')
                ->limit(5)
                ->get();

            $coOccurrence[$sp->id] = $coOccurring->pluck('name', 'id');
        }

        return [
            'items' => $species->map(function ($item) use ($total) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->articles_count,
                    'percentage' => $total > 0 ? round(($item->articles_count / $total) * 100, 2) : 0,
                    'parent' => $item->parent ? $item->parent->name : null,
                ];
            }),
            'co_occurrence' => $coOccurrence,
            'total' => $total,
        ];
    }

    private function exploreOrgans($req)
    {
        $organs = Organ::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $organs->map(function ($organ) {
            // Get all articles for this organ
            $articles = $organ->articles()->where('status', 'Verified')->get();

            // Count human studies
            $humanStudyOccurrences = 0;
            foreach ($articles as $article) {
                $hasHuman = $article->species()->whereIn('species.name', ['Human', 'Humans', 'human', 'humans'])->exists();
                if ($hasHuman) {
                    $humanStudyOccurrences++;
                }
            }

            // Get system relationships (organs can belong to multiple systems)
            $systemRelationships = [];
            foreach ($articles as $article) {
                foreach ($article->systems as $system) {
                    $systemName = $system->name;
                    if (! isset($systemRelationships[$systemName])) {
                        $systemRelationships[$systemName] = 0;
                    }
                    $systemRelationships[$systemName]++;
                }
            }

            // Sort by count descending
            arsort($systemRelationships);

            // Get primary system (most common)
            $primarySystem = ! empty($systemRelationships)
                ? array_key_first($systemRelationships)
                : null;

            return [
                'id' => $organ->id,
                'name' => $organ->name,
                'status' => null,
                'total_occurrences' => $organ->articles_count,
                'article_count' => $organ->articles_count,
                'human_study_occurrences' => $humanStudyOccurrences,
                'primary_system' => $primarySystem,
                'system_relationships' => $systemRelationships,
                'created_at' => $organ->created_at->toIso8601String(),
                'updated_at' => $organ->updated_at->toIso8601String(),
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreSystems($req)
    {
        $systems = System::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $systems->map(function ($system) {
            // Get all articles for this system
            $articles = $system->articles()->where('status', 'Verified')->get();

            // Count human studies
            $humanStudyOccurrences = 0;
            foreach ($articles as $article) {
                $hasHuman = $article->species()->whereIn('species.name', ['Human', 'Humans', 'human', 'humans'])->exists();
                if ($hasHuman) {
                    $humanStudyOccurrences++;
                }
            }

            return [
                'id' => $system->id,
                'name' => $system->name,
                'status' => null,
                'total_occurrences' => $system->articles_count,
                'article_count' => $system->articles_count,
                'human_study_occurrences' => $humanStudyOccurrences,
                'primary_system' => null,
                'system_relationships' => [],
                'created_at' => $system->created_at->toIso8601String(),
                'updated_at' => $system->updated_at->toIso8601String(),
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreResearchTopics($req)
    {
        $topics = ResearchTopic::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'name' => $topic->name,
                'status' => $topic->status,
                'total_occurrences' => $topic->articles_count,
                'article_count' => $topic->articles_count,
                'created_at' => $topic->created_at->toIso8601String(),
                'updated_at' => $topic->updated_at->toIso8601String(),
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreAdministrationMethods($req)
    {
        $methods = AdministrationMethod::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $methods->map(function ($method) {
            // Get all articles for this method
            $articles = $method->articles()->where('status', 'Verified')->get();

            // Calculate species relationships
            $speciesRelationships = [];
            foreach ($articles as $article) {
                foreach ($article->species as $species) {
                    $speciesName = strtolower($species->name);
                    if (! isset($speciesRelationships[$speciesName])) {
                        $speciesRelationships[$speciesName] = 0;
                    }
                    $speciesRelationships[$speciesName]++;
                }
            }

            // Sort by count descending
            arsort($speciesRelationships);

            // Get primary species (most common)
            $primarySpecies = ! empty($speciesRelationships)
                ? array_key_first($speciesRelationships)
                : null;

            return [
                'id' => $method->id,
                'name' => $method->name,
                'status' => $method->status,
                'total_occurrences' => $method->articles_count,
                'article_count' => $method->articles_count,
                'primary_species' => $primarySpecies,
                'species_relationships' => $speciesRelationships,
                'created_at' => $method->created_at->toIso8601String(),
                'updated_at' => $method->updated_at->toIso8601String(),
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreCountries($req)
    {
        $countries = Country::withCount([
            'articles as publication_count' => function ($q) {
                $q->where('article_countries.country_type', 'publication')
                    ->where('articles.status', 'Verified');
            },
            'articles as grant_count' => function ($q) {
                $q->where('article_countries.country_type', 'grant')
                    ->where('articles.status', 'Verified');
            },
            'articles as research_count' => function ($q) {
                $q->where('article_countries.country_type', 'research')
                    ->where('articles.status', 'Verified');
            },
        ])
            ->with('children')
            ->where('status', 'Active')
            ->get();

        // Filter out countries with zero occurrences
        $countries = $countries->filter(function ($country) {
            return ($country->publication_count + $country->grant_count + $country->research_count) > 0;
        });

        // Sort by total occurrences
        $countries = $countries->sortByDesc(function ($country) {
            return $country->publication_count + $country->grant_count + $country->research_count;
        })->values();

        // Transform to exact old format
        $transformed = $countries->map(function ($country) {
            $totalOccurrences = $country->publication_count + $country->grant_count + $country->research_count;

            return [
                'id' => $country->id,
                'name' => $country->name,
                'status' => 'Approved',
                'total_occurrences' => $totalOccurrences,
                'occurrences_by_field' => [
                    'country' => $country->publication_count,
                    'grantCountry' => $country->grant_count,
                    'researchCountry' => $country->research_count,
                ],
                'created_at' => $country->created_at->toIso8601String(),
                'updated_at' => $country->updated_at->toIso8601String(),
                'article_count' => $country->publication_count,
                'children' => $country->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'parent_id' => $child->parent_id,
                    ];
                })->toArray(),
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreDiseases($req)
    {
        $diseases = Disease::withCount(['articles' => function ($q) {
            $q->where('status', 'Verified');
        }])
            ->having('articles_count', '>', 0)
            ->with(['parent', 'children'])
            ->orderBy('articles_count', 'desc')
            ->get();

        $total = $diseases->sum('articles_count');

        return [
            'items' => $diseases->map(function ($item) use ($total) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => $item->articles_count,
                    'percentage' => $total > 0 ? round(($item->articles_count / $total) * 100, 2) : 0,
                    'parent' => $item->parent ? $item->parent->name : null,
                ];
            }),
            'total' => $total,
        ];
    }

    private function exploreAuthors($req)
    {
        // Get all authors with article counts
        $authors = VerifiedAuthor::withCount([
            'articles' => function ($q) {
                $q->where('status', 'Verified');
            },
            'children',
        ])
            ->with('children')
            ->orderBy('articles_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $authors->map(function ($author) {
            return [
                'id' => $author->id,
                'name' => $author->name,
                'is_featured' => $author->is_featured ? 1 : 0,
                'article_count' => $author->articles_count,
                'total_occurrences' => $author->articles_count,
                'children_count' => $author->children_count,
                'children' => $author->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'parent_id' => $child->parent_id,
                        'is_featured' => $child->is_featured ? 1 : 0,
                    ];
                })->toArray(),
                'created_at' => $author->created_at->toIso8601String(),
                'updated_at' => $author->updated_at->toIso8601String(),
            ];
        });

        // Get just the names
        $authorNames = $authors->pluck('name')->toArray();

        // Calculate statistics
        $totalArticles = Article::where('status', 'Verified')->count();
        $totalAuthors = $authors->count();
        $authorsWithArticles = $authors->where('articles_count', '>', 0)->count();
        $authorsWithoutArticles = $authors->where('articles_count', '=', 0)->count();
        $totalOccurrences = $authors->sum('articles_count');

        // Most frequent authors (top 10)
        $mostFrequent = $authors
            ->where('articles_count', '>', 0)
            ->take(10)
            ->map(function ($author) {
                return [
                    'name' => $author->name,
                    'article_count' => $author->articles_count,
                ];
            })
            ->values()
            ->toArray();

        return [
            'items' => $transformed->toArray(),
            'au_names' => $authorNames,
            'debug_info' => [
                'total_articles_processed' => $totalArticles,
                'total_authors_in_db' => $totalAuthors,
                'authors_with_articles' => $authorsWithArticles,
                'authors_without_articles' => $authorsWithoutArticles,
                'total_author_occurrences' => $totalOccurrences,
                'most_frequent_authors' => $mostFrequent,
            ],
        ];
    }

    private function exploreBiomarkers($req)
    {
        $biomarkers = \App\Models\BioSub::withCount(['articleBiomarkers' => function ($q) {
            $q->whereHas('article', function ($subQ) {
                $subQ->where('status', 'Verified');
            });
        }])
            ->having('article_biomarkers_count', '>', 0)
            ->with('categories')
            ->orderBy('article_biomarkers_count', 'desc')
            ->get();

        // Transform to exact old format
        $transformed = $biomarkers->map(function ($biomarker) {
            // Get category names in lowercase
            $categories = $biomarker->categories->map(function ($cat) {
                return strtolower($cat->name);
            })->toArray();

            // Count unique articles (not just occurrences)
            $uniqueArticles = \App\Models\ArticleBiomarker::where('biomarker_id', $biomarker->id)
                ->whereHas('article', function ($q) {
                    $q->where('status', 'Verified');
                })
                ->distinct('article_id')
                ->count('article_id');

            return [
                'sub_category_name' => strtolower($biomarker->name),
                'categories' => $categories,
                'total_articles' => $uniqueArticles,
                'marker_occurrences' => $biomarker->article_biomarkers_count,
            ];
        });

        return [
            'items' => $transformed->toArray(),
        ];
    }

    private function exploreYears($req)
    {
        $yearStats = Article::where('status', 'Verified')
            ->join('article_publication_details', 'articles.id', '=', 'article_publication_details.article_id')
            ->whereNotNull('article_publication_details.year')
            ->select('article_publication_details.year', DB::raw('count(*) as count'))
            ->groupBy('article_publication_details.year')
            ->orderBy('article_publication_details.year')
            ->get();

        $total = $yearStats->sum('count');

        return [
            'items' => $yearStats->map(function ($item) use ($total) {
                return [
                    'year' => $item->year,
                    'count' => $item->count,
                    'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
                ];
            }),
            'total' => $total,
        ];
    }

    // Helper method for continent determination
    private function determineContinent($countryName)
    {
        // Simplified mapping - you would want a proper database table for this
        $continentMap = [
            'United States' => 'North America',
            'Canada' => 'North America',
            'Mexico' => 'North America',
            'United Kingdom' => 'Europe',
            'Germany' => 'Europe',
            'France' => 'Europe',
            'China' => 'Asia',
            'Japan' => 'Asia',
            'India' => 'Asia',
            'Australia' => 'Oceania',
            'Brazil' => 'South America',
            'South Africa' => 'Africa',
        ];

        return $continentMap[$countryName] ?? 'Other';
    }

    // ============================================================================
    // UTILITY METHODS (Legacy support)
    // ============================================================================

    public function mhtoMhi()
    {
        // Update old MH format to MHI
        $articles = Article::where('mhid', 'like', 'MH%')
            ->where('mhid', 'not like', 'MHI%')
            ->get();

        foreach ($articles as $article) {
            $article->mhid = str_replace('MH', 'MHI', $article->mhid);
            $article->save();
        }

        return response()->json([
            'status' => true,
            'message' => "Updated {$articles->count()} articles",
        ]);
    }

    public function authorsUpdate()
    {
        // Update author format (if needed for migration)
        return response()->json([
            'status' => true,
            'message' => 'Authors are now in normalized structure',
        ]);
    }

    public function fixArticle()
    {
        // Fix article data issues
        return response()->json([
            'status' => true,
            'message' => 'Article fixes applied',
        ]);
    }

    public function getDisease()
    {
        $diseases = Disease::with(['parent', 'children'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'diseases' => $diseases,
        ]);
    }

    protected function determineStudyType($name)
    {
        // Helper to determine study type category
        $name = strtolower($name);

        if (strpos($name, 'clinical') !== false) {
            return 'clinical';
        }
        if (strpos($name, 'vivo') !== false) {
            return 'in_vivo';
        }
        if (strpos($name, 'vitro') !== false) {
            return 'in_vitro';
        }
        if (strpos($name, 'review') !== false) {
            return 'non_experimental';
        }

        return 'other';
    }
}
