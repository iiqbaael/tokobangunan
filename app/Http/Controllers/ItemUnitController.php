<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Services\ItemUnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemUnitController extends Controller
{
    public function __construct(private readonly ItemUnitService $units)
    {
    }

    public function store(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('item.manage');

        $this->units->create($item, $this->validated($request));

        return back()->with('success', 'Satuan alternatif ditambahkan.');
    }

    public function update(Request $request, ItemUnit $unit): RedirectResponse
    {
        Gate::authorize('item.manage');

        $this->units->update($unit, $this->validated($request));

        return back()->with('success', 'Satuan alternatif diperbarui.');
    }

    public function destroy(ItemUnit $unit): RedirectResponse
    {
        Gate::authorize('item.manage');

        $this->units->delete($unit);

        return back()->with('success', 'Satuan alternatif dihapus.');
    }

    /**
     * Validasi bentuk data saja. Aturan bisnis (unit <> satuan dasar, duplikat,
     * barcode unik lintas tabel, format konversi) ditegakkan ItemUnitService.
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'unit' => ['required', 'string', 'max:20'],
            'conversion_qty' => ['required', 'numeric', 'gt:0'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [], [
            'unit' => 'satuan',
            'conversion_qty' => 'konversi',
            'barcode' => 'barcode',
            'sell_price' => 'harga jual',
        ]);
    }
}