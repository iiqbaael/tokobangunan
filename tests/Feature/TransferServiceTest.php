<?php

use App\Enums\DifferenceResolution;
use App\Enums\StockMutationType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Services\TransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

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

/** Isi stok langsung lewat StockService, tanpa lewat StockAdjustmentService. */
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

beforeEach(function () {
    $this->service = app(TransferService::class);
    $this->branchA = makeBranch(); // asal
    $this->branchB = makeBranch(); // tujuan
    $this->owner = makeOwner();
    $this->adminA = makeAdmin($this->branchA->id);
    $this->item = makeItem();
});

test('transfer history remains available when a referenced item is soft deleted', function () {
    $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '2']],
    ], $this->owner);

    $this->item->delete();

    $this->actingAs($this->owner)
        ->get(route('transfers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Transfers/Index')
            ->where('transfers.data.0.items.0.item_name', $this->item->name)
            ->where('transfers.data.0.items.0.unit', $this->item->unit));
});

test('from_branch_id dan to_branch_id tidak boleh sama', function () {
    expect(fn () => $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchA->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '5']],
    ], $this->adminA))->toThrow(ValidationException::class);
});

test('send mengunci unit_cost dari avg_cost cabang asal dan tidak mengubah avg_cost asal', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '4']],
    ], $this->adminA);

    $sent = $this->service->send($transfer, $this->adminA);

    expect($sent->status->value)->toBe('sent');
    expect($sent->details->first()->unit_cost)->toEqual('1200.0000');

    $balanceA = balanceOf($this->branchA->id, $this->item->id);
    expect($balanceA->qty)->toEqual('6.0000');
    expect($balanceA->avg_cost)->toEqual('1200.0000'); // tidak berubah oleh mutasi keluar
});

test('avg_cost tujuan menjadi sama dengan unit_cost terkunci saat stok tujuan masih nol', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '4']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);
    $detail = $transfer->details->first();

    $received = $this->service->receive($transfer, [$detail->id => '4'], $this->adminA);

    expect($received->status->value)->toBe('received');

    $balanceB = balanceOf($this->branchB->id, $this->item->id);
    expect($balanceB->qty)->toEqual('4.0000');
    expect($balanceB->avg_cost)->toEqual('1200.0000');
});

test('avg_cost tujuan dihitung moving average saat stok tujuan sudah ada', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);
    seedStock($this->branchB->id, $this->item->id, '6', '1000', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '4']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);
    $detail = $transfer->details->first();

    $this->service->receive($transfer, [$detail->id => '4'], $this->adminA);

    // (6*1000 + 4*1200) / 10 = 1080
    $balanceB = balanceOf($this->branchB->id, $this->item->id);
    expect($balanceB->qty)->toEqual('10.0000');
    expect($balanceB->avg_cost)->toEqual('1080.0000');
});

test('qty_received tidak boleh melebihi qty_sent', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '4']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);
    $detail = $transfer->details->first();

    expect(fn () => $this->service->receive($transfer, [$detail->id => '5'], $this->adminA))
        ->toThrow(ValidationException::class);

    expect($detail->fresh()->qty_received)->toEqual('0.0000');
});

test('selisih returned mencatat mutasi masuk ke cabang asal dan dokumen jadi received', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '10']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);
    $detail = $transfer->details->first();

    $transfer = $this->service->receive($transfer, [$detail->id => '6'], $this->adminA);
    expect($transfer->status->value)->toBe('partial');

    $resolved = $this->service->resolveDifference($detail->fresh(), DifferenceResolution::Returned, $this->adminA);

    expect($resolved->difference_resolution->value)->toBe('returned');
    expect($transfer->fresh()->status->value)->toBe('received');

    // sisa 4 kembali ke cabang asal: 10 (awal) - 10 (kirim semua) + 4 (kembali) = 4
    $balanceA = balanceOf($this->branchA->id, $this->item->id);
    expect($balanceA->qty)->toEqual('4.0000');
});

test('selisih written_off tidak menambah mutasi apa pun tapi dokumen tetap jadi received', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '10']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);
    $detail = $transfer->details->first();

    $this->service->receive($transfer, [$detail->id => '6'], $this->adminA);

    $this->service->resolveDifference($detail->fresh(), DifferenceResolution::WrittenOff, $this->adminA);

    expect($transfer->fresh()->status->value)->toBe('received');

    // Tidak ada barang kembali: 10 (awal) - 10 (kirim) = 0
    $balanceA = balanceOf($this->branchA->id, $this->item->id);
    expect($balanceA->qty)->toEqual('0.0000');
});

test('barang dari transfer sent tidak bisa direstock di cabang tujuan sebelum received', function () {
    seedStock($this->branchA->id, $this->item->id, '10', '1200', $this->owner->id);

    $transfer = $this->service->create([
        'from_branch_id' => $this->branchA->id,
        'to_branch_id' => $this->branchB->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $this->item->id, 'qty_sent' => '5']],
    ], $this->adminA);

    $transfer = $this->service->send($transfer, $this->adminA);

    $adminB = makeAdmin($this->branchB->id);
    $adjustmentService = app(StockAdjustmentService::class);

    expect(fn () => $adjustmentService->create([
        'branch_id' => $this->branchB->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [['item_id' => $this->item->id, 'qty' => '5', 'unit_cost' => '1200']],
    ], $adminB))->toThrow(ValidationException::class);

    // Setelah diterima penuh, restock boleh lagi.
    $detail = $transfer->details->first();
    $this->service->receive($transfer, [$detail->id => '5'], $this->adminA);

    $adjustment = $adjustmentService->create([
        'branch_id' => $this->branchB->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [['item_id' => $this->item->id, 'qty' => '2', 'unit_cost' => '1200']],
    ], $adminB);

    expect($adjustment->status->value)->toBe('approved');
});