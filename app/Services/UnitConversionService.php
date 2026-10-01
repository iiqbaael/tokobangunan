<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemUnit;
use InvalidArgumentException;

class UnitConversionService
{
    private const QTY_SCALE = 4;
    private const MONEY_SCALE = 2;
    private const CALC_SCALE = 10;

    /**
     * Cari satuan (dasar atau alternatif) dan tentukan konversi + harga per satuan input.
     * Harga alternatif: item_units.sell_price bila ada, jika tidak
     * ROUND(items.sell_price x conversion_qty) ke rupiah penuh (ERD §5 no. 5).
     *
     * @return array{unit: string, conversion_qty: string, unit_price: string, is_base: bool}
     */
    public function resolve(Item $item, string $unit): array
    {
        $needle = mb_strtolower(trim($unit));

        if ($needle === mb_strtolower($item->unit)) {
            return [
                'unit' => $item->unit,
                'conversion_qty' => $this->fixed('1', self::QTY_SCALE),
                'unit_price' => $this->fixed($item->sell_price, self::MONEY_SCALE),
                'is_base' => true,
            ];
        }

        /** @var ItemUnit|null $row */
        $row = $item->units()->get()
            ->first(fn (ItemUnit $u) => mb_strtolower($u->unit) === $needle);

        if ($row === null) {
            throw new InvalidArgumentException("Satuan '{$unit}' tidak terdaftar untuk barang {$item->code}.");
        }

        $conversion = $this->fixed($row->conversion_qty, self::QTY_SCALE);

        if ($row->sell_price !== null) {
            $price = $this->fixed($row->sell_price, self::MONEY_SCALE);
        } else {
            $raw = bcmul($this->num($item->sell_price), $conversion, 8);
            $price = $this->fixed($this->round($raw, 0), self::MONEY_SCALE);
        }

        return [
            'unit' => $row->unit,
            'conversion_qty' => $conversion,
            'unit_price' => $price,
            'is_base' => false,
        ];
    }

    /**
     * Satu baris penjualan siap simpan ke sales_details (ERD §2 no. 2).
     *
     * @return array{unit: string, qty_input: string, conversion_qty: string, qty: string, unit_price: string, subtotal: string}
     */
    public function line(Item $item, string $unit, string|int|float $qtyInput): array
    {
        $qtyInput = $this->fixed($qtyInput, self::QTY_SCALE);

        if (bccomp($qtyInput, '0', self::QTY_SCALE) <= 0) {
            throw new InvalidArgumentException('qty_input harus lebih dari 0.');
        }

        $r = $this->resolve($item, $unit);

        return [
            'unit' => $r['unit'],
            'qty_input' => $qtyInput,
            'conversion_qty' => $r['conversion_qty'],
            'qty' => $this->toBaseQty($qtyInput, $r['conversion_qty']),
            'unit_price' => $r['unit_price'],
            'subtotal' => $this->subtotal($qtyInput, $r['unit_price']),
        ];
    }

    /** qty (satuan dasar) = qty_input x conversion_qty, dibulatkan 4 desimal. */
    public function toBaseQty(string|int|float $qtyInput, string|int|float $conversionQty): string
    {
        $product = bcmul($this->num($qtyInput), $this->num($conversionQty), 8);

        return $this->fixed($product, self::QTY_SCALE);
    }

    /** subtotal = ROUND(qty_input x unit_price) ke rupiah penuh, round half up (ERD §2 no. 12). */
    public function subtotal(string|int|float $qtyInput, string|int|float $unitPrice): string
    {
        $product = bcmul($this->num($qtyInput), $this->num($unitPrice), 8);

        return $this->fixed($this->round($product, 0), self::MONEY_SCALE);
    }

    /** Biaya per satuan input -> biaya per satuan dasar (ERD §4.6): unit_cost / conversion_qty. */
    public function costToBase(string|int|float $costPerInputUnit, string|int|float $conversionQty): string
    {
        $conversion = $this->num($conversionQty);

        if (bccomp($conversion, '0', self::CALC_SCALE) <= 0) {
            throw new InvalidArgumentException('conversion_qty harus lebih dari 0.');
        }

        $quotient = bcdiv($this->num($costPerInputUnit), $conversion, self::CALC_SCALE);

        return $this->fixed($quotient, self::QTY_SCALE);
    }

    private function fixed(string|int|float $value, int $scale): string
    {
        return $this->round($this->num($value), $scale);
    }

    /** Round half up (menjauhi nol). bcadd/bcsub dengan $scale memotong, jadi offset setengah dipakai. */
    private function round(string $value, int $scale): string
    {
        $offset = '0.' . str_repeat('0', $scale) . '5';

        return bccomp($value, '0', self::CALC_SCALE) >= 0
            ? bcadd($value, $offset, $scale)
            : bcsub($value, $offset, $scale);
    }

    /** Normalisasi ke string desimal valid (float diformat tetap, bukan notasi ilmiah). */
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