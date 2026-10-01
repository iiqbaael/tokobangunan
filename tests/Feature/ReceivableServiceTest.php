<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\ReceivableService;
use Illuminate\Support\Facades\DB;

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

if (! function_exists('makeCustomer')) {
    function makeCustomer(): Customer
    {
        static $seq = 0;
        $seq++;

        return Customer::create(['name' => 'Pelanggan '.$seq]);
    }
}

/** Insert nota kredit minimal langsung ke DB (bukan lewat SaleService, tidak relevan di sini). */
function seedCreditSale(int $branchId, int $shiftId, int $customerId, int $createdBy, string $amount): void
{
    static $seq = 0;
    $seq++;

    $saleId = DB::table('sales_headers')->insertGetId([
        'branch_id' => $branchId,
        'cashier_shift_id' => $shiftId,
        'customer_id' => $customerId,
        'invoice_no' => 'INV-CR-'.$seq.'-'.uniqid(),
        'sale_date' => now(),
        'total' => $amount,
        'status' => 'completed',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'method' => 'credit',
        'amount' => $amount,
        'created_at' => now(),
    ]);
}

beforeEach(function () {
    $this->service = app(ReceivableService::class);
    $this->shiftService = app(CashierShiftService::class);
    $this->branch = makeBranch();
    $this->owner = makeOwner();
    $this->admin = makeAdmin($this->branch->id);
    $this->cashier = makeCashier($this->branch->id);
    $this->customer = makeCustomer();
});

test('saldo piutang dihitung dari sale_payments method credit', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    expect($this->service->balance($this->customer))->toEqual('50000.00');
});

test('pelunasan mengurangi saldo piutang', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    $this->service->pay($this->customer, $this->admin, '20000', cash: false);

    expect($this->service->balance($this->customer))->toEqual('30000.00');
});

test('pelunasan melebihi saldo ditolak', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    expect(fn () => $this->service->pay($this->customer, $this->admin, '60000', cash: false))
        ->toThrow(RuntimeException::class);

    expect($this->service->balance($this->customer))->toEqual('50000.00'); // tidak berubah
});

test('kasir tidak berhak mengelola pelunasan piutang', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    expect(fn () => $this->service->pay($this->customer, $this->cashier, '10000', cash: false))
        ->toThrow(RuntimeException::class);
});

test('pelunasan tunai atomik: notes dan cash_movements tercatat sekaligus', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    $note = $this->service->pay($this->customer, $this->admin, '20000', cash: true, branchId: $this->branch->id);

    expect($note->amount)->toEqual('20000.00');

    $movement = CashMovement::where('cashier_shift_id', $shift->id)
        ->where('reason', 'pelunasan_piutang')
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->type->value)->toBe('in');
    expect($movement->amount)->toEqual('20000.00');
});

test('pelunasan tunai ditolak jika tidak ada shift open di cabang', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    $this->shiftService->close($shift, $this->admin, '0');

    expect(fn () => $this->service->pay($this->customer, $this->admin, '20000', cash: true, branchId: $this->branch->id))
        ->toThrow(RuntimeException::class);
});

test('koreksi pelunasan mencatat baris negatif dan mengembalikan saldo', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    $this->service->pay($this->customer, $this->admin, '20000', cash: false);
    expect($this->service->balance($this->customer))->toEqual('30000.00');

    $correction = $this->service->correct($this->customer, $this->admin, '20000', wasCash: false);

    expect($correction->amount)->toEqual('-20000.00');
    expect($this->service->balance($this->customer))->toEqual('50000.00'); // kembali seperti semula
});

test('koreksi pelunasan tunai mencatat cash_movements type out', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    $this->service->pay($this->customer, $this->admin, '20000', cash: true, branchId: $this->branch->id);
    $this->service->correct($this->customer, $this->admin, '20000', wasCash: true, branchId: $this->branch->id);

    $movement = CashMovement::where('cashier_shift_id', $shift->id)
        ->where('reason', 'koreksi_pelunasan')
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->type->value)->toBe('out');
    expect($movement->amount)->toEqual('20000.00');
});

test('amount pelunasan harus lebih dari 0', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashier, '0');
    seedCreditSale($this->branch->id, $shift->id, $this->customer->id, $this->admin->id, '50000');

    expect(fn () => $this->service->pay($this->customer, $this->admin, '0', cash: false))
        ->toThrow(InvalidArgumentException::class);
});