<?php

use App\Enums\StockMutationType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\ItemService;
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

beforeEach(function () {
    $this->service = new StockService();
    $this->branch = makeBranch();
    $this->user = makeOwner();
    $this->item = makeItem();
});

test('stok masuk pertama memakai unit_cost sebagai avg_cost', function () {
    DB::transaction(function () {
        $this->service->applyIn(
            $this->branch->id,
            $this->item->id,
            '10',
            '1000',
            StockMutationType::AdjustmentIn,
            $this->user->id,
        );
    });

    $balance = StockBalance::where('branch_id', $this->branch->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($balance->qty)->toEqual('10.0000');
    expect($balance->avg_cost)->toEqual('1000.0000');
});

test('moving average dihitung presisi 4 desimal untuk stok masuk kedua', function () {
    DB::transaction(function () {
        // 10 @ 1000
        $this->service->applyIn($this->branch->id, $this->item->id, '10', '1000', StockMutationType::AdjustmentIn, $this->user->id);
        // +3 @ 1333.3333 -> avg baru = (10*1000 + 3*1333.3333) / 13 = 13999.9999 / 13 = 1076.9230(69...)
        $this->service->applyIn($this->branch->id, $this->item->id, '3', '1333.3333', StockMutationType::AdjustmentIn, $this->user->id);
    });

    $balance = StockBalance::where('branch_id', $this->branch->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($balance->qty)->toEqual('13.0000');
    expect($balance->avg_cost)->toEqual('1076.9231'); // round half up di desimal ke-4
});

test('stok keluar mengurangi qty tapi tidak mengubah avg_cost', function () {
    DB::transaction(function () {
        $this->service->applyIn($this->branch->id, $this->item->id, '10', '1000', StockMutationType::AdjustmentIn, $this->user->id);
        $mutation = $this->service->applyOut($this->branch->id, $this->item->id, '4', StockMutationType::SaleOut, $this->user->id);

        expect($mutation->qty)->toEqual('-4.0000');
        expect($mutation->unit_cost)->toEqual('1000.0000'); // snapshot avg_cost saat keluar
    });

    $balance = StockBalance::where('branch_id', $this->branch->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($balance->qty)->toEqual('6.0000');
    expect($balance->avg_cost)->toEqual('1000.0000'); // tidak berubah
});

test('stok keluar ditolak jika membuat saldo negatif', function () {
    DB::transaction(function () {
        $this->service->applyIn($this->branch->id, $this->item->id, '5', '1000', StockMutationType::AdjustmentIn, $this->user->id);
    });

    expect(fn () => DB::transaction(function () {
        $this->service->applyOut($this->branch->id, $this->item->id, '6', StockMutationType::SaleOut, $this->user->id);
    }))->toThrow(RuntimeException::class);

    $balance = StockBalance::where('branch_id', $this->branch->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($balance->qty)->toEqual('5.0000'); // saldo tidak berubah, transaksi dibatalkan
});

test('stok keluar diizinkan negatif saat allow_negative_stock aktif', function () {
    config(['toko.allow_negative_stock' => true]);

    DB::transaction(function () {
        $this->service->applyIn($this->branch->id, $this->item->id, '5', '1000', StockMutationType::AdjustmentIn, $this->user->id);
        $this->service->applyOut($this->branch->id, $this->item->id, '8', StockMutationType::SaleOut, $this->user->id);
    });

    $balance = StockBalance::where('branch_id', $this->branch->id)
        ->where('item_id', $this->item->id)
        ->first();

    expect($balance->qty)->toEqual('-3.0000');

    config(['toko.allow_negative_stock' => false]);
});

test('items.unit tidak bisa diubah setelah ada mutasi stok', function () {
    DB::transaction(function () {
        $this->service->applyIn($this->branch->id, $this->item->id, '1', '1000', StockMutationType::AdjustmentIn, $this->user->id);
    });

    $itemService = new ItemService();

    expect(fn () => $itemService->update($this->item, ['unit' => 'dus']))
        ->toThrow(ValidationException::class);

    expect($this->item->refresh()->unit)->toBe('pcs');
});

test('items.unit masih bisa diubah selama belum ada mutasi stok', function () {
    $itemService = new ItemService();

    $updated = $itemService->update($this->item, ['unit' => 'dus']);

    expect($updated->unit)->toBe('dus');
});