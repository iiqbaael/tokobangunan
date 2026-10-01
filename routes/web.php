<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\GrossProfitController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConsolidationReportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\TransferController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Middleware 'verified' dilepas: User tidak implement MustVerifyEmail (ERD §4.1).
Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    // Laporan (ERD §9 Fase 4). Filter cabang ditentukan di ReportController.
    Route::get('/reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
    Route::get('/reports/stock-card', [ReportController::class, 'stockCard'])->name('reports.stock-card');
    Route::get('/reports/kas-shift', [ReportController::class, 'kasShift'])->name('reports.kas-shift');
    Route::get('/reports/sales-credit', [ReportController::class, 'salesCredit'])->name('reports.sales-credit');
    Route::get('/reports/gross-profit', GrossProfitController::class)->name('reports.gross-profit');
    Route::get('/reports/written-off', [ReportController::class, 'writtenOff'])->name('reports.written-off');
    Route::get('/reports/consolidation', ConsolidationReportController::class)->name('reports.consolidation');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ERD §5 no. 1, §2 no. 17: pemilih cabang aktif, khusus Owner (dicek di controller).
    Route::get('/active-branch', [ActiveBranchController::class, 'edit'])->name('active-branch.edit');
    Route::patch('/active-branch', [ActiveBranchController::class, 'update'])->name('active-branch.update');

    // Baru: pencarian cepat navbar (AuthenticatedLayout). Didaftarkan SEBELUM
    // Route::resource('items', ...) supaya '/items/quick-search' tidak ketangkep
    // sebagai '/items/{item}' milik resource route di bawahnya.
    Route::get('/items/quick-search', [ItemController::class, 'quickSearch'])->name('items.quick-search');

    // ERD §2 no. 13, §5 no. 20: index dibaca semua user login, tulis dibatasi
    // Gate::authorize('category.manage') di dalam controller.
    Route::resource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // customer.manage (Admin/Owner, keputusan di luar ERD §6 -- lihat diskusi sebelumnya).
    Route::resource('customers', CustomerController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // ERD §2 no. 13, §5 no. 20: index dibaca semua user login, tulis dibatasi
    // item.manage (ItemRequest::authorize dan Gate di destroy).
    Route::resource('items', ItemController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // Satuan alternatif: store lewat /items/{item}/units, update/destroy lewat /units/{unit}.
    Route::resource('items.units', ItemUnitController::class)
        ->shallow()
        ->only(['store', 'update', 'destroy']);

    // Barang masuk & penyesuaian stok (ERD §4.6). Index scope cabang ada di controller.
    Route::resource('stock-adjustments', StockAdjustmentController::class)
        ->only(['index', 'store']);
    Route::patch('/stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])
        ->name('stock-adjustments.approve');
    Route::patch('/stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])
        ->name('stock-adjustments.reject');

    // Transfer antar cabang (ERD §4.6, §5 no. 17/18/19). Index scope cabang ada di controller.
    Route::resource('transfers', TransferController::class)
        ->only(['index', 'store']);
    Route::patch('/transfers/{transfer}/send', [TransferController::class, 'send'])
        ->name('transfers.send');
    Route::patch('/transfers/{transfer}/receive', [TransferController::class, 'receive'])
        ->name('transfers.receive');
    Route::patch('/transfer-details/{transferDetail}/resolve', [TransferController::class, 'resolveDifference'])
        ->name('transfer-details.resolve');
    Route::patch('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])
        ->name('transfers.cancel');

    // Shift kasir (ERD §5 no. 11). Cabang aktif ditentukan ResolvesActiveBranch.
    Route::get('/cashier-shift', [CashierShiftController::class, 'index'])->name('cashier-shift.index');
    Route::post('/cashier-shift/open', [CashierShiftController::class, 'open'])->name('cashier-shift.open');
    Route::patch('/cashier-shift/{cashierShift}/close', [CashierShiftController::class, 'close'])->name('cashier-shift.close');

    // Kas masuk/keluar laci (ERD §5 no. 12). Shift 'open' ditemukan lewat cabang aktif di service.
    Route::post('/cash-movements', [CashMovementController::class, 'store'])->name('cash-movements.store');

    // POS Penjualan & void (UC-SAL-01/02/03/07, ERD §4.3, §5 no. 6/7/9/10/13/14).
    // Cabang aktif ditentukan ResolvesActiveBranch (sama seperti shift kasir).
    Route::get('/pos', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/pos', [SaleController::class, 'store'])->name('sales.store');
    Route::patch('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');

    // Piutang pelanggan & catatan titipan/pengingat (ERD §5 no. 8, §2 no. 20, §4.7).
    // notes.manage (admin, owner) ditegakkan di dalam ReceivableController, bukan di sini.
    Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
    Route::post('/customers/{customer}/receivables/pay', [ReceivableController::class, 'pay'])
        ->name('receivables.pay');
    Route::post('/customers/{customer}/receivables/correct', [ReceivableController::class, 'correct'])
        ->name('receivables.correct');
    Route::post('/customers/{customer}/notes', [ReceivableController::class, 'storeNote'])
        ->name('receivables.notes.store');
    Route::patch('/notes/{note}/toggle', [ReceivableController::class, 'toggleNote'])
        ->name('receivables.notes.toggle');
});

require __DIR__ . '/auth.php';