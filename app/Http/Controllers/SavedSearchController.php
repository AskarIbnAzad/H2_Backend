<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SavedSearchController extends Controller
{
    public function saveSearch(Request $request)
    {
        $name = $request->input('name');
        $payload = $request->input('data');

        if (!$payload || !is_array($payload)) {
            return response()->json([
                'message' => 'Invalid payload. Provide request_body (object) or data (object).'
            ], 422);
        }

        $payloadString = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $saved = SavedSearch::create([
            'user_id' => auth()->id(),
            'name' => $name,
            'search_data' => $payloadString,
        ]);

        return response()->json([
            'message' => 'Search saved successfully',
            'id' => $saved->id,
        ], 201);
    }

    public function getSavedSearches(Request $request)
    {
        $data = SavedSearch::where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function destroy($id)
    {
        $row = SavedSearch::where('user_id', auth()->id())->findOrFail($id);
        $row->delete();

        return response()->json(['status' => true]);
    }
}
