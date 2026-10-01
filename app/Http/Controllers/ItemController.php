<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $items)
    {
    }

    /**
     * Semua user login boleh lihat (dipakai POS). Tombol kelola hanya untuk
     * yang lolos item.manage (canManage). avg_cost/harga modal sengaja TIDAK
     * dikirim di sini (ERD §5 no. 9); tampil di modul stok untuk yang berhak.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $user = $request->user();
        $branchId = $user->isOwner()
            ? $request->session()->get('active_branch_id')
            : $user->branch_id;
        $branchId = $branchId ? (int) $branchId : null;
        $categoryId = filter_var($request->query('category_id'), FILTER_VALIDATE_INT);
        $categoryId = $categoryId && $categoryId > 0 ? $categoryId : null;
        $stockFilter = $branchId && $request->query('stock') === 'low' ? 'low' : '';

        $items = Item::query()
            ->with([
                'category:id,name',
                'units' => fn ($q) => $q->orderBy('unit'),
                'stockBalances' => fn ($q) => $q
                    ->where('branch_id', $branchId)
                    ->select(['id', 'branch_id', 'item_id', 'qty', 'min_stock']),
            ])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($stockFilter === 'low', fn ($query) => $query->whereHas('stockBalances', fn ($stock) => $stock
                ->where('branch_id', $branchId)
                ->where('min_stock', '>', 0)
                ->whereColumn('qty', '<=', 'min_stock')))
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";

                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhereHas('units', fn ($u) => $u->where('barcode', 'like', $like));
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'brand' => $item->brand,
                'specification' => $item->specification,
                'unit' => $item->unit,
                'sell_price' => $item->sell_price,
                'is_active' => $item->is_active,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'stock' => $branchId ? (float) ($item->stockBalances->first()->qty ?? 0) : null,
                'min_stock' => $branchId ? (float) ($item->stockBalances->first()->min_stock ?? 0) : null,
                'units' => $item->units->map(fn ($u) => [
                    'id' => $u->id,
                    'unit' => $u->unit,
                    'conversion_qty' => $u->conversion_qty,
                    'barcode' => $u->barcode,
                    'sell_price' => $u->sell_price,
                ]),
            ]);

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => [
                'q' => $search,
                'category_id' => $categoryId,
                'stock' => $stockFilter,
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'canManage' => Gate::allows('item.manage'),
            'stockBranchAvailable' => $branchId !== null,
        ]);
    }

    /**
     * Baru: pencarian cepat untuk kotak search di navbar (AuthenticatedLayout).
     * JSON biasa (bukan Inertia::render) supaya bisa di-fetch tiap ketik tanpa
     * pindah halaman. Maksimal 8 hasil, tanpa avg_cost (ERD §5 no. 9 tetap
     * berlaku). Stok yang ditampilkan mengikuti cabang aktif user:
     * - admin/cashier -> branch_id miliknya sendiri
     * - owner         -> cabang aktif di sesi; kalau belum pilih, stok
     *                    dikirim null (bukan error) supaya search tetap jalan.
     */
    public function quickSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['items' => []]);
        }

        $user = $request->user();
        $branchId = $user->isOwner()
            ? $request->session()->get('active_branch_id')
            : $user->branch_id;

        $like = "%{$query}%";

        $items = Item::query()
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            })
            ->when($branchId, fn ($q) => $q->with([
                'stockBalances' => fn ($sb) => $sb->where('branch_id', $branchId),
            ]))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'unit' => $item->unit,
                'sell_price' => $item->sell_price,
                'stock' => $branchId ? (float) ($item->stockBalances->first()->qty ?? 0) : null,
            ]);

        return response()->json(['items' => $items]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $this->items->create($request->validated());

        return back()->with('success', 'Barang ditambahkan.');
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $this->items->update($item, $request->validated());

        return back()->with('success', 'Barang diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('item.manage');

        $this->items->delete($item);

        return back()->with('success', 'Barang dinonaktifkan.');
    }
}