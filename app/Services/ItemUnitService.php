<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satuan alternatif barang (ERD §4.2 item_units, §5 no. 5 dan 26).
 * conversion_qty = berapa satuan dasar dalam 1 satuan alternatif
 * (mis. dasar "zak", alternatif "kg" -> 0.0200).
 * Otorisasi (item.manage) dicek di controller.
 */
class ItemUnitService
{
    private const FIELDS = ['unit', 'conversion_qty', 'barcode', 'sell_price'];

    public function __construct(private readonly ItemService $items)
    {
    }

    public function create(Item $item, array $data): ItemUnit
    {
        $data = $this->normalize(Arr::only($data, self::FIELDS));

        $this->validate($item, $data);

        return DB::transaction(fn () => $item->units()->create($data));
    }

    public function update(ItemUnit $unit, array $data): ItemUnit
    {
        $data = $this->normalize(Arr::only($data, self::FIELDS));

        $this->validate($unit->item, $data, $unit);

        return DB::transaction(function () use ($unit, $data) {
            $unit->update($data);

            return $unit->refresh();
        });
    }

    /**
     * Hapus permanen. Aman: sales_details menyimpan snapshot nama satuan dan
     * conversion_qty, tidak ada FK ke item_units.
     */
    public function delete(ItemUnit $unit): void
    {
        DB::transaction(fn () => $unit->delete());
    }

    private function validate(Item $item, array $data, ?ItemUnit $current = null): void
    {
        $unit = $data['unit'] ?? '';

        if ($unit === '') {
            throw ValidationException::withMessages(['unit' => 'Satuan wajib diisi.']);
        }

        // CHECK tidak bisa lintas tabel, jadi unit <> items.unit dicek di sini.
        if (mb_strtolower($unit) === mb_strtolower($item->unit)) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan alternatif tidak boleh sama dengan satuan dasar barang.',
            ]);
        }

        $duplicate = $item->units()
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->get()
            ->contains(fn (ItemUnit $u) => mb_strtolower($u->unit) === mb_strtolower($unit));

        if ($duplicate) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan tersebut sudah terdaftar untuk barang ini.',
            ]);
        }

        $conversion = (string) ($data['conversion_qty'] ?? '');

        if (! preg_match('/^\d+(\.\d{1,4})?$/', $conversion) || bccomp($conversion, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'conversion_qty' => 'Konversi harus angka lebih dari 0 (maksimal 4 desimal).',
            ]);
        }

        $price = $data['sell_price'] ?? null;

        if ($price !== null && (! preg_match('/^\d+(\.\d{1,2})?$/', (string) $price))) {
            throw ValidationException::withMessages([
                'sell_price' => 'Harga jual harus angka 0 atau lebih (maksimal 2 desimal).',
            ]);
        }

        $this->items->assertBarcodeAvailable($data['barcode'] ?? null, exceptItemUnitId: $current?->id);
    }

    /** Trim; barcode dan sell_price kosong menjadi NULL (NULL = harga otomatis). */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = ($value === '' && in_array($key, ['barcode', 'sell_price'], true))
                    ? null
                    : $value;
            }
        }

        return $data;
    }
}