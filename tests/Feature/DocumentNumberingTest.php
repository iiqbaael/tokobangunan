<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
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

/** Baris stock_adjustments minimal langsung ke DB, untuk menyusun kondisi awal nomor. */
function insertRawAdjustment(int $branchId, string $adjustmentNo, int $createdBy): void
{
    DB::table('stock_adjustments')->insert([
        'branch_id' => $branchId,
        'adjustment_no' => $adjustmentNo,
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'in',
        'reason' => 'restock',
        'status' => 'approved',
        'created_by' => $createdBy,
        'approved_by' => $createdBy,
        'approved_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function currentAdjustmentHead(string $branchCode): string
{
    return 'ADJ-'.$branchCode.'-'.now()->format('ym').'-';
}

beforeEach(function () {
    $this->service = app(DocumentNumberService::class);
    $this->branch = makeBranch();
    $this->admin = makeAdmin($this->branch->id);
});

test('nomor dokumen berformat PREFIX-KODECABANG-YYMM-urut dan naik setiap dibuat', function () {
    $head = currentAdjustmentHead($this->branch->code);

    $first = $this->service->create('adjustment', $this->branch->id, function (string $no) {
        return StockAdjustment::create([
            'branch_id' => $this->branch->id,
            'adjustment_no' => $no,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'in',
            'reason' => 'restock',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    });

    $second = $this->service->create('adjustment', $this->branch->id, function (string $no) {
        return StockAdjustment::create([
            'branch_id' => $this->branch->id,
            'adjustment_no' => $no,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'in',
            'reason' => 'restock',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    });

    expect($first->adjustment_no)->toBe($head.'0001');
    expect($second->adjustment_no)->toBe($head.'0002');
});

test('urutan boleh melewati 9999 (5 digit lebih)', function () {
    $head = currentAdjustmentHead($this->branch->code);
    insertRawAdjustment($this->branch->id, $head.'9999', $this->admin->id);

    $next = $this->service->create('adjustment', $this->branch->id, function (string $no) {
        return StockAdjustment::create([
            'branch_id' => $this->branch->id,
            'adjustment_no' => $no,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'in',
            'reason' => 'restock',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    });

    expect($next->adjustment_no)->toBe($head.'10000');
});

test('nomor diambil dari ORDER BY id DESC, bukan urut string', function () {
    $head = currentAdjustmentHead($this->branch->code);
    // Masukkan nomor "besar" duluan (id kecil), lalu nomor "kecil" belakangan (id besar) --
    // seharusnya next() ikut yang id-nya paling besar (0005), bukan yang angkanya
    // paling besar secara string.
    insertRawAdjustment($this->branch->id, $head.'0100', $this->admin->id);
    insertRawAdjustment($this->branch->id, $head.'0005', $this->admin->id);

    $next = $this->service->create('adjustment', $this->branch->id, function (string $no) {
        return StockAdjustment::create([
            'branch_id' => $this->branch->id,
            'adjustment_no' => $no,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'in',
            'reason' => 'restock',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    });

    expect($next->adjustment_no)->toBe($head.'0006');
});

test('retry otomatis saat kena duplicate key dan akhirnya berhasil dengan nomor baru', function () {
    $head = currentAdjustmentHead($this->branch->code);
    $collidingNo = $head.'0001';
    insertRawAdjustment($this->branch->id, $collidingNo, $this->admin->id);

    $attempts = 0;

    $result = $this->service->create('adjustment', $this->branch->id, function (string $no) use (&$attempts, $collidingNo) {
        $attempts++;

        // Percobaan pertama sengaja dipaksa memakai nomor yang sudah dipakai
        // (mensimulasikan race condition), supaya create() harus retry.
        $useNo = $attempts === 1 ? $collidingNo : $no;

        return StockAdjustment::create([
            'branch_id' => $this->branch->id,
            'adjustment_no' => $useNo,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'in',
            'reason' => 'restock',
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    });

    expect($attempts)->toBe(2);
    expect($result->adjustment_no)->toBe($head.'0002');
    expect($result->adjustment_no)->not->toBe($collidingNo);
    expect(StockAdjustment::count())->toBe(2);
});

test('kena duplicate key terus menerus akhirnya dilempar ulang setelah maxAttempts', function () {
    $head = currentAdjustmentHead($this->branch->code);
    $collidingNo = $head.'0001';
    insertRawAdjustment($this->branch->id, $collidingNo, $this->admin->id);

    expect(function () use ($collidingNo) {
        $this->service->create('adjustment', $this->branch->id, function (string $no) use ($collidingNo) {
            // Selalu memakai nomor yang sama -> selalu tabrakan.
            return StockAdjustment::create([
                'branch_id' => $this->branch->id,
                'adjustment_no' => $collidingNo,
                'adjustment_date' => now()->toDateString(),
                'adjustment_type' => 'in',
                'reason' => 'restock',
                'status' => 'approved',
                'created_by' => $this->admin->id,
            ]);
        }, maxAttempts: 3);
    })->toThrow(UniqueConstraintViolationException::class);

    expect(StockAdjustment::count())->toBe(1); // tetap cuma yang manual di awal
});