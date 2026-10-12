<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Models\Profile;
use App\Models\RecyclableItem;
use App\Models\RecyclingBin;
use App\Models\RecyclingSession;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $categories = DB::table('transactions')
            ->join('recyclable_items', 'transactions.recyclable_item_id', '=', 'recyclable_items.id')
            ->join('recyclable_item_categories', 'recyclable_items.category_id', '=', 'recyclable_item_categories.id')
            ->where('transactions.status', TransactionStatus::ACCEPTED->value)
            ->select('recyclable_item_categories.id', 'recyclable_item_categories.name')
            ->selectRaw('COUNT(*) as scan_count')
            ->groupBy('recyclable_item_categories.id', 'recyclable_item_categories.name')
            ->orderByDesc('scan_count')
            ->orderBy('recyclable_item_categories.id')
            ->limit(5)
            ->get()
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'scan_count' => (int) $category->scan_count,
            ]);

        return response()->json([
            'profiles_count' => Profile::count(),
            'sessions_count' => RecyclingSession::count(),
            'bins_count' => RecyclingBin::count(),
            'items_count' => RecyclableItem::count(),
            'accepted_scans_count' => Transaction::where('status', TransactionStatus::ACCEPTED)->count(),
            'top_categories' => $categories,
        ]);
    }
}
