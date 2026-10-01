<?php

use App\Enums\CashMovementType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\CashMovementService;
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

/** Insert nota penjualan minimal langsung ke DB (bukan lewat SaleService, belum dibuat filenya). */
function seedSale(int $branchId, int $shiftId, int $createdBy, string $total, string $method, string $amount): int
{
    static $seq = 0;
    $seq++;

    $saleId = DB::table('sales_headers')->insertGetId([
        'branch_id' => $branchId,
        'cashier_shift_id' => $shiftId,
        'invoice_no' => 'INV-TEST-'.$seq.'-'.uniqid(),
        'sale_date' => now(),
        'total' => $total,
        'status' => 'completed',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('sale_payments')->insert([
        'sale_id' => $saleId,
        'method' => $method,
        'amount' => $amount,
        'created_at' => now(),
    ]);

    return $saleId;
}

beforeEach(function () {
    $this->shiftService = new CashierShiftService();
    $this->movementService = new CashMovementService();
    $this->branch = makeBranch();
    $this->owner = makeOwner();
    $this->cashierA = makeCashier($this->branch->id);
});

test('satu shift open per cabang, shift kedua di cabang yang sama ditolak', function () {
    $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    $cashierB = makeCashier($this->branch->id);

    expect(fn () => $this->shiftService->open($this->branch->id, $cashierB, '50000'))
        ->toThrow(RuntimeException::class);
});

test('expected_balance dihitung dari opening + penjualan cash + cash_movements in/out', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    seedSale($this->branch->id, $shift->id, $this->cashierA->id, '50000', 'cash', '50000');

    $this->movementService->record($this->branch->id, $this->cashierA, CashMovementType::In, '20000', 'setor_brankas');
    $this->movementService->record($this->branch->id, $this->cashierA, CashMovementType::Out, '5000', 'beli_operasional');

    $closed = $this->shiftService->close($shift, $this->cashierA, '165000');

    expect($closed->expected_balance)->toEqual('165000.00');
    expect($closed->difference)->toEqual('0.00');
});

test('pembayaran non-cash tidak masuk hitungan expected_balance', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    seedSale($this->branch->id, $shift->id, $this->cashierA->id, '30000', 'transfer', '30000');

    $closed = $this->shiftService->close($shift, $this->cashierA, '100000');

    expect($closed->expected_balance)->toEqual('100000.00'); // transfer diabaikan
    expect($closed->difference)->toEqual('0.00');
});

test('tutup paksa oleh admin cabang berhasil dan closed_by terisi', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashierA, '100000');
    $admin = makeAdmin($this->branch->id);

    $closed = $this->shiftService->close($shift, $admin, '100000');

    expect($closed->status->value)->toBe('closed');
    expect($closed->closed_by)->toBe($admin->id);
});

test('tutup paksa ditolak untuk admin cabang lain', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    $otherBranch = makeBranch();
    $otherAdmin = makeAdmin($otherBranch->id);

    expect(fn () => $this->shiftService->close($shift, $otherAdmin, '100000'))
        ->toThrow(RuntimeException::class);

    expect($shift->fresh()->status->value)->toBe('open');
});

test('kasir lain bisa mencatat cash_movements di shift yang dibuka kasir lain', function () {
    $shift = $this->shiftService->open($this->branch->id, $this->cashierA, '100000');
    $cashierB = makeCashier($this->branch->id);

    $movement = $this->movementService->record($this->branch->id, $cashierB, CashMovementType::In, '10000', 'setor_brankas');

    expect($movement->cashier_shift_id)->toBe($shift->id);
    expect($movement->created_by)->toBe($cashierB->id);
});

test('cash_movements ditolak jika tidak ada shift open di cabang itu', function () {
    expect(fn () => $this->movementService->record($this->branch->id, $this->cashierA, CashMovementType::In, '10000', 'setor_brankas'))
        ->toThrow(RuntimeException::class);
});

test('amount cash_movement harus lebih dari 0', function () {
    $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    expect(fn () => $this->movementService->record($this->branch->id, $this->cashierA, CashMovementType::In, '0', 'setor_brankas'))
        ->toThrow(InvalidArgumentException::class);
});

test('reason cash_movement wajib diisi', function () {
    $this->shiftService->open($this->branch->id, $this->cashierA, '100000');

    expect(fn () => $this->movementService->record($this->branch->id, $this->cashierA, CashMovementType::In, '10000', '   '))
        ->toThrow(InvalidArgumentException::class);
});