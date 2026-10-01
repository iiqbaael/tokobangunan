<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\ItemService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (! function_exists('makeBranch')) {
    function makeBranch(array $attrs = []): Branch
    {
        static $seq = 0;
        $seq++;

        return Branch::create(array_merge([
            'code' => 'BR'.$seq,
            'name' => 'Cabang '.$seq,
            'is_main' => false,
        ], $attrs));
    }
}

if (! function_exists('makeOwner')) {
    function makeOwner(): User
    {
        return User::create([
            'branch_id' => null,
            'name' => 'Owner',
            'email' => 'owner'.uniqid().'@toko.test',
            'password' => 'password',
            'role' => UserRole::Owner,
        ]);
    }
}

if (! function_exists('makeAdmin')) {
    function makeAdmin(int $branchId): User
    {
        static $seq = 0;
        $seq++;

        return User::create([
            'branch_id' => $branchId,
            'name' => 'Admin '.$seq,
            'email' => 'admin'.uniqid().'@toko.test',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);
    }
}

if (! function_exists('makeItem')) {
    function makeItem(array $attrs = []): Item
    {
        static $seq = 0;
        $seq++;

        return Item::create(array_merge([
            'code' => 'ITM'.$seq,
            'name' => 'Barang '.$seq,
            'unit' => 'pcs',
            'sell_price' => 10000,
        ], $attrs));
    }
}

function seedSaleHeader(int $branchId, int $shiftId, int $createdBy): int
{
    static $seq = 0;
    $seq++;

    return DB::table('sales_headers')->insertGetId([
        'branch_id' => $branchId,
        'cashier_shift_id' => $shiftId,
        'invoice_no' => 'INV-CHK-'.$seq.'-'.uniqid(),
        'sale_date' => now(),
        'total' => 0,
        'status' => 'completed',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedTransfer(int $fromBranchId, int $toBranchId): int
{
    static $seq = 0;
    $seq++;

    return DB::table('transfers')->insertGetId([
        'from_branch_id' => $fromBranchId,
        'to_branch_id' => $toBranchId,
        'transfer_no' => 'TRF-CHK-'.$seq.'-'.uniqid(),
        'transfer_date' => now()->toDateString(),
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedStockAdjustment(int $branchId, int $createdBy): int
{
    static $seq = 0;
    $seq++;

    return DB::table('stock_adjustments')->insertGetId([
        'branch_id' => $branchId,
        'adjustment_no' => 'ADJ-CHK-'.$seq.'-'.uniqid(),
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'status' => 'approved',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->branch = makeBranch();
    $this->owner = makeOwner();
    $this->admin = makeAdmin($this->branch->id);
    $this->item = makeItem();
});

test('chk_users_role_branch menolak owner dengan branch_id terisi', function () {
    expect(fn () => DB::table('users')->insert([
        'branch_id' => $this->branch->id,
        'name' => 'Owner Salah',
        'email' => 'ownersalah'.uniqid().'@toko.test',
        'password' => 'x',
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_users_role_branch menolak admin/kasir dengan branch_id kosong', function () {
    expect(fn () => DB::table('users')->insert([
        'branch_id' => null,
        'name' => 'Admin Salah',
        'email' => 'adminsalah'.uniqid().'@toko.test',
        'password' => 'x',
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_item_units_conversion menolak conversion_qty <= 0', function () {
    expect(fn () => DB::table('item_units')->insert([
        'item_id' => $this->item->id,
        'unit' => 'dus',
        'conversion_qty' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_sales_headers_total menolak total negatif', function () {
    $shift = app(CashierShiftService::class)->open($this->branch->id, $this->admin, '0');

    expect(fn () => DB::table('sales_headers')->insert([
        'branch_id' => $this->branch->id,
        'cashier_shift_id' => $shift->id,
        'invoice_no' => 'INV-NEG-'.uniqid(),
        'sale_date' => now(),
        'total' => -1,
        'status' => 'completed',
        'created_by' => $this->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_sales_headers_cash menolak cash_received negatif', function () {
    $shift = app(CashierShiftService::class)->open($this->branch->id, $this->admin, '0');

    expect(fn () => DB::table('sales_headers')->insert([
        'branch_id' => $this->branch->id,
        'cashier_shift_id' => $shift->id,
        'invoice_no' => 'INV-NEG2-'.uniqid(),
        'sale_date' => now(),
        'total' => 0,
        'cash_received' => -1,
        'status' => 'completed',
        'created_by' => $this->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_sales_details_qty menolak qty_input <= 0', function () {
    $shift = app(CashierShiftService::class)->open($this->branch->id, $this->admin, '0');
    $saleId = seedSaleHeader($this->branch->id, $shift->id, $this->admin->id);

    expect(fn () => DB::table('sales_details')->insert([
        'sale_id' => $saleId,
        'item_id' => $this->item->id,
        'unit' => 'pcs',
        'qty_input' => 0,
        'conversion_qty' => 1,
        'qty' => 0,
        'unit_price' => 10000,
        'subtotal' => 0,
        'cost_at_sale' => 5000,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_sale_payments_amount menolak amount <= 0', function () {
    $shift = app(CashierShiftService::class)->open($this->branch->id, $this->admin, '0');
    $saleId = seedSaleHeader($this->branch->id, $shift->id, $this->admin->id);

    expect(fn () => DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'method' => 'cash',
        'amount' => 0,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_cash_movements_amount menolak amount <= 0', function () {
    $shift = app(CashierShiftService::class)->open($this->branch->id, $this->admin, '0');

    expect(fn () => DB::table('cash_movements')->insert([
        'cashier_shift_id' => $shift->id,
        'type' => 'in',
        'amount' => -5,
        'reason' => 'test',
        'created_by' => $this->admin->id,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_stock_mutations_cost mewajibkan unit_cost untuk mutasi masuk, tapi tidak untuk mutasi keluar', function () {
    expect(fn () => DB::table('stock_mutations')->insert([
        'branch_id' => $this->branch->id,
        'item_id' => $this->item->id,
        'mutation_date' => now(),
        'mutation_type' => 'adjustment_in',
        'qty' => 5,
        'unit_cost' => null,
        'created_by' => $this->admin->id,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);

    // Kontrol: mutasi keluar boleh unit_cost NULL (constraint tidak berlaku untuk sale_out).
    DB::table('stock_mutations')->insert([
        'branch_id' => $this->branch->id,
        'item_id' => $this->item->id,
        'mutation_date' => now(),
        'mutation_type' => 'sale_out',
        'qty' => -5,
        'unit_cost' => null,
        'created_by' => $this->admin->id,
        'created_at' => now(),
    ]);

    expect(DB::table('stock_mutations')->where('mutation_type', 'sale_out')->count())->toBe(1);
});

test('chk_sad_qty menolak qty <= 0', function () {
    $adjustmentId = seedStockAdjustment($this->branch->id, $this->admin->id);

    expect(fn () => DB::table('stock_adjustment_details')->insert([
        'stock_adjustment_id' => $adjustmentId,
        'item_id' => $this->item->id,
        'qty' => 0,
        'unit_cost' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_transfers_branches menolak from_branch_id sama dengan to_branch_id', function () {
    expect(fn () => seedTransfer($this->branch->id, $this->branch->id))
        ->toThrow(QueryException::class);
});

test('chk_td_qty_sent menolak qty_sent <= 0', function () {
    $branchB = makeBranch();
    $transferId = seedTransfer($this->branch->id, $branchB->id);

    expect(fn () => DB::table('transfer_details')->insert([
        'transfer_id' => $transferId,
        'item_id' => $this->item->id,
        'qty_sent' => 0,
        'qty_received' => 0,
        'unit_cost' => 0,
        'difference_resolution' => 'none',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_td_qty_received menolak qty_received melebihi qty_sent', function () {
    $branchB = makeBranch();
    $transferId = seedTransfer($this->branch->id, $branchB->id);

    expect(fn () => DB::table('transfer_details')->insert([
        'transfer_id' => $transferId,
        'item_id' => $this->item->id,
        'qty_sent' => 5,
        'qty_received' => 6,
        'unit_cost' => 0,
        'difference_resolution' => 'none',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('chk_notes_pembayaran mewajibkan amount terisi dan tidak nol untuk type pembayaran', function () {
    expect(fn () => DB::table('notes')->insert([
        'noteable_type' => 'customer',
        'noteable_id' => 1,
        'type' => 'pembayaran',
        'body' => 'test',
        'amount' => null,
        'created_by' => $this->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('notes')->insert([
        'noteable_type' => 'customer',
        'noteable_id' => 1,
        'type' => 'pembayaran',
        'body' => 'test',
        'amount' => 0,
        'created_by' => $this->admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('main_flag UNIQUE hanya mengizinkan satu is_main = 1', function () {
    // $this->branch dibuat dengan is_main = false; jadikan is_main dulu.
    $this->branch->update(['is_main' => true]);

    expect(fn () => makeBranch(['is_main' => true]))
        ->toThrow(QueryException::class);
});

test('barcode barang tidak boleh dipakai ulang oleh barang lain', function () {
    $itemService = new ItemService();
    makeItem(['barcode' => '8991111111111']);
    $itemB = makeItem();

    expect(fn () => $itemService->update($itemB, ['barcode' => '8991111111111']))
        ->toThrow(ValidationException::class);
});

test('barcode yang dipakai item_units juga tidak boleh dipakai barang lain (unik lintas tabel)', function () {
    $itemService = new ItemService();
    $itemA = makeItem();

    $itemA->units()->create([
        'unit' => 'dus',
        'conversion_qty' => '12',
        'barcode' => '8992222222222',
    ]);

    expect(fn () => $itemService->create([
        'code' => 'ITMBARU',
        'name' => 'Barang Baru',
        'unit' => 'pcs',
        'barcode' => '8992222222222', // sudah dipakai item_units milik $itemA
    ]))->toThrow(ValidationException::class);
});