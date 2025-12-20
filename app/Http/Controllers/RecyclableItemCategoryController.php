<?php

namespace App\Http\Controllers;

use App\Models\RecyclableItemCategory;
use Illuminate\Http\Request;

class RecyclableItemCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(RecyclableItemCategory::all(), 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|integer|min:0',
        ]);

        $category = RecyclableItemCategory::create($validated);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        return response()->json($category, 200);
    }

    public function update(Request $request, $id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|integer|min:0',
        ]);

        $category->update($validated);

        return response()->json($category, 200);
    }

    public function destroy($id)
    {
        $category = RecyclableItemCategory::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.'], 200);
    }
}
