<?php

namespace App\Http\Controllers;

use App\Models\Disease;
use App\Models\Organ;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganDiseaseRelationController extends Controller
{
    /**
     * Get linked IDs for a given disease or organ.
     */
    public function getRelations(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['disease', 'organ'])],
            'id'   => 'required|integer',
        ]);

        $type = $validated['type'];
        $id   = $validated['id'];

        if ($type === 'disease') {
            $disease = Disease::findOrFail($id);
            $linkedIds = $disease->organs()->pluck('organs.id')->toArray();
        } else {
            $organ = Organ::findOrFail($id);
            $linkedIds = $organ->diseases()->pluck('diseases.id')->toArray();
        }

        return response()->json(['linked_ids' => $linkedIds]);
    }

    /**
     * Replace all links for a given disease or organ.
     */
    public function updateRelations(Request $request)
    {
        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['disease', 'organ'])],
            'source_id'   => 'required|integer',
            'target_ids'  => 'array',          // nullable by default
            'target_ids.*'=> 'integer',
        ]);

        $sourceType = $validated['source_type'];
        $sourceId   = $validated['source_id'];
        $targetIds  = $validated['target_ids'] ?? [];

        if ($sourceType === 'disease') {
            $disease = Disease::findOrFail($sourceId);
            $disease->organs()->sync($targetIds);
        } else {
            $organ = Organ::findOrFail($sourceId);
            $organ->diseases()->sync($targetIds);
        }

        return response()->json(['message' => 'Relations updated successfully']);
    }
}
