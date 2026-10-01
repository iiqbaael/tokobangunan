<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validasi bentuk data saja (tipe, ada/tidaknya field). Aturan bisnis --
 * kombinasi adjustment_type+reason, cabang mana yang boleh, unit_cost wajib
 * untuk restock, larangan tabrakan dengan transfer berjalan -- ditegakkan
 * StockAdjustmentService (ERD §5 no. 15/16/18).
 */
class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('stock_adjustment.create');
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'adjustment_date' => ['required', 'date'],
            'adjustment_type' => ['required', 'in:in,out'],
            'reason' => ['required', 'in:restock,initial_stock,damaged,lost,correction,other'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'cabang',
            'adjustment_date' => 'tanggal',
            'adjustment_type' => 'tipe',
            'reason' => 'alasan',
            'reference_no' => 'nomor referensi',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.qty' => 'qty',
            'items.*.unit_cost' => 'harga satuan',
        ];
    }
}