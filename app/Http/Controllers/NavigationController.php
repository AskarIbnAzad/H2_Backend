<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NavigationController extends Controller
{
    public function index()
    {
        $items = NavigationItem::with(['featured', 'sections.sectionItems'])
            ->whereNull('parent_id')
            ->where('type', 'nav_item')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $item = NavigationItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'parent_id'     => 'nullable|exists:navigation_items,id',
            'type'          => 'sometimes|in:nav_item,featured,section,section_item',
            'name'          => 'sometimes|nullable|string|max:100',
            'path'          => 'sometimes|nullable|string|max:500',
            'description'   => 'sometimes|nullable|string|max:500',
            'image'         => 'sometimes|nullable|string|max:500',
            'has_mega_menu' => 'sometimes|boolean',
            'is_active'     => 'sometimes|boolean',
            'sort_order'    => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $item->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Item updated.',
            'data'    => $item->fresh()->load(['featured', 'sections.sectionItems']),
        ]);
    }
}
