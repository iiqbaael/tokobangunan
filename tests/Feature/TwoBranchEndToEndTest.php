<?php

use App\Enums\CashMovementType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\CashMovementService;
use App\Services\SaleService;
use App\Services\StockAdjustmentService;
use App\Services\TransferService;

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

test('skenario end-to-end 2 cabang: restock kedua cabang, transfer, jual, tutup shift dengan cash_movements', function () {
    $adjustments = app(StockAdjustmentService::class);
    $transfers = app(TransferService::class);
    $sales = app(SaleService::class);
    $shifts = app(CashierShiftService::class);
    $movements = app(CashMovementService::class);

    $branchMain = makeBranch(); // is_main = true
    $branchTwo = makeBranch();
    $owner = makeOwner();
    $adminMain = makeAdmin($branchMain->id);
    $adminTwo = makeAdmin($branchTwo->id);
    $item = makeItem();

    // 1) Restock di kedua cabang (reason=restock -> auto-approved, ERD §5 no. 15).
    $adjustments->create([
        'branch_id' => $branchMain->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [['item_id' => $item->id, 'qty' => '100', 'unit_cost' => '5000']],
    ], $adminMain);

    $adjustments->create([
        'branch_id' => $branchTwo->id,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'items' => [['item_id' => $item->id, 'qty' => '50', 'unit_cost' => '6000']],
    ], $adminTwo);

    expect(balanceOf($branchMain->id, $item->id)->qty)->toEqual('100.0000');
    expect(balanceOf($branchTwo->id, $item->id)->qty)->toEqual('50.0000');

    // 2) Transfer 20 unit dari Cabang Utama ke Cabang 2.
    $transfer = $transfers->create([
        'from_branch_id' => $branchMain->id,
        'to_branch_id' => $branchTwo->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['item_id' => $item->id, 'qty_sent' => '20']],
    ], $adminMain);

    $transfer = $transfers->send($transfer, $adminMain);
    $detail = $transfer->details->first();
    $transfer = $transfers->receive($transfer, [$detail->id => '20'], $adminTwo);

    expect($transfer->status->value)->toBe('received');

    $mainAfterTransfer = balanceOf($branchMain->id, $item->id);
    expect($mainAfterTransfer->qty)->toEqual('80.0000');
    expect($mainAfterTransfer->avg_cost)->toEqual('5000.0000'); // tidak berubah oleh mutasi keluar

    $twoAfterTransfer = balanceOf($branchTwo->id, $item->id);
    expect($twoAfterTransfer->qty)->toEqual('70.0000');
    // (50 x 6000 + 20 x 5000) / 70 = 5714.2857
    expect($twoAfterTransfer->avg_cost)->toEqual('5714.2857');

    // 3) Buka shift di kedua cabang, lalu jual.
    $cashierMain = makeAdmin($branchMain->id); // pakai admin sekalian jadi kasir (owner boleh transaksi, admin juga)
    $cashierTwo = makeAdmin($branchTwo->id);

    $shiftMain = $shifts->open($branchMain->id, $cashierMain, '100000');
    $shiftTwo = $shifts->open($branchTwo->id, $cashierTwo, '50000');

    $saleMain = $sales->create($branchMain->id, [
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '5']],
        'payments' => [['method' => 'cash', 'amount' => '50000']],
        'cash_received' => '50000',
    ], $cashierMain);

    $saleTwo = $sales->create($branchTwo->id, [
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '30000']],
        'cash_received' => '30000',
    ], $cashierTwo);

    expect($saleMain->details->first()->cost_at_sale)->toEqual('5000.0000');
    expect($saleTwo->details->first()->cost_at_sale)->toEqual('5714.2857');

    expect(balanceOf($branchMain->id, $item->id)->qty)->toEqual('75.0000'); // 80 - 5
    expect(balanceOf($branchTwo->id, $item->id)->qty)->toEqual('67.0000'); // 70 - 3

    // 4) Catat uang masuk/keluar laci di kedua cabang.
    $movements->record($branchMain->id, $cashierMain, CashMovementType::In, '20000', 'setor_brankas');
    $movements->record($branchMain->id, $cashierMain, CashMovementType::Out, '5000', 'beli_operasional');

    $movements->record($branchTwo->id, $cashierTwo, CashMovementType::In, '10000', 'setor_brankas');
    $movements->record($branchTwo->id, $cashierTwo, CashMovementType::Out, '2000', 'beli_operasional');

    // 5) Tutup shift kedua cabang, verifikasi expected_balance & difference.
    $closedMain = $shifts->close($shiftMain, $cashierMain, '165000');
    $closedTwo = $shifts->close($shiftTwo, $cashierTwo, '88000');

    // Main: 100000 + 50000 (cash sale) + 20000 (in) - 5000 (out) = 165000
    expect($closedMain->expected_balance)->toEqual('165000.00');
    expect($closedMain->difference)->toEqual('0.00');

    // Two: 50000 + 30000 (cash sale) + 10000 (in) - 2000 (out) = 88000
    expect($closedTwo->expected_balance)->toEqual('88000.00');
    expect($closedTwo->difference)->toEqual('0.00');
});