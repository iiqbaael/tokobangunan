<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\CashierShiftService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('pos history remains available when a referenced item is soft deleted', function () {
    $branch = Branch::create([
        'code' => 'SALEARCH',
        'name' => 'Cabang Arsip POS',
        'is_main' => true,
    ]);
    $owner = User::create([
        'branch_id' => null,
        'name' => 'Owner POS Test',
        'email' => 'sale-index-'.uniqid().'@toko.test',
        'password' => 'password',
        'role' => UserRole::Owner,
    ]);
    $item = Item::create([
        'code' => 'SALE-ARCHIVE-ITEM',
        'name' => 'Barang Nota Terhapus',
        'unit' => 'pcs',
        'sell_price' => 10000,
    ]);
    $shift = app(CashierShiftService::class)->open($branch->id, $owner, '100000');
    $saleId = DB::table('sales_headers')->insertGetId([
        'branch_id' => $branch->id,
        'cashier_shift_id' => $shift->id,
        'invoice_no' => 'INV-ARCHIVE-001',
        'sale_date' => now(),
        'total' => '10000.00',
        'status' => 'completed',
        'created_by' => $owner->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sales_details')->insert([
        'sale_id' => $saleId,
        'item_id' => $item->id,
        'unit' => 'pcs',
        'qty_input' => '1.0000',
        'conversion_qty' => '1.0000',
        'qty' => '1.0000',
        'unit_price' => '10000.00',
        'subtotal' => '10000.00',
        'cost_at_sale' => '7500.0000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $item->delete();

    $this->actingAs($owner)
        ->withSession(['active_branch_id' => $branch->id])
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->where('history.data.0.items.0.item_name', 'Barang Nota Terhapus')
            ->where('history.data.0.items.0.unit', 'pcs'));
});