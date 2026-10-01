<?php

/*
 * LOKASI : <root proyek Laravel>/tes_pos_piutang.php   (sejajar dengan file `artisan`)
 * JALANKAN: php artisan tinker tes_pos_piutang.php
 *
 * Tes POS + Piutang (SaleService + ReceivableService). Semua data dibuat di dalam
 * satu transaksi lalu di-ROLLBACK di akhir, jadi database tidak kotor.
 * Setelah lolos, file ini boleh dihapus.
 */

use App\Enums\PaymentMethod;
use App\Enums\StockMutationType;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\CashierShiftService;
use App\Services\ItemService;
use App\Services\ReceivableService;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

$check = function (string $label, bool $ok, string $extra = '') use (&$pass, &$fail) {
    $ok ? $pass++ : $fail++;
    echo ($ok ? '[LOLOS] ' : '[GAGAL] ') . $label . ($extra !== '' ? "  -> {$extra}" : '') . PHP_EOL;
};

$expectFail = function (string $label, callable $fn) use ($check) {
    try {
        $fn();
        $check($label, false, 'seharusnya ditolak, tapi lolos');
    } catch (Throwable $e) {
        $msg = $e instanceof \Illuminate\Validation\ValidationException
            ? collect($e->errors())->flatten()->first()
            : $e->getMessage();
        $check($label, true, 'ditolak: ' . mb_substr((string) $msg, 0, 90));
    }
};

DB::beginTransaction();

