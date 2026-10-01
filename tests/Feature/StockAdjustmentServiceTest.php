<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\StockAdjustmentService;
use Illuminate\Validation\ValidationException;

if (!function_exists('makeBranch')) {
    function makeBranch(array $attrs = []): Branch
    {
        static $seq = 0;
        $seq++;

        return Branch::create(array_merge([
            'code' => 'BR' . $seq,
            'name' => 'Cabang ' . $seq,
            'is_main' => $seq === 1,
        ], $attrs));
    }
}

if (!function_exists('makeOwner')) {
    function makeOwner(): User
    {
        return User::create([
            'branch_id' => null,
            'name' => 'Owner',
            'email' => 'owner' . uniqid() . '@toko.test',
            'password' => 'password',
            'role' => UserRole::Owner,
        ]);
    }
}

if (!function_exists('makeItem')) {
    function makeItem(array $attrs = []): Item
    {
        static $seq = 0;
        $seq++;

        return Item::create(array_merge([
            'code' => 'ITM' . $seq,
            'name' => 'Barang ' . $seq,
            'unit' => 'pcs',
            'sell_price' => 10000,
        ], $attrs));
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

if (! function_exists('balanceOf')) {
    function balanceOf(int $branchId, int $itemId): ?StockBalance
    {
        return StockBalance::where('branch_id', $branchId)->where('item_id', $itemId)->first();
    }
}

beforeEach(function () {
    $this->service = app(StockAdjustmentService::class);
    $this->branch = makeBranch();
    $this->owner = makeOwner();
    $this->admin = makeAdmin($this->branch->id);
    $this->item = makeItem();
});

test('restock langsung approved dan mutasi stok langsung jalan', function () {
    $adjustment = $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '10', 'unit_cost' => '1500'],
        ],
    ], $this->admin);

    expect($adjustment->status->value)->toBe('approved');
    expect($adjustment->approved_by)->toBe($this->admin->id);

    $balance = balanceOf($this->branch->id, $this->item->id);
    expect($balance->qty)->toEqual('10.0000');
    expect($balance->avg_cost)->toEqual('1500.0000');
});

test('restock tanpa unit_cost ditolak', function () {
    expect(fn() => $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '10'],
        ],
    ], $this->admin))->toThrow(ValidationException::class);

    expect(balanceOf($this->branch->id, $this->item->id))->toBeNull();
});

test('kombinasi reason dan adjustment_type yang salah ditolak', function () {
    expect(fn() => $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in', // damaged cuma boleh 'out'
        'reason' => 'damaged',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '1'],
        ],
    ], $this->admin))->toThrow(ValidationException::class);
});

test('reason correction tetap pending dan belum mengubah saldo stok', function () {
    $adjustment = $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'correction',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '5', 'unit_cost' => '2000'],
        ],
    ], $this->admin);

    expect($adjustment->status->value)->toBe('pending');
    expect($adjustment->approved_by)->toBeNull();
    expect(balanceOf($this->branch->id, $this->item->id))->toBeNull(); // belum ada mutasi
});

test('dokumen pending disetujui admin lain berhasil dan menerapkan mutasi stok', function () {
    $adjustment = $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'correction',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '5', 'unit_cost' => '2000'],
        ],
    ], $this->admin);

    $otherAdmin = makeAdmin($this->branch->id);

    $approved = $this->service->approve($adjustment, $otherAdmin);

    expect($approved->status->value)->toBe('approved');
    expect($approved->approved_by)->toBe($otherAdmin->id);

    $balance = balanceOf($this->branch->id, $this->item->id);
    expect($balance->qty)->toEqual('5.0000');
});

test('penyetuju tidak boleh pembuat dokumen sendiri kecuali owner', function () {
    $adjustment = $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'correction',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '5', 'unit_cost' => '2000'],
        ],
    ], $this->admin);

    expect(fn() => $this->service->approve($adjustment, $this->admin))
        ->toThrow(ValidationException::class);

    expect($adjustment->fresh()->status->value)->toBe('pending');
});

test('owner boleh menyetujui dokumen buatannya sendiri', function () {
    $adjustment = $this->service->create([
        'branch_id' => $this->branch->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'correction',
        'items' => [
            ['item_id' => $this->item->id, 'qty' => '5', 'unit_cost' => '2000'],
        ],
    ], $this->owner);

    $approved = $this->service->approve($adjustment, $this->owner);

    expect($approved->status->value)->toBe('approved');
    expect($approved->approved_by)->toBe($this->owner->id);
});