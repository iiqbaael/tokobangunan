<?php

namespace App\Http\Controllers;

use App\Enums\DifferenceResolution;
use App\Enums\TransferStatus;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers)
    {
    }

    /**
     * Admin hanya melihat transfer yang menyangkut cabangnya (asal ATAU tujuan);
     * Owner melihat semua (ERD §5 no. 1, filter di level query).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $transfers = Transfer::query()
            ->with([
                'fromBranch:id,name,code',
                'toBranch:id,name,code',
                'creator:id,name',
                'sender:id,name',
                'receiver:id,name',
                'details.item:id,code,name,unit',
            ])
            ->when(! $user->isOwner(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->where('from_branch_id', $user->branch_id)
                    ->orWhere('to_branch_id', $user->branch_id);
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transfer $t) => [
                'id' => $t->id,
                'transfer_no' => $t->transfer_no,
                'transfer_date' => $t->transfer_date->format('Y-m-d'),
                'status' => $t->status->value,
                'note' => $t->note,
                'from_branch_id' => $t->from_branch_id,
                'to_branch_id' => $t->to_branch_id,
                'from_branch_name' => $t->fromBranch->name,
                'to_branch_name' => $t->toBranch->name,
                'creator_name' => $t->creator->name,
                'sender_name' => $t->sender?->name,
                'receiver_name' => $t->receiver?->name,
                'items' => $t->details->map(fn (TransferDetail $d) => [
                    'id' => $d->id,
                    'item_name' => $d->item->name,
                    'unit' => $d->item->unit,
                    'qty_sent' => $d->qty_sent,
                    'qty_received' => $d->qty_received,
                    'unit_cost' => $d->unit_cost,
                    'difference_resolution' => $d->difference_resolution->value,
                    'has_difference' => bccomp((string) $d->qty_received, (string) $d->qty_sent, 4) < 0,
                ]),
            ]);

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'filters' => ['status' => $request->query('status', '')],
            'branches' => $user->isOwner()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => Item::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'unit']),
            'canCreate' => Gate::allows('transfer.create'),
        ]);
    }

    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $this->transfers->create($request->validated(), $request->user());

        return back()->with('success', 'Dokumen transfer (draft) tersimpan.');
    }

    public function send(Request $request, Transfer $transfer): RedirectResponse
    {
        Gate::authorize('transfer.create');

        $this->transfers->send($transfer, $request->user());

        return back()->with('success', 'Transfer dikirim, stok cabang asal berkurang.');
    }

    public function receive(Request $request, Transfer $transfer): RedirectResponse
    {
        Gate::authorize('transfer.resolve', $transfer);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // items dikirim sebagai { transfer_detail_id: qty_received }
        $this->transfers->receive($transfer, $validated['items'], $request->user());

        return back()->with('success', 'Transfer diterima.');
    }

    public function resolveDifference(Request $request, TransferDetail $transferDetail): RedirectResponse
    {
        $transfer = $transferDetail->transfer;

        Gate::authorize('transfer.approve', $transfer);

        $validated = $request->validate([
            'difference_resolution' => ['required', 'in:returned,written_off'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['difference_resolution' => 'resolusi selisih']);

        $this->transfers->resolveDifference(
            $transferDetail,
            DifferenceResolution::from($validated['difference_resolution']),
            $request->user(),
            $validated['note'] ?? null,
        );

        return back()->with('success', 'Selisih diselesaikan.');
    }

    public function cancel(Request $request, Transfer $transfer): RedirectResponse
    {
        Gate::authorize('transfer.create');

        $this->transfers->cancel($transfer, $request->user());

        return back()->with('success', 'Transfer dibatalkan.');
    }
}