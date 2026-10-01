<?php

namespace App\Providers;

use App\Models\StockAdjustment;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Ability manual (ERD §6). Bukan Policy per model karena sebagian besar ability
 * adalah aksi bisnis (POS, shift, kas, piutang), bukan CRUD satu model.
 * Penegakan aturan detail tetap di service layer (ERD poin 2) -- Gate ini dipakai
 * controller sebelum memanggil service, dan dibagikan ke Vue lewat shared props
 * Inertia (Fase 4) untuk menyembunyikan menu/tombol.
 *
 * Ability yang terikat cabang menerima $branchId eksplisit (cabang aktif Owner
 * dibaca dari sesi oleh controller/middleware di Fase 4, lalu dioper ke sini --
 * Gate sendiri tidak menyimpan sesi).
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // --- Penjualan ---
        Gate::define('sales.create', fn (User $user, ?int $branchId = null) => $this->inOwnBranchOrOwner($user, $branchId)
        );

        Gate::define('sales.give_credit', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        Gate::define('sales.void', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        // --- Shift & Kas ---
        Gate::define('shift.open', fn (User $user, ?int $branchId = null) => $this->inOwnBranchOrOwner($user, $branchId)
        );

        Gate::define('shift.force_close', function (User $user, int $branchId) {
            if ($user->isOwner()) {
                return true;
            }

            return $user->isAdmin() && (int) $user->branch_id === $branchId;
        });

        Gate::define('cash_movement.manage', fn (User $user, ?int $branchId = null) => $this->inOwnBranchOrOwner($user, $branchId)
        );

        // --- Barang & Laporan ---
        Gate::define('item.view_cost', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        Gate::define('report.view_margin', function (User $user) {
            if ($user->isOwner()) {
                return true;
            }

            return $user->isAdmin() && (bool) config('toko.admin_can_view_margin');
        });

        // --- Barang Masuk & Adjustment ---
        // Pembatasan cabang detail (restock cabang manapun untuk Admin, alasan lain
        // hanya cabang sendiri) ditegakkan StockAdjustmentService, bukan di sini --
        // Gate ini hanya menyaring siapa yang boleh membuka fitur sama sekali.
        Gate::define('stock_adjustment.create', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        Gate::define('adjustment.approve', function (User $user, StockAdjustment $adjustment) {
            if ($user->isOwner()) {
                return true;
            }

            if (! $user->isAdmin() || (int) $user->branch_id !== (int) $adjustment->branch_id) {
                return false;
            }

            return (int) $adjustment->created_by !== $user->id;
        });

        // --- Transfer ---
        // Sama seperti stock_adjustment.create: hanya menyaring kasar siapa yang
        // boleh membuat/mengirim/membatalkan transfer sama sekali. Kasir tidak
        // pernah ikut modul ini (tidak disebut ERD §6). Pembatasan "Admin hanya
        // dari/ke cabangnya sendiri, Owner bebas" ditegakkan TransferService.
        Gate::define('transfer.create', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        Gate::define('transfer.resolve', function (User $user, Transfer $transfer) {
            if ($user->isOwner()) {
                return true;
            }

            return $user->isAdmin()
                && ((int) $user->branch_id === (int) $transfer->from_branch_id
                    || (int) $user->branch_id === (int) $transfer->to_branch_id);
        });

        // --- Stok ---
        Gate::define('stock.recalculate', fn (User $user) => $user->isOwner()
        );

        // --- Piutang & Catatan ---
        Gate::define('notes.manage', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );

        // --- Master Data ---
        Gate::define('item.manage', fn (User $user) => $this->canManageMasterData($user)
        );

        Gate::define('category.manage', fn (User $user) => $this->canManageMasterData($user)
        );

        // --- Pelanggan ---
        // Tidak ada di ERD §6 secara eksplisit; diputuskan sama seperti sales.give_credit
        // (Admin/Owner) karena pelanggan hanya relevan untuk transaksi kredit (ERD §2 no. 18).
        Gate::define('customer.manage', fn (User $user) => $user->isAdmin() || $user->isOwner()
        );
    }

    /**
     * Owner: selalu boleh (cabang aktif dipilih di sesi, divalidasi terpisah).
     * Admin/Cashier: hanya jika $branchId cocok dengan branch_id miliknya
     * (atau $branchId tidak dioper -- pemanggil belum tahu cabang, biarkan lolos
     * di sini dan cek ulang di service layer yang wajib menerima branchId eksplisit).
     */
    private function inOwnBranchOrOwner(User $user, ?int $branchId): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($branchId === null) {
            return true;
        }

        return (int) $user->branch_id === $branchId;
    }

    /**
     * item.manage / category.manage (ERD §2 no. 13): Owner, atau Admin dengan
     * branch_id = cabang utama (is_main = 1).
     */
    private function canManageMasterData(User $user): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->isAdmin() && $user->branch?->is_main === true;
    }
}