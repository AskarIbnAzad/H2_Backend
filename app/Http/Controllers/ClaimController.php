<?php
// ============================================================================
// FILE: app/Http/Controllers/ClaimController.php
// COMPLETE REWRITE - Using Normalized Structure
// ============================================================================

namespace App\Http\Controllers;

use App\Models\ArticleClaim;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClaimController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);

        $paginator = ArticleClaim::with(['article.publicationDetail', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        // Status count summary
        $statusCounts = ArticleClaim::selectRaw('
            COUNT(CASE WHEN status = "Approved" THEN 1 END) as approved,
            COUNT(CASE WHEN status = "Rejected" THEN 1 END) as rejected,
            COUNT(CASE WHEN status = "Pending" THEN 1 END) as pending,
            COUNT(*) as total
        ')->first();

        $transformed = $paginator->getCollection()->map(function ($item) {
            $article = $item->article;
            $title = $article && $article->publicationDetail 
                ? $article->publicationDetail->title 
                : null;

            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'full_name' => $item->full_name,
                'email' => $item->email,
                'affiliation' => $item->affiliation,
                'position_title' => $item->position_title,
                'orcid_id' => $item->orcid_id,
                'explanation' => $item->explanation,
                'supporting_evidence' => $item->supporting_evidence,
                'status' => $item->status,
                'created_at' => $item->created_at->toIso8601String(),
                'article' => $article ? [
                    'id' => $article->id,
                    'mhid' => $article->mhid,
                    'title' => $title,
                    'doi' => $article->doi,
                    'pmid' => $article->pmid,
                ] : null,
            ];
        });

        return response()->json([
            'status' => true,
            'claims' => $transformed,
            'status_counts' => $statusCounts,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'final_article_id' => 'required|exists:articles,id',
            'explanation' => 'nullable|string',
            'supporting_evidence' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $claim = ArticleClaim::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'affiliation' => $request->affiliation,
            'position_title' => $request->position_title,
            'orcid_id' => $request->orcid_id,
            'explanation' => $request->explanation,
            'supporting_evidence' => $request->supporting_evidence,
            'final_article_id' => $request->final_article_id,
            'status' => 'Pending',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'status' => true,
            'claim' => $claim->load('article'),
            'message' => 'Claim submitted successfully'
        ], 201);
    }

    public function show($id)
    {
        $claim = ArticleClaim::with(['article.publicationDetail', 'user'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'claim' => $claim
        ]);
    }

    public function update(Request $request, $id)
    {
        $claim = ArticleClaim::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:Pending,Approved,Rejected',
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $claim->fill($request->only([
            'full_name', 'email', 'affiliation', 'position_title', 
            'orcid_id', 'explanation', 'supporting_evidence', 'status'
        ]));
        
        $claim->save();

        return response()->json([
            'status' => true,
            'claim' => $claim,
            'message' => 'Claim updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $claim = ArticleClaim::findOrFail($id);
        $claim->delete();

        return response()->json([
            'status' => true,
            'message' => 'Claim deleted successfully'
        ]);
    }

    public function approve($id)
    {
        $claim = ArticleClaim::findOrFail($id);
        $claim->status = 'Approved';
        $claim->save();

        return response()->json([
            'status' => true,
            'claim' => $claim,
            'message' => 'Claim approved successfully'
        ]);
    }

    public function reject($id)
    {
        $claim = ArticleClaim::findOrFail($id);
        $claim->status = 'Rejected';
        $claim->save();

        return response()->json([
            'status' => true,
            'claim' => $claim,
            'message' => 'Claim rejected'
        ]);
    }
}