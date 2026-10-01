<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi bentuk data saja. Otorisasi (Gate 'sales.create') dicek di
 * SaleController setelah branch_id aktif diketahui (ResolvesActiveBranch) --
 * beda dari modul lain karena Gate ini butuh branchId, yang baru diketahui
 * dari sesi (Owner) atau users.branch_id (Admin/Kasir), bukan dari body request.
 * Aturan bisnis (shift open, kesamaan total, kredit khusus Admin/Owner, dst)
 * ditegakkan SaleService (ERD §5 no. 6/7/9/10).
 */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.qty_input' => ['required', 'numeric', 'gt:0'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,transfer,ewallet,card,credit'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'pelanggan',
            'cash_received' => 'uang diterima',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.unit' => 'satuan',
            'items.*.qty_input' => 'qty',
            'payments' => 'metode pembayaran',
            'payments.*.method' => 'metode',
            'payments.*.amount' => 'jumlah',
        ];
    }
}