try {
    $branchId = (int) DB::table('branches')->where('code', 'CBU')->value('id');
    $owner = User::where('email', 'owner@toko.test')->firstOrFail();
    $admin = User::where('email', 'admin@toko.test')->firstOrFail();
    $kasir = User::where('email', 'kasir@toko.test')->firstOrFail();

    $shiftSvc = app(CashierShiftService::class);
    $saleSvc = app(SaleService::class);
    $recvSvc = app(ReceivableService::class);

    // --- Persiapan: barang, stok, pelanggan, shift ---
    $item = app(ItemService::class)->create([
        'code' => 'TES-' . uniqid(),
        'name' => 'Barang Tes POS',
        'unit' => 'pcs',
        'sell_price' => '10000',
    ]);

    DB::transaction(fn () => app(StockService::class)->applyIn(
        $branchId, $item->id, '100', '6000', StockMutationType::AdjustmentIn, $admin->id
    ));

    $customer = Customer::create(['name' => 'Pelanggan Tes', 'is_active' => true]);

    $shift = $shiftSvc->openShiftForUpdate($branchId, shared: false);
    $weOpened = false;
    if ($shift === null) {
        $shift = $shiftSvc->open($branchId, $kasir, '100000');
        $weOpened = true;
    }
    echo "Shift #{$shift->id} (" . ($weOpened ? 'dibuka skrip' : 'sudah ada sebelumnya') . ")" . PHP_EOL . PHP_EOL;

    $stok = fn () => (string) StockBalance::where('branch_id', $branchId)->where('item_id', $item->id)->value('qty');
    $saldo = fn () => $recvSvc->balance($customer->fresh());

    // 1. Jual tunai oleh kasir
    $s1 = $saleSvc->create($branchId, [
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '30000']],
        'cash_received' => '50000',
    ], $kasir);
    $check('1a. Jual tunai: total 30000', bccomp((string) $s1->total, '30000', 2) === 0, (string) $s1->total);
    $check('1b. Kembalian 20000', bccomp((string) $s1->change_given, '20000', 2) === 0, (string) $s1->change_given);
    $check('1c. cost_at_sale = 6000', bccomp((string) $s1->details->first()->cost_at_sale, '6000', 4) === 0);
    $check('1d. Stok jadi 97', bccomp($stok(), '97', 4) === 0, $stok());

    // 2. Kasir dilarang kredit
    $expectFail('2. Kasir memilih kredit ditolak', fn () => $saleSvc->create($branchId, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '1']],
        'payments' => [['method' => 'credit', 'amount' => '10000']],
    ], $kasir));

    // 3. Total pembayaran tidak sama
    $expectFail('3. Total bayar tidak sama dengan total nota ditolak', fn () => $saleSvc->create($branchId, [
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '1']],
        'payments' => [['method' => 'transfer', 'amount' => '9000']],
    ], $kasir));
    $check('3b. Stok tetap 97 (transaksi gagal ter-rollback)', bccomp($stok(), '97', 4) === 0, $stok());

    // 4. Kredit oleh Admin tanpa pelanggan
    $expectFail('4. Kredit tanpa pelanggan ditolak', fn () => $saleSvc->create($branchId, [
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '1']],
        'payments' => [['method' => 'credit', 'amount' => '10000']],
    ], $admin));

    // 5. Kredit penuh oleh Admin (2 pcs = 20000)
    $s5 = $saleSvc->create($branchId, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '2']],
        'payments' => [['method' => 'credit', 'amount' => '20000']],
    ], $admin);
    $check('5a. created_by = Admin', (int) $s5->created_by === $admin->id);
    $check('5b. Saldo piutang 20000', bccomp($saldo(), '20000', 2) === 0, $saldo());

    // 6. Bayar campuran tunai 10000 + kredit 10000 (Owner)
    $s6 = $saleSvc->create($branchId, [
        'customer_id' => $customer->id,
        'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '2']],
        'payments' => [
            ['method' => 'cash', 'amount' => '10000'],
            ['method' => 'credit', 'amount' => '10000'],
        ],
        'cash_received' => '10000',
    ], $owner);
    $check('6. Saldo piutang jadi 30000', bccomp($saldo(), '30000', 2) === 0, $saldo());

    // 7. Pelunasan tunai 15000 oleh Admin
    $recvSvc->pay($customer, $admin, '15000', true, $branchId, 'Bayar sebagian');
    $check('7a. Saldo jadi 15000', bccomp($saldo(), '15000', 2) === 0, $saldo());
    $check('7b. cash_movements IN pelunasan_piutang tercatat',
        CashMovement::where('cashier_shift_id', $shift->id)->where('reason', 'pelunasan_piutang')->where('type', 'in')->exists());

    // 8. Pelunasan melebihi saldo & oleh kasir
    $expectFail('8a. Pelunasan melebihi saldo ditolak', fn () => $recvSvc->pay($customer, $admin, '999999', false));
    $expectFail('8b. Kasir melunasi piutang ditolak', fn () => $recvSvc->pay($customer, $kasir, '1000', false));

    // 9. Void nota kredit 20000 saat saldo 15000 -> harus ditolak
    $expectFail('9. Void nota kredit yang bikin saldo negatif ditolak', fn () => $saleSvc->void($s5, $admin, 'salah input'));

    // 10. Koreksi pelunasan (baris pembalik)
    $recvSvc->correct($customer, $admin, '15000', true, $branchId, 'Salah catat');
    $check('10a. Saldo kembali 30000', bccomp($saldo(), '30000', 2) === 0, $saldo());
    $check('10b. cash_movements OUT koreksi_pelunasan tercatat',
        CashMovement::where('cashier_shift_id', $shift->id)->where('reason', 'koreksi_pelunasan')->where('type', 'out')->exists());

    // 11. Void nota kredit sekarang boleh
    $stokSebelum = $stok();
    $v = $saleSvc->void($s5, $admin, 'salah input');
    $check('11a. Status void', $v->status->value === 'void');
    $check('11b. Stok kembali +2', bccomp($stok(), bcadd($stokSebelum, '2', 4), 4) === 0, $stok());
    $check('11c. Saldo jadi 10000', bccomp($saldo(), '10000', 2) === 0, $saldo());

    // 12. Void dobel
    $expectFail('12. Void dua kali ditolak', fn () => $saleSvc->void($s5, $admin, 'lagi'));

    // 13. Tutup shift, lalu void & pelunasan tunai harus ditolak
    if ($weOpened) {
        // expected = 100000 + 30000 (s1) + 10000 (s6 tunai) + 15000 (in) - 15000 (out) = 140000
        $closed = $shiftSvc->close($shift->fresh(), $kasir, '140000');
        $check('13a. expected_balance = 140000', bccomp((string) $closed->expected_balance, '140000', 2) === 0, (string) $closed->expected_balance);
        $check('13b. difference = 0', bccomp((string) $closed->difference, '0', 2) === 0, (string) $closed->difference);
        $check('13c. closed_by terisi', (int) $closed->closed_by === $kasir->id);

        $expectFail('13d. Void setelah shift tutup ditolak', fn () => $saleSvc->void($s1, $admin, 'telat'));
        $expectFail('13e. Pelunasan tunai tanpa shift open ditolak', fn () => $recvSvc->pay($customer, $admin, '1000', true, $branchId));
        $expectFail('13f. Jual tanpa shift open ditolak', fn () => $saleSvc->create($branchId, [
            'items' => [['item_id' => $item->id, 'unit' => 'pcs', 'qty_input' => '1']],
            'payments' => [['method' => 'transfer', 'amount' => '10000']],
        ], $kasir));
    } else {
        echo '[LEWAT] Langkah 13 dilewati (shift sudah ada sebelumnya, tidak ditutup oleh skrip).' . PHP_EOL;
    }
} catch (Throwable $e) {
    $fail++;
    echo '[ERROR] ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL
        . '        di ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
} finally {
    DB::rollBack();
}

echo PHP_EOL . "HASIL: {$pass} lolos, {$fail} gagal. (Semua data tes di-rollback.)" . PHP_EOL;