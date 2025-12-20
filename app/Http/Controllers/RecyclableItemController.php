<?php

namespace App\Http\Controllers;

use App\Models\RecyclableItem;
use Illuminate\Http\Request;

class RecyclableItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(RecyclableItem::with('category')->get(), 200);
    }

    /**
     * Store a newly created resource in storage (Admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'nullable|integer',
            'barcode' => 'required|string|unique:recyclable_items,barcode',
            'category_id' => 'nullable|exists:recyclable_item_categories,id',
        ]);

        $item = RecyclableItem::create($validated);
        $item->load('category');

        return response()->json($item, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $item = RecyclableItem::with('category')->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        return response()->json($item, 200);
    }

    /**
     * Update the specified resource in storage (Admin only).
     */
    public function update(Request $request, $id)
    {
        $item = RecyclableItem::find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'value' => 'sometimes|nullable|integer',
            'barcode' => 'sometimes|string|unique:recyclable_items,barcode,' . $id,
            'category_id' => 'sometimes|nullable|exists:recyclable_item_categories,id',
        ]);

        $item->update($validated);
        $item->load('category');

        return response()->json($item, 200);
    }

    /**
     * Remove the specified resource from storage (Admin only).
     */
    public function destroy($id)
    {
        $item = RecyclableItem::find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.'], 200);
    }
}
