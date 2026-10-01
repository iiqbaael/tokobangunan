<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveBranch;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesHeader;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * POS Penjualan & Void (UC-SAL-01/02/03/07, ERD §4.3, §5 no. 6/7/9/10/13/14).
 * Otorisasi kasar (boleh akses POS, boleh kredit, boleh void) dicek Gate di
 * sini; aturan bisnis yang butuh data (shift open, kesamaan total, cost_at_sale,
 * saldo piutang tidak boleh negatif saat void) ditegakkan SaleService.
 */
class SaleController extends Controller
{
    use ResolvesActiveBranch;

    public function __construct(private readonly SaleService $sales)
    {
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isOwner() && ! $request->session()->get('active_branch_id')) {
            return redirect()->route('active-branch.edit')
                ->with('error', 'Pilih cabang aktif terlebih dahulu.');
        }

        $branchId = $this->activeBranchId($request);

        Gate::authorize('sales.create', $branchId);

        $items = Item::query()
            ->with(['units:id,item_id,unit,conversion_qty,sell_price'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'unit' => $item->unit,
                'sell_price' => $item->sell_price,
                'units' => $item->units->map(fn ($u) => [
                    'unit' => $u->unit,
                    'conversion_qty' => $u->conversion_qty,
                    'sell_price' => $u->sell_price,
                ]),
            ]);

        $hasOpenShift = CashierShift::query()
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->exists();

        $canViewHistory = $user->isAdmin() || $user->isOwner();

        return Inertia::render('Sales/Index', [
            'branchId' => $branchId,
            'hasOpenShift' => $hasOpenShift,
            'items' => $items,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'canGiveCredit' => Gate::allows('sales.give_credit'),
            'canVoid' => Gate::allows('sales.void'),
            'history' => $canViewHistory
                ? SalesHeader::query()
                    ->with([
                        'creator:id,name',
                        'customer:id,name',
                        'details.item' => fn ($query) => $query->withTrashed(),
                    ])
                    ->where('branch_id', $branchId)
                    ->orderByDesc('id')
                    ->paginate(15)
                    ->withQueryString()
                    ->through(fn (SalesHeader $s) => [
                        'id' => $s->id,
                        'invoice_no' => $s->invoice_no,
                        'sale_date' => $s->sale_date->format('Y-m-d H:i'),
                        'status' => $s->status->value,
                        'total' => $s->total,
                        'customer_name' => $s->customer?->name,
                        'creator_name' => $s->creator?->name ?? 'Pengguna tidak tersedia',
                        'void_reason' => $s->void_reason,
                        'items' => $s->details->map(fn ($d) => [
                            'item_name' => $d->item?->name ?? "Barang tidak tersedia (#{$d->item_id})",
                            'unit' => $d->unit,
                            'qty_input' => $d->qty_input,
                            'subtotal' => $d->subtotal,
                        ]),
                    ])
                : null,
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $branchId = $this->activeBranchId($request);

        Gate::authorize('sales.create', $branchId);

        $sale = $this->sales->create($branchId, $request->validated(), $request->user());

        return back()->with('success', "Nota {$sale->invoice_no} tersimpan.");
    }

    public function void(Request $request, SalesHeader $sale): RedirectResponse
    {
        Gate::authorize('sales.void');

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ], [], ['void_reason' => 'alasan void']);

        $this->sales->void($sale, $request->user(), $validated['void_reason']);

        return back()->with('success', "Nota {$sale->invoice_no} dibatalkan (void).");
    }
}