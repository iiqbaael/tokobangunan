<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * ERD §8: daftar ability + active_branch_id dibagikan ke Vue lewat shared props,
     * dipakai HANYA untuk menyembunyikan menu/tombol. Penegakan aturan tetap di
     * service layer/Gate backend (ERD poin 2) -- request tetap divalidasi ulang di server.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'active_branch_id' => $user ? $this->activeBranchId($request, $user) : null,
                // Objek ringkas cabang aktif (id/code/name) buat ditampilkan di navbar.
                'active_branch' => $user ? $this->activeBranch($request, $user) : null,
                // Baru: daftar cabang aktif untuk card pemilih cabang di topbar.
                // Hanya Owner; user lain dapat array kosong.
                'branches' => $user ? $this->branchOptions($user) : [],
                'abilities' => $user ? $this->abilities($request, $user) : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }

    /**
     * Owner: cabang aktif dipilih & disimpan di sesi. Admin/Kasir: selalu
     * cabangnya sendiri, sesi diabaikan.
     */
    private function activeBranchId(Request $request, \App\Models\User $user): ?int
    {
        if (! $user->isOwner()) {
            return $user->branch_id;
        }

        return $request->session()->get('active_branch_id');
    }

    /**
     * Objek ringkas cabang aktif untuk ditampilkan di UI (bukan untuk validasi --
     * validasi tetap lewat activeBranchId/ResolvesActiveBranch di service layer).
     *
     * @return array{id: int, code: string, name: string}|null
     */
    private function activeBranch(Request $request, \App\Models\User $user): ?array
    {
        $branchId = $this->activeBranchId($request, $user);

        if ($branchId === null) {
            return null;
        }

        $branch = Branch::query()->find($branchId, ['id', 'code', 'name']);

        if (! $branch) {
            return null;
        }

        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
        ];
    }

    /**
     * Daftar cabang aktif untuk pemilih cabang di topbar. Hanya Owner
     * (ERD §5 no. 1, §2 no. 17); hanya untuk tampilan, update tetap divalidasi
     * ActiveBranchController.
     *
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function branchOptions(\App\Models\User $user): array
    {
        if (! $user->isOwner()) {
            return [];
        }

        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
            ])
            ->all();
    }

    /**
     * Ability yang tidak butuh model spesifik (ERD §6), dievaluasi terhadap
     * active_branch_id untuk yang berkonteks cabang. adjustment.approve dan
     * transfer.approve butuh instance model, dicek langsung di controller terkait.
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request, \App\Models\User $user): array
    {
        $branchId = $this->activeBranchId($request, $user);
        $gate = Gate::forUser($user);

        return [
            'sales.create' => $gate->allows('sales.create', $branchId),
            'sales.give_credit' => $gate->allows('sales.give_credit'),
            'sales.void' => $gate->allows('sales.void'),
            'shift.open' => $gate->allows('shift.open', $branchId),
            'shift.force_close' => $branchId !== null && $gate->allows('shift.force_close', $branchId),
            'cash_movement.manage' => $gate->allows('cash_movement.manage', $branchId),
            'item.view_cost' => $gate->allows('item.view_cost'),
            'report.view_margin' => $gate->allows('report.view_margin'),
            'stock.recalculate' => $gate->allows('stock.recalculate'),
            'notes.manage' => $gate->allows('notes.manage'),
            'transfer.create' => $gate->allows('transfer.create'),
            'item.manage' => $gate->allows('item.manage'),
            'category.manage' => $gate->allows('category.manage'),
        ];
    }
}