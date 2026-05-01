<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Article;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    // List user's folders (with optional article IDs)
    public function index(Request $request)
    {
        $folders = Folder::where('user_id', auth()->id())
            ->with('articles:id,folder_id')
            ->get()
            ->map(function ($folder) use ($request) {
                $data = [
                    'id'            => $folder->id,
                    'name'          => $folder->name,
                    'article_count' => $folder->articles->count(),
                ];
                if ($request->boolean('with_articles')) {
                    $data['articles'] = $folder->articles->pluck('id')->toArray();
                }
                return $data;
            });

        return response()->json(['status' => true, 'data' => $folders]);
    }

    // Create a new folder
    public function store(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);

        $folder = Folder::create([
            'user_id' => auth()->id(),
            'name'    => $validated['name'],
        ]);

        return response()->json(['status' => true, 'data' => $folder], 201);
    }

    // Delete a folder (sets folder_id on its articles to null)
    public function destroy(Folder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }
        $folder->delete();
        return response()->json(['status' => true, 'message' => 'Folder deleted']);
    }

    // Paginated articles inside a folder
    public function articles(Request $request, Folder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $page = $request->input('page', 1);
        $perPage = 20;

        $query = Article::with([
            'publicationDetail.journal', 'authors', 'species', 'diseases',
            'organs', 'systems', 'studyTypes', 'researchTopics', 'countries',
            'administrationMethods', 'cellCultureProtocols'  // your usual relations
        ])
            ->where('folder_id', $folder->id)
            ->where('status', 'Verified')
            ->orderBy('created_at', 'DESC');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $transformed = $paginator->getCollection()->map(function ($article) {
            return (new \App\Services\TransformService())->transformToJson($article); // adjust
        });

        return response()->json([
            'status'       => true,
            'articles'     => $transformed,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ]);
    }

    // Add article to folder (move it here, removing from any previous folder)
    public function addArticle(Request $request, Folder $folder)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $articleId = $request->input('article_id');
        $article = Article::findOrFail($articleId);

        // One article can only be in one folder – just update folder_id
        $article->folder_id = $folder->id;
        $article->save();

        return response()->json(['status' => true, 'message' => 'Article saved']);
    }

    // Remove article from folder
    public function removeArticle(Folder $folder, $articleId)
    {
        if ($folder->user_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
        }

        $article = Article::where('id', $articleId)->where('folder_id', $folder->id)->first();
        if ($article) {
            $article->folder_id = null;
            $article->save();
        }

        return response()->json(['status' => true, 'message' => 'Article removed']);
    }
}
