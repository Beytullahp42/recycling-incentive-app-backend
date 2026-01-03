<?php

namespace App\Http\Controllers;

use App\Models\RecyclableItemCategory;
use App\Models\RecyclableItem;
use Illuminate\Http\Request;

class RecyclableItemCategoryController extends Controller
{
    public function index()
    {
        return response()->json(RecyclableItemCategory::all(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:recyclable_item_categories,name',
            'value' => 'required|integer|min:0',
        ]);

        $category = RecyclableItemCategory::create($validated);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => __('messages.category.not_found')], 404);
        }

        return response()->json($category, 200);
    }

    public function update(Request $request, $id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => __('messages.category.not_found')], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:recyclable_item_categories,name,' . $id,
            'value' => 'sometimes|integer|min:0',
        ]);

        $category->update($validated);

        return response()->json($category, 200);
    }

    public function destroy($id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => __('messages.category.not_found')], 404);
        }

        if ($category->name === 'Uncategorized') {
            return response()->json(['message' => __('messages.category.uncategorized_delete_error')], 403);
        }

        $uncategorized = RecyclableItemCategory::where('name', 'Uncategorized')->first();

        if (!$uncategorized) {
            return response()->json(['message' => __('messages.category.critical_uncategorized_missing')], 500);
        }

        RecyclableItem::where('category_id', $category->id)->update(['category_id' => $uncategorized->id]);

        $category->delete();

        return response()->json(['message' => __('messages.category.deleted')], 200);
    }
}
