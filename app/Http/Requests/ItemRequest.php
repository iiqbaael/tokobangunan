<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validasi input barang (ERD §4.2). Dipakai store (tanpa route param `item`)
 * dan update (dengan route param `item`).
 *
 * Yang TIDAK dicek di sini, karena butuh konteks data: barcode unik lintas
 * items/item_units dan satuan dasar immutable -> ditegakkan ItemService.
 */
class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('item.manage');
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $isUpdate = $item !== null;

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('items', 'code')->ignore($item?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'specification' => ['nullable', 'string', 'max:160'],
            'unit' => ['required', 'string', 'max:20'],
            'sell_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'is_active' => $isUpdate ? ['required', 'boolean'] : ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'code' => 'kode barang',
            'barcode' => 'barcode',
            'name' => 'nama barang',
            'brand' => 'merek',
            'specification' => 'spesifikasi',
            'unit' => 'satuan dasar',
            'sell_price' => 'harga jual',
            'is_active' => 'status aktif',
        ];
    }
}