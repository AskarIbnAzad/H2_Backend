<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrganDiseaseRelationController extends Controller
{
    /**
     * Get linked IDs for a given disease or organ.
     *
     * Request body: { type: "disease"|"organ", id: 123 }
     * Response: { linked_ids: [1, 5, 9] }
     */
    public function getRelations(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['disease', 'organ'])],
            'id'   => 'required|integer|exists:' . ($request->type === 'disease' ? 'diseases' : 'organs') . ',id',
        ]);

        $type = $validated['type'];
        $id   = $validated['id'];

        if ($type === 'disease') {
            // Return organ IDs linked to this disease
            $linkedIds = DB::table('disease_organ')
                ->where('disease_id', $id)
                ->pluck('organ_id')
                ->toArray();
        } else {
            // Return disease IDs linked to this organ
            $linkedIds = DB::table('disease_organ')
                ->where('organ_id', $id)
                ->pluck('disease_id')
                ->toArray();
        }

        return response()->json(['linked_ids' => $linkedIds]);
    }

    /**
     * Replace all links for a given disease or organ.
     *
     * Request body: {
     *   source_type: "disease"|"organ",
     *   source_id: 123,
     *   target_ids: [2, 5, 10]
     * }
     *
     * Logic: Delete all existing rows where the source entity appears,
     * then insert the new set of target IDs.
     */
    public function updateRelations(Request $request)
    {
        Log::info('Updating relations with payload: ' . json_encode($request->all()));
        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['disease', 'organ'])],
            'source_id'   => 'required|integer|exists:' . ($request->source_type === 'disease' ? 'diseases' : 'organs') . ',id',
            'target_ids'  => 'nullable|array',             // can be empty
            'target_ids.*'=> 'integer|exists:' . ($request->source_type === 'disease' ? 'organs' : 'diseases') . ',id',
        ]);

        $sourceType = $validated['source_type'];
        $sourceId   = $validated['source_id'];
        $targetIds  = $validated['target_ids'] ?? [];

        // Use a transaction to ensure atomicity
        DB::transaction(function () use ($sourceType, $sourceId, $targetIds) {
            if ($sourceType === 'disease') {
                // Remove all existing organ links for this disease
                DB::table('disease_organ')->where('disease_id', $sourceId)->delete();
                // Insert new links
                $inserts = array_map(fn($organId) => [
                    'disease_id' => $sourceId,
                    'organ_id'   => $organId,
                ], $targetIds);
                if (!empty($inserts)) {
                    DB::table('disease_organ')->insert($inserts);
                }
            } else {
                // source_type === 'organ'
                DB::table('disease_organ')->where('organ_id', $sourceId)->delete();
                $inserts = array_map(fn($diseaseId) => [
                    'disease_id' => $diseaseId,
                    'organ_id'   => $sourceId,
                ], $targetIds);
                if (!empty($inserts)) {
                    DB::table('disease_organ')->insert($inserts);
                }
            }
        });

        return response()->json(['message' => 'Relations updated successfully']);
    }
}
