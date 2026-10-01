<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActiveBranchController extends Controller
{
    /**
     * Halaman pemilih cabang aktif (fallback). Hanya Owner (branch_id NULL)
     * yang boleh mengakses -- Admin/Kasir selalu terikat ke branch_id miliknya
     * sendiri (ERD §5 no. 1, §2 no. 17).
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->isOwner(), 403);

        return Inertia::render('ActiveBranch/Select', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'activeBranchId' => $request->session()->get('active_branch_id'),
        ]);
    }

    /**
     * Simpan cabang aktif Owner ke sesi. Murni state sesi, dipakai validasi
     * cabang untuk operasi POS/shift/kas (ERD §5 no. 1). Setelah simpan,
     * kembali ke halaman asal supaya pemilih di topbar tidak berpindah halaman.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branch = Branch::query()
            ->where('is_active', true)
            ->findOrFail($validated['branch_id']);

        $request->session()->put('active_branch_id', $branch->id);

        return redirect()->back(fallback: route('dashboard'))
            ->with('success', "Cabang aktif diubah ke {$branch->name}.");
    }
}