<?php
// ============================================================================
// FILE: app/Http/Controllers/TutorialController.php
// Already Simple - No changes needed, just using proper namespace
// ============================================================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tutorial;
use Illuminate\Support\Facades\Validator;

class TutorialController extends Controller
{
    public function index()
    {
        return response()->json(Tutorial::all(), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tutorial = Tutorial::create($validator->validated());

        return response()->json($tutorial, 201);
    }

    public function show($id)
    {
        $tutorial = Tutorial::find($id);

        if (!$tutorial) {
            return response()->json(['message' => 'Tutorial not found'], 404);
        }

        return response()->json($tutorial, 200);
    }

    public function update(Request $request, $id)
    {
        $tutorial = Tutorial::find($id);

        if (!$tutorial) {
            return response()->json(['message' => 'Tutorial not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tutorial->update($validator->validated());

        return response()->json($tutorial, 200);
    }

    public function destroy($id)
    {
        $tutorial = Tutorial::find($id);

        if (!$tutorial) {
            return response()->json(['message' => 'Tutorial not found'], 404);
        }

        $tutorial->delete();

        return response()->json(['message' => 'Tutorial deleted'], 200);
    }
}