<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NavigationController extends Controller
{
    public function forPublic()
    {
        $items = NavigationItem::with([
            'featured' => function ($query) {
                $query->where('is_active', true);
            },
            'sections' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order');
            },
            'sections.sectionItems' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ])
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id'     => 'nullable|exists:navigation_items,id',
            'type'          => 'required|in:nav_item,featured,section,section_item',
            'name'          => 'nullable|string|max:100',
            'path'          => 'nullable|string|max:500',
            'description'   => 'nullable|string|max:500',
            'image'         => 'nullable|string|max:500',
            'has_mega_menu' => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = $request->type;
            $parentId = $request->parent_id;

            if ($type === 'nav_item' && $parentId) {
                $validator->errors()->add('parent_id', 'Nav item cannot have parent.');
            }

            if (in_array($type, ['featured', 'section']) && !$parentId) {
                $validator->errors()->add('parent_id', ucfirst($type) . ' must have a nav item parent.');
            }

            if ($type === 'section_item' && !$parentId) {
                $validator->errors()->add('parent_id', 'Section item must have a section parent.');
            }

            if ($parentId) {
                $parent = NavigationItem::find($parentId);

                if ($parent) {
                    if (in_array($type, ['featured', 'section']) && $parent->type !== 'nav_item') {
                        $validator->errors()->add('parent_id', ucfirst($type) . ' parent must be a nav item.');
                    }

                    if ($type === 'section_item' && $parent->type !== 'section') {
                        $validator->errors()->add('parent_id', 'Section item parent must be a section.');
                    }
                }
            }

            if ($type === 'featured' && $parentId) {
                $featuredExists = NavigationItem::where('parent_id', $parentId)
                    ->where('type', 'featured')
                    ->exists();

                if ($featuredExists) {
                    $validator->errors()->add('featured', 'This nav item already has a featured item.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $item = NavigationItem::create([
            'parent_id'     => $data['parent_id'] ?? null,
            'type'          => $data['type'],
            'name'          => $data['name'] ?? null,
            'path'          => $data['path'] ?? null,
            'description'   => $data['description'] ?? null,
            'image'         => $data['image'] ?? null,
            'has_mega_menu' => $data['has_mega_menu'] ?? false,
            'is_active'     => $data['is_active'] ?? true,
            'sort_order'    => $data['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Navigation item created.',
            'data'    => $item->fresh()->load(['featured', 'sections.sectionItems']),
        ], 201);
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

        $validator->after(function ($validator) use ($request, $item) {
            $type = $request->type ?? $item->type;
            $parentId = $request->has('parent_id') ? $request->parent_id : $item->parent_id;

            if ($parentId == $item->id) {
                $validator->errors()->add('parent_id', 'Item cannot be its own parent.');
            }

            if ($type === 'nav_item' && $parentId) {
                $validator->errors()->add('parent_id', 'Nav item cannot have parent.');
            }

            if (in_array($type, ['featured', 'section']) && !$parentId) {
                $validator->errors()->add('parent_id', ucfirst($type) . ' must have a nav item parent.');
            }

            if ($type === 'section_item' && !$parentId) {
                $validator->errors()->add('parent_id', 'Section item must have a section parent.');
            }

            if ($parentId) {
                $parent = NavigationItem::find($parentId);

                if ($parent) {
                    if (in_array($type, ['featured', 'section']) && $parent->type !== 'nav_item') {
                        $validator->errors()->add('parent_id', ucfirst($type) . ' parent must be a nav item.');
                    }

                    if ($type === 'section_item' && $parent->type !== 'section') {
                        $validator->errors()->add('parent_id', 'Section item parent must be a section.');
                    }
                }
            }

            if ($type === 'featured' && $parentId) {
                $featuredExists = NavigationItem::where('parent_id', $parentId)
                    ->where('type', 'featured')
                    ->where('id', '!=', $item->id)
                    ->exists();

                if ($featuredExists) {
                    $validator->errors()->add('featured', 'This nav item already has a featured item.');
                }
            }
        });

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

    public function destroy(int $id)
    {
        $item = NavigationItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted.',
        ]);
    }
}
