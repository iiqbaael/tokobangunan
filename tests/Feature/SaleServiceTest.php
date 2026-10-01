<?php

use App\Enums\StockMutationType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\ReceivableService;
use App\Services\SaleService;
use App\Services\StockService;
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
            'is_main' => $seq === 1,
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

if (! function_exists('makeCashier')) {
    function makeCashier(int $branchId): User
    {
        static $seq = 0;
        $seq++;

        return User::create([
            'branch_id' => $branchId,
            'name' => 'Kasir '.$seq,
            'email' => 'kasir'.uniqid().'@toko.test',
            'password' => 'password',
            'role' => UserRole::Cashier,
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

if (! function_exists('balanceOf')) {
    function balanceOf(int $branchId, int $itemId): ?StockBalance
    {
        return StockBalance::where('branch_id', $branchId)->where('item_id', $itemId)->first();
    }
}

if (! function_exists('seedStock')) {
    function seedStock(int $branchId, int $itemId, string $qty, string $cost, int $userId): void
    {
        DB::transaction(function () use ($branchId, $itemId, $qty, $cost, $userId) {
            app(StockService::class)->applyIn(
                $branchId, $itemId, $qty, $cost, StockMutationType::AdjustmentIn, $userId
            );
        });
    }
}

if (! function_exists('makeCustomer')) {
    function makeCustomer(): Customer
    {
        static $seq = 0;
        $seq++;

        return Customer::create(['name' => 'Pelanggan '.$seq]);
    }
}

beforeEach(function () {
    $this->saleService = app(SaleService::class);
    $this->shiftService = app(CashierShiftService::class);
    $this->receivables = app(ReceivableService::class);
    $this->branch = makeBranch();
    $this->owner = makeOwner();
    $this->admin = makeAdmin($this->branch->id);
    $this->cashier = makeCashier($this->branch->id);
    $this->item = makeItem();
});

test('penjualan ditolak jika tidak ada shift open di cabang', function () {
    expect(fn () => $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '10000']],
        'cash_received' => '10000',
    ], $this->cashier))->toThrow(ValidationException::class);
});

test('penjualan satuan dasar: total sama dengan subtotal dan stok berkurang', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '100000');

    $sale = $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '30000']],
        'cash_received' => '30000',
    ], $this->cashier);

    expect($sale->total)->toEqual('30000.00');
    expect($sale->change_given)->toEqual('0.00');
    expect($sale->details->first()->cost_at_sale)->toEqual('5000.0000');

    $balance = balanceOf($this->branch->id, $this->item->id);
    expect($balance->qty)->toEqual('17.0000');
});

test('konversi satuan alternatif dihitung dengan benar', function () {
    ItemUnit::create([
        'item_id' => $this->item->id,
        'unit' => 'dus',
        'conversion_qty' => '12',
        'sell_price' => null, // otomatis = 10000 * 12
    ]);

    seedStock($this->branch->id, $this->item->id, '50', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');

    $sale = $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'dus', 'qty_input' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '120000']],
        'cash_received' => '120000',
    ], $this->cashier);

    $detail = $sale->details->first();
    expect($detail->conversion_qty)->toEqual('12.0000');
    expect($detail->qty)->toEqual('12.0000'); // qty satuan dasar
    expect($detail->unit_price)->toEqual('120000.00'); // 10000 x 12
    expect($sale->total)->toEqual('120000.00');

    $balance = balanceOf($this->branch->id, $this->item->id);
    expect($balance->qty)->toEqual('38.0000'); // 50 - 12
});

test('total pembayaran harus sama persis dengan total nota', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');

    expect(fn () => $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '25000']], // kurang dari 30000
        'cash_received' => '25000',
    ], $this->cashier))->toThrow(ValidationException::class);
});

test('kasir tidak bisa memakai metode pembayaran credit', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');

    $customer = makeCustomer();

    expect(fn () => $this->saleService->create($this->branch->id, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'credit', 'amount' => '30000']],
    ], $this->cashier))->toThrow(ValidationException::class);
});

test('penjualan kredit oleh admin wajib customer_id dan created_by tercatat admin', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');

    expect(fn () => $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'credit', 'amount' => '30000']],
    ], $this->admin))->toThrow(ValidationException::class); // tanpa customer_id

    $customer = makeCustomer();

    $sale = $this->saleService->create($this->branch->id, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'credit', 'amount' => '30000']],
    ], $this->admin);

    expect($sale->created_by)->toBe($this->admin->id);
    expect($this->receivables->balance($customer))->toEqual('30000.00');
});

test('void mengembalikan stok pada cost_at_sale', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');

    $sale = $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '30000']],
        'cash_received' => '30000',
    ], $this->cashier);

    expect(balanceOf($this->branch->id, $this->item->id)->qty)->toEqual('17.0000');

    $voided = $this->saleService->void($sale, $this->admin, 'Salah input');

    expect($voided->status->value)->toBe('void');
    expect($voided->voided_by)->toBe($this->admin->id);

    $balance = balanceOf($this->branch->id, $this->item->id);
    expect($balance->qty)->toEqual('20.0000'); // kembali penuh
    expect($balance->avg_cost)->toEqual('5000.0000'); // cost_at_sale = avg_cost saat itu
});

test('void ditolak kalau shift transaksi sudah ditutup', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');

    $sale = $this->saleService->create($this->branch->id, [
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '30000']],
        'cash_received' => '30000',
    ], $this->cashier);

    $this->shiftService->close($shift, $this->admin, '30000');

    expect(fn () => $this->saleService->void($sale, $this->admin, 'Terlambat'))
        ->toThrow(ValidationException::class);
});

test('void nota kredit ditolak jika membuat saldo piutang pelanggan negatif', function () {
    seedStock($this->branch->id, $this->item->id, '20', '5000', $this->owner->id);
    $this->shiftService->open($this->branch->id, $this->cashier, '0');
    $customer = makeCustomer();

    $sale = $this->saleService->create($this->branch->id, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $this->item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'credit', 'amount' => '30000']],
    ], $this->admin);

    // Lunasi penuh dulu -> saldo jadi 0
    $this->receivables->pay($customer, $this->admin, '30000', cash: false);
    expect($this->receivables->balance($customer))->toEqual('0.00');

    expect(fn () => $this->saleService->void($sale, $this->admin, 'Batal'))
        ->toThrow(ValidationException::class);

    expect($sale->fresh()->status->value)->toBe('completed');
});