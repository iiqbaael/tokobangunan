<?php

namespace App\Services;

use App\Enums\StockMutationType;
use App\Models\StockBalance;
use App\Models\StockMutation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class StockService
{
    // Tipe dibandingkan lewat value string, supaya tidak bergantung pada nama case enum.
    private const IN_TYPES = ['adjustment_in', 'transfer_in', 'sale_void'];
    private const OUT_TYPES = ['sale_out', 'transfer_out', 'adjustment_out'];

    private const QTY_SCALE = 4;
    private const CALC_SCALE = 10;

    /**
     * Kunci baris stock_balances urut item_id menaik (ERD §5 no. 2, cegah deadlock).
     * Baris yang belum ada dibuat dulu (qty 0, avg_cost 0). Wajib di dalam DB::transaction().
     * Service pemanggil (penjualan, transfer) memanggil ini sekali untuk semua barang di dokumennya.
     */
    public function lockBalances(int $branchId, array $itemIds): Collection
    {
        $this->assertInTransaction();

        $ids = collect($itemIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        if ($ids === []) {
            return new Collection();
        }

        $existing = StockBalance::where('branch_id', $branchId)
            ->whereIn('item_id', $ids)
            ->pluck('item_id')
            ->all();

        $missing = array_diff($ids, $existing);

        if ($missing !== []) {
            $rows = [];
            foreach ($missing as $itemId) {
                $rows[] = [
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'qty' => 0,
                    'avg_cost' => 0,
                    'min_stock' => 0,
                    'updated_at' => now(),
                ];
            }
            StockBalance::insertOrIgnore($rows);
        }

        return StockBalance::where('branch_id', $branchId)
            ->whereIn('item_id', $ids)
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Stok masuk: adjustment_in, transfer_in, sale_void. unit_cost per satuan dasar, wajib.
     * Moving average (ERD §5 no. 3); jika stok lama <= 0, avg_cost = unit_cost masuk.
     */
    public function applyIn(
        int $branchId,
        int $itemId,
        string|int|float $qty,
        string|int|float $unitCost,
        StockMutationType $type,
        int $userId,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMutation {
        $this->assertInTransaction();
        $this->assertType($type, self::IN_TYPES);

        $qty = $this->positive($qty, 'qty');
        $cost = $this->nonNegative($unitCost, 'unit_cost');

        $balance = $this->lockedBalance($branchId, $itemId);
        $oldQty = $this->num($balance->qty);
        $oldAvg = $this->num($balance->avg_cost);

        if (bccomp($oldQty, '0', self::QTY_SCALE) <= 0) {
            $newAvg = $cost;
        } else {
            $value = bcadd(bcmul($oldQty, $oldAvg, 8), bcmul($qty, $cost, 8), 8);
            $newAvg = $this->round(
                bcdiv($value, bcadd($oldQty, $qty, self::QTY_SCALE), self::CALC_SCALE),
                self::QTY_SCALE
            );
        }

        $balance->qty = bcadd($oldQty, $qty, self::QTY_SCALE);
        $balance->avg_cost = $newAvg;
        $balance->save();

        return $this->record($branchId, $itemId, $type, $qty, $cost, $userId, $reference, $note);
    }

    /**
     * Stok keluar: sale_out, transfer_out, adjustment_out. avg_cost tidak berubah.
     * unit_cost mutasi = avg_cost saat itu (snapshot per satuan dasar; dipakai sebagai cost_at_sale).
     * Ditolak jika stok jadi negatif, kecuali config('toko.allow_negative_stock').
     */
    public function applyOut(
        int $branchId,
        int $itemId,
        string|int|float $qty,
        StockMutationType $type,
        int $userId,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMutation {
        $this->assertInTransaction();
        $this->assertType($type, self::OUT_TYPES);

        $qty = $this->positive($qty, 'qty');

        $balance = $this->lockedBalance($branchId, $itemId);
        $oldQty = $this->num($balance->qty);
        $avgCost = $this->num($balance->avg_cost);
        $newQty = bcsub($oldQty, $qty, self::QTY_SCALE);

        if (bccomp($newQty, '0', self::QTY_SCALE) < 0 && ! config('toko.allow_negative_stock')) {
            throw new RuntimeException("Stok tidak cukup: tersedia {$oldQty}, diminta {$qty}.");
        }

        $balance->qty = $newQty;
        $balance->save();

        return $this->record(
            $branchId,
            $itemId,
            $type,
            bcmul($qty, '-1', self::QTY_SCALE),
            $avgCost,
            $userId,
            $reference,
            $note
        );
    }

        /**
     * Hitung ulang qty & avg_cost satu barang di satu cabang dari stock_mutations,
     * diurutkan (mutation_date, id) supaya deterministik (ERD §5 no. 24).
     * $apply = false -> hanya laporan (dry run), saldo tidak diubah.
     * Hak akses `stock.recalculate` (owner) ditegakkan di Policy/controller nanti.
     *
     * @return array{before_qty: string, before_avg: string, after_qty: string, after_avg: string, changed: bool, mutations: int}
     */
    public function recalculate(int $branchId, int $itemId, bool $apply = true): array
    {
        $this->assertInTransaction();

        $balance = $this->lockedBalance($branchId, $itemId);
        $beforeQty = $this->num($balance->qty);
        $beforeAvg = $this->num($balance->avg_cost);

        $qty = '0.0000';
        $avg = '0.0000';
        $count = 0;

        $mutations = StockMutation::where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->orderBy('mutation_date')
            ->orderBy('id')
            ->cursor();

        foreach ($mutations as $m) {
            $count++;
            $mQty = $this->num($m->qty);

            if (in_array($m->mutation_type->value, self::IN_TYPES, true)) {
                if ($m->unit_cost === null) {
                    throw new RuntimeException("Mutasi #{$m->id} bertipe masuk tetapi unit_cost kosong.");
                }

                $cost = $this->num($m->unit_cost);

                if (bccomp($qty, '0', self::QTY_SCALE) <= 0) {
                    $avg = $this->round($cost, self::QTY_SCALE);
                } else {
                    $value = bcadd(bcmul($qty, $avg, 8), bcmul($mQty, $cost, 8), 8);
                    $avg = $this->round(
                        bcdiv($value, bcadd($qty, $mQty, self::QTY_SCALE), self::CALC_SCALE),
                        self::QTY_SCALE
                    );
                }
            }

            // Mutasi keluar sudah bertanda negatif dan tidak mengubah avg_cost.
            $qty = bcadd($qty, $mQty, self::QTY_SCALE);
        }

        $changed = bccomp($qty, $beforeQty, self::QTY_SCALE) !== 0
            || bccomp($avg, $beforeAvg, self::QTY_SCALE) !== 0;

        if ($apply && $changed) {
            $balance->qty = $qty;
            $balance->avg_cost = $avg;
            $balance->save();
        }

        return [
            'before_qty' => $beforeQty,
            'before_avg' => $beforeAvg,
            'after_qty' => $qty,
            'after_avg' => $avg,
            'changed' => $changed,
            'mutations' => $count,
        ];
    }

    /**
     * Hitung ulang semua barang di satu cabang (urut item_id menaik).
     * Mengembalikan hanya barang yang saldonya berbeda, dengan kunci item_id.
     */
    public function recalculateBranch(int $branchId, bool $apply = true): array
    {
        $this->assertInTransaction();

        $itemIds = StockMutation::where('branch_id', $branchId)->distinct()->pluck('item_id')
            ->merge(StockBalance::where('branch_id', $branchId)->pluck('item_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $changed = [];

        foreach ($itemIds as $itemId) {
            $result = $this->recalculate($branchId, $itemId, $apply);

            if ($result['changed']) {
                $changed[$itemId] = $result;
            }
        }

        return $changed;
    }

    private function lockedBalance(int $branchId, int $itemId): StockBalance
    {
        return $this->lockBalances($branchId, [$itemId])->first();
    }

    private function record(
        int $branchId,
        int $itemId,
        StockMutationType $type,
        string $signedQty,
        string $unitCost,
        int $userId,
        ?Model $reference,
        ?string $note,
    ): StockMutation {
        return StockMutation::create([
            'branch_id' => $branchId,
            'item_id' => $itemId,
            'mutation_date' => now(),
            'mutation_type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'qty' => $signedQty,
            'unit_cost' => $unitCost,
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('StockService wajib dipanggil di dalam DB::transaction().');
        }
    }

    private function assertType(StockMutationType $type, array $allowed): void
    {
        if (! in_array($type->value, $allowed, true)) {
            throw new InvalidArgumentException("Tipe mutasi '{$type->value}' tidak valid untuk operasi ini.");
        }
    }

    private function positive(string|int|float $value, string $label): string
    {
        $fixed = $this->round($this->num($value), self::QTY_SCALE);

        if (bccomp($fixed, '0', self::QTY_SCALE) <= 0) {
            throw new InvalidArgumentException("{$label} harus lebih dari 0.");
        }

        return $fixed;
    }

    private function nonNegative(string|int|float $value, string $label): string
    {
        $fixed = $this->round($this->num($value), self::QTY_SCALE);

        if (bccomp($fixed, '0', self::QTY_SCALE) < 0) {
            throw new InvalidArgumentException("{$label} tidak boleh negatif.");
        }

        return $fixed;
    }

    /** Round half up (menjauhi nol). bcadd/bcsub dengan $scale memotong, jadi offset setengah dipakai. */
    private function round(string $value, int $scale): string
    {
        $offset = '0.' . str_repeat('0', $scale) . '5';

        return bccomp($value, '0', self::CALC_SCALE) >= 0
            ? bcadd($value, $offset, $scale)
            : bcsub($value, $offset, $scale);
    }

    private function num(string|int|float $value): string
    {
        if (is_float($value)) {
            $value = number_format($value, 8, '.', '');
        }

        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Nilai bukan angka desimal yang valid: '{$value}'.");
        }

        return $value;
    }
}