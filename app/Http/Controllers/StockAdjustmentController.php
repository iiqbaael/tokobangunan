<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $adjustments)
    {
    }

    /**
     * Admin hanya melihat dokumen cabangnya sendiri; Owner melihat semua
     * (ERD §5 no. 1, filter cabang di level query -- bukan hanya UI).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $adjustments = StockAdjustment::query()
            ->with([
                'branch:id,name,code',
                'creator:id,name',
                'approver:id,name',
                'details.item' => fn ($query) => $query->withTrashed(),
            ])
            ->when(! $user->isOwner(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StockAdjustment $a) => [
                'id' => $a->id,
                'adjustment_no' => $a->adjustment_no,
                'adjustment_date' => $a->adjustment_date->format('Y-m-d'),
                'adjustment_type' => $a->adjustment_type->value,
                'reason' => $a->reason->value,
                'status' => $a->status->value,
                'reference_no' => $a->reference_no,
                'description' => $a->description,
                'rejected_reason' => $a->rejected_reason,
                'branch_name' => $a->branch?->name ?? 'Cabang tidak tersedia',
                'creator_name' => $a->creator?->name ?? 'Pengguna tidak tersedia',
                'approver_name' => $a->approver?->name,
                'created_by' => $a->created_by,
                'items' => $a->details->map(fn ($d) => [
                    'item_name' => $d->item?->name ?? "Barang tidak tersedia (#{$d->item_id})",
                    'unit' => $d->item?->unit ?? '-',
                    'qty' => $d->qty,
                    'unit_cost' => $d->unit_cost,
                ]),
            ]);

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
            'filters' => ['status' => $request->query('status', '')],
            'branches' => $user->isOwner()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : Branch::query()->where('id', $user->branch_id)->get(['id', 'name']),
            'items' => Item::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'canCreate' => Gate::allows('stock_adjustment.create'),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $this->adjustments->create($request->validated(), $request->user());

        return back()->with('success', 'Dokumen tersimpan.');
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('adjustment.approve', $stockAdjustment);

        $this->adjustments->approve($stockAdjustment, $request->user());

        return back()->with('success', 'Dokumen disetujui, stok diperbarui.');
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('adjustment.approve', $stockAdjustment);

        $validated = $request->validate([
            'rejected_reason' => ['required', 'string', 'max:255'],
        ], [], ['rejected_reason' => 'alasan penolakan']);

        $this->adjustments->reject($stockAdjustment, $request->user(), $validated['rejected_reason']);

        return back()->with('success', 'Dokumen ditolak.');
    }
}