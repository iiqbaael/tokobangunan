<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validasi bentuk data saja (tipe, ada/tidaknya field). Aturan bisnis --
 * from_branch_id <> to_branch_id, Admin hanya dari cabangnya sendiri --
 * ditegakkan TransferService (ERD §5 no. 17, chk_transfers_branches).
 */
class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('transfer.create');
    }

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'to_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:from_branch_id'],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.qty_sent' => ['required', 'numeric', 'gt:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from_branch_id' => 'cabang asal',
            'to_branch_id' => 'cabang tujuan',
            'transfer_date' => 'tanggal',
            'items' => 'daftar barang',
            'items.*.item_id' => 'barang',
            'items.*.qty_sent' => 'qty dikirim',
        ];
    }
}