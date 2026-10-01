<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('stock adjustment history remains available when a referenced item is soft deleted', function () {
    $branch = Branch::create([
        'code' => 'ARCHIVE',
        'name' => 'Cabang Arsip',
        'is_main' => true,
    ]);
    $owner = User::create([
        'branch_id' => null,
        'name' => 'Owner Test',
        'email' => 'adjustment-index-'.uniqid().'@toko.test',
        'password' => 'password',
        'role' => UserRole::Owner,
    ]);
    $item = Item::create([
        'code' => 'ARCHIVE-ITEM',
        'name' => 'Barang Riwayat Terhapus',
        'unit' => 'pcs',
        'sell_price' => 10000,
    ]);

    $adjustmentId = DB::table('stock_adjustments')->insertGetId([
        'branch_id' => $branch->id,
        'adjustment_no' => 'ADJ-ARCHIVE-001',
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'initial_stock',
        'status' => 'approved',
        'created_by' => $owner->id,
        'approved_by' => $owner->id,
        'approved_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('stock_adjustment_details')->insert([
        'stock_adjustment_id' => $adjustmentId,
        'item_id' => $item->id,
        'qty' => '5.0000',
        'unit_cost' => '7500.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $item->delete();

    $this->actingAs($owner)
        ->get(route('stock-adjustments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('StockAdjustments/Index')
            ->where('adjustments.data.0.items.0.item_name', 'Barang Riwayat Terhapus')
            ->where('adjustments.data.0.items.0.unit', 'pcs'));
});