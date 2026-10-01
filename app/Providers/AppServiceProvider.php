<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // ERD §8: morph map wajib lengkap (19 model). Dipakai oleh
        // stock_mutations.reference_type, audit_logs.model_type, notes.noteable_type.
        Relation::enforceMorphMap([
            'branch'                  => \App\Models\Branch::class,
            'user'                    => \App\Models\User::class,
            'category'                => \App\Models\Category::class,
            'customer'                => \App\Models\Customer::class,
            'item'                    => \App\Models\Item::class,
            'item_unit'               => \App\Models\ItemUnit::class,
            'sale'                    => \App\Models\SalesHeader::class,
            'sale_detail'             => \App\Models\SalesDetail::class,
            'sale_payment'            => \App\Models\SalePayment::class,
            'cashier_shift'           => \App\Models\CashierShift::class,
            'cash_movement'           => \App\Models\CashMovement::class,
            'stock_mutation'          => \App\Models\StockMutation::class,
            'stock_balance'           => \App\Models\StockBalance::class,
            'transfer'                => \App\Models\Transfer::class,
            'transfer_detail'         => \App\Models\TransferDetail::class,
            'stock_adjustment'        => \App\Models\StockAdjustment::class,
            'stock_adjustment_detail' => \App\Models\StockAdjustmentDetail::class,
            'audit_log'               => \App\Models\AuditLog::class,
            'note'                    => \App\Models\Note::class,
        ]);
    }
}