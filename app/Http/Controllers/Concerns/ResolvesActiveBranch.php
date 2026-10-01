<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cabang yang dipakai untuk operasi POS/shift/kas (ERD §5 no. 1).
 * Admin/Kasir: selalu branch_id miliknya sendiri.
 * Owner: cabang aktif dari sesi (diisi lewat ActiveBranchController).
 */
trait ResolvesActiveBranch
{
    protected function activeBranchId(Request $request): int
    {
        $user = $request->user();

        if (! $user->isOwner()) {
            return (int) $user->branch_id;
        }

        $branchId = $request->session()->get('active_branch_id');

        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Pilih cabang aktif terlebih dahulu.',
            ]);
        }

        return (int) $branchId;
    }
}