<?php

namespace App\Http\Controllers;

use App\Enums\CashMovementType;
use App\Http\Controllers\Concerns\ResolvesActiveBranch;
use App\Services\CashMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class CashMovementController extends Controller
{
    use ResolvesActiveBranch;

    public function __construct(private readonly CashMovementService $cashMovements)
    {
    }

    /**
     * ERD §5 no. 12: siapa pun yang berhak (cash_movement.manage) di cabang aktif boleh
     * mencatat, bukan hanya pembuka shift. Shift 'open' dicari oleh service lewat branchId,
     * bukan lewat cashier_shift_id dari form (mencegah user mengirim shift orang lain).
     */
    public function store(Request $request): RedirectResponse
    {
        $branchId = $this->activeBranchId($request);

        Gate::authorize('cash_movement.manage', $branchId);

        $validated = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'type' => 'jenis',
            'amount' => 'jumlah',
            'reason' => 'alasan',
        ]);

        try {
            $this->cashMovements->record(
                $branchId,
                $request->user(),
                CashMovementType::from($validated['type']),
                $validated['amount'],
                $validated['reason'],
                $validated['note'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kas dicatat.');
    }
}