<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveBranch;
use App\Models\CashierShift;
use App\Services\CashierShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class CashierShiftController extends Controller
{
    use ResolvesActiveBranch;

    public function __construct(private readonly CashierShiftService $shifts)
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

        $currentShift = CashierShift::query()
            ->with([
                'opener:id,name',
                'cashMovements' => fn ($q) => $q->latest()->with('creator:id,name'),
            ])
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->first();

        $canViewHistory = $user->isAdmin() || $user->isOwner();

        return Inertia::render('CashierShift/Index', [
            'branchId' => $branchId,
            'currentShift' => $currentShift ? [
                'id' => $currentShift->id,
                'opening_balance' => $currentShift->opening_balance,
                'opened_at' => $currentShift->opened_at->format('Y-m-d H:i'),
                'opener_name' => $currentShift->opener->name,
                'is_opener' => $currentShift->user_id === $user->id,
                'cash_movements' => $currentShift->cashMovements->map(fn ($m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'amount' => $m->amount,
                    'reason' => $m->reason,
                    'note' => $m->note,
                    'creator_name' => $m->creator->name,
                    'created_at' => $m->created_at->format('Y-m-d H:i'),
                ]),
            ] : null,
            'canOpen' => Gate::allows('shift.open', $branchId),
            'canForceClose' => Gate::allows('shift.force_close', $branchId),
            'canRecordCash' => Gate::allows('cash_movement.manage', $branchId),
            'history' => $canViewHistory
                ? CashierShift::query()
                    ->with(['opener:id,name', 'closer:id,name'])
                    ->where('branch_id', $branchId)
                    ->where('status', 'closed')
                    ->orderByDesc('id')
                    ->paginate(10)
                    ->withQueryString()
                    ->through(fn (CashierShift $s) => [
                        'id' => $s->id,
                        'opening_balance' => $s->opening_balance,
                        'closing_balance' => $s->closing_balance,
                        'expected_balance' => $s->expected_balance,
                        'difference' => $s->difference,
                        'opener_name' => $s->opener->name,
                        'closer_name' => $s->closer?->name,
                        'opened_at' => $s->opened_at->format('Y-m-d H:i'),
                        'closed_at' => $s->closed_at?->format('Y-m-d H:i'),
                    ])
                : null,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $branchId = $this->activeBranchId($request);

        Gate::authorize('shift.open', $branchId);

        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['opening_balance' => 'modal awal']);

        try {
            $this->shifts->open($branchId, $request->user(), $validated['opening_balance'], $validated['note'] ?? null);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shift dibuka.');
    }

    /**
     * Otorisasi (pembuka shift atau force-close Admin/Owner) ditegakkan di
     * CashierShiftService::close(); di sini hanya menerjemahkan exception-nya
     * jadi flash message (service ini pakai RuntimeException, bukan ValidationException).
     */
    public function close(Request $request, CashierShift $cashierShift): RedirectResponse
    {
        $validated = $request->validate([
            'closing_balance' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['closing_balance' => 'kas fisik akhir']);

        try {
            $this->shifts->close($cashierShift, $request->user(), $validated['closing_balance'], $validated['note'] ?? null);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Shift ditutup.');
    }
}