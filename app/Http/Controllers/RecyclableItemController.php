<?php

namespace App\Http\Controllers;

use App\Models\RecyclableItem;
use Illuminate\Http\Request;

class RecyclableItemController extends Controller
{
    public function index()
    {
        // Eager load 'category' so the model doesn't run extra queries
        $items = RecyclableItem::with('category')->get();
        return response()->json($items, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manual_value' => 'nullable|integer',
            'barcode' => 'required|string|unique:recyclable_items,barcode',
            'category_id' => 'required|exists:recyclable_item_categories,id',
        ]);

        $item = RecyclableItem::create($validated);

        // Load category so the response includes the correct calculated price
        $item->load('category');

        return response()->json($item, 201);
    }

    public function show($id)
    {
        // Find with category
        $item = RecyclableItem::with('category')->find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        return response()->json($item, 200);
    }

    public function update(Request $request, $id)
    {
        $item = RecyclableItem::find($id);

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'manual_value' => 'sometimes|nullable|integer',
            'barcode' => 'sometimes|string|unique:recyclable_items,barcode,' . $id,
            'category_id' => 'sometimes|required|exists:recyclable_item_categories,id',
        ]);

        $item->update($validated);

        // Refresh the relationship data
        $item->load('category');

        return response()->json($item, 200);
    }

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
