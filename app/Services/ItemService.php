<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\StockMutation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Master barang (ERD §4.2, §5 no. 25 dan 26).
 * Otorisasi (item.manage) dicek di controller/Gate; service hanya menegakkan aturan data.
 */
class ItemService
{
    private const FIELDS = [
        'category_id', 'code', 'barcode', 'name', 'brand',
        'specification', 'unit', 'sell_price', 'image', 'is_active',
    ];

    /**
     * Buat barang + baris stock_balances (qty 0) untuk setiap cabang (ERD §4.5).
     */
    public function create(array $data): Item
    {
        $data = $this->normalize(Arr::only($data, self::FIELDS));

        $this->assertBarcodeAvailable($data['barcode'] ?? null);

        return DB::transaction(function () use ($data) {
            $item = Item::create($data + ['is_active' => true]);

            $this->createBalances($item);

            return $item;
        });
    }

    /**
     * Ubah barang. Satuan dasar tidak boleh berubah jika sudah ada mutasi stok (§5 no. 25).
     */
    public function update(Item $item, array $data): Item
    {
        $data = $this->normalize(Arr::only($data, self::FIELDS));

        if (array_key_exists('barcode', $data)) {
            $this->assertBarcodeAvailable($data['barcode'], exceptItemId: $item->id);
        }

        if (isset($data['unit']) && $data['unit'] !== $item->unit) {
            $this->assertUnitChangeAllowed($item, $data['unit']);
        }

        return DB::transaction(function () use ($item, $data) {
            $item->update($data);

            return $item->refresh();
        });
    }

    /**
     * Nonaktifkan barang = soft delete (ERD §2 no. 7).
     */
    public function delete(Item $item): void
    {
        DB::transaction(function () use ($item) {
            $item->update(['is_active' => false]);
            $item->delete();
        });
    }

    /**
     * Barcode harus unik lintas items dan item_units, termasuk baris soft-deleted (§5 no. 26).
     * Public supaya bisa dipakai ulang oleh pengelolaan satuan alternatif.
     */
    public function assertBarcodeAvailable(
        ?string $barcode,
        ?int $exceptItemId = null,
        ?int $exceptItemUnitId = null,
    ): void {
        if ($barcode === null || $barcode === '') {
            return;
        }

        $usedByItem = Item::withTrashed()
            ->where('barcode', $barcode)
            ->when($exceptItemId, fn ($q) => $q->where('id', '!=', $exceptItemId))
            ->exists();

        $usedByUnit = ItemUnit::query()
            ->where('barcode', $barcode)
            ->when($exceptItemUnitId, fn ($q) => $q->where('id', '!=', $exceptItemUnitId))
            ->exists();

        if ($usedByItem || $usedByUnit) {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode sudah dipakai barang atau satuan lain.',
            ]);
        }
    }

    /**
     * Buat stock_balances untuk semua cabang. insertOrIgnore aman dijalankan ulang
     * karena ada UNIQUE(branch_id, item_id).
     */
    public function createBalances(Item $item): void
    {
        $now = now();

        $rows = Branch::query()->pluck('id')->map(fn ($branchId) => [
            'branch_id' => $branchId,
            'item_id' => $item->id,
            'qty' => 0,
            'avg_cost' => 0,
            'min_stock' => 0,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('stock_balances')->insertOrIgnore($rows);
        }
    }

    private function assertUnitChangeAllowed(Item $item, string $newUnit): void
    {
        if (StockMutation::where('item_id', $item->id)->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan dasar tidak bisa diubah karena barang ini sudah punya mutasi stok.',
            ]);
        }

        $clash = $item->units()->get()
            ->contains(fn (ItemUnit $u) => mb_strtolower($u->unit) === mb_strtolower($newUnit));

        if ($clash) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan tersebut sudah terdaftar sebagai satuan alternatif barang ini.',
            ]);
        }
    }

    /** Trim string; string kosong pada kolom opsional menjadi NULL. */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = ($value === '' && in_array($key, ['barcode', 'brand', 'specification', 'image'], true))
                    ? null
                    : $value;
            }
        }

        return $data;
    }
}