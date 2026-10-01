<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMutationType;
use App\Models\Item;
use App\Models\Customer;
use App\Models\SalesHeader;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transaksi penjualan POS & void (ERD §4.3, §5 no. 6/7/9/10/13/14; §2 no. 2/12).
 * Otorisasi kasar (boleh akses POS sama sekali, boleh kredit, boleh void) dicek
 * Gate di controller; service ini menegakkan aturan yang butuh data: shift open,
 * pembulatan & kesamaan total, snapshot cost_at_sale, dan saldo piutang tidak
 * boleh negatif saat void nota kredit.
 */
class SaleService
{
        public function __construct(
        private readonly StockService $stock,
        private readonly UnitConversionService $units,
        private readonly CashierShiftService $shifts,
        private readonly ReceivableService $receivables,
    ) {
    }

    /**
     * $data:
     *   customer_id?: int
     *   note?: string
     *   cash_received?: string  (wajib kalau ada baris payment method='cash')
     *   items: [['item_id','unit','qty_input'], ...]
     *   payments: [['method','amount','reference_no'?], ...]
     */
    public function create(int $branchId, array $data, User $user): SalesHeader
    {
        $lines = $this->normalizeItems($data['items'] ?? []);
        $payments = $this->normalizePayments($data['payments'] ?? []);

        $hasCredit = collect($payments)->contains(fn (array $p) => $p['method'] === PaymentMethod::Credit);

        if ($hasCredit && ! ($user->isAdmin() || $user->isOwner())) {
            throw ValidationException::withMessages([
                'payments' => 'Hanya Admin/Owner yang boleh memberikan pembayaran kredit.',
            ]);
        }

        $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;

        if ($hasCredit && $customerId === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pelanggan wajib dipilih untuk pembayaran kredit.',
            ]);
        }

        return app(DocumentNumberService::class)->create(
            'sale',
            $branchId,
            function (string $no) use ($branchId, $data, $lines, $payments, $customerId, $user) {
                $shift = $this->shifts->openShiftForUpdate($branchId, shared: true);

                if ($shift === null) {
                    throw ValidationException::withMessages([
                        'shift' => 'Tidak ada shift kasir yang terbuka di cabang ini.',
                    ]);
                }

                $items = Item::whereIn('id', collect($lines)->pluck('item_id'))->get()->keyBy('id');

                foreach ($lines as $i => $line) {
                    $item = $items->get($line['item_id']);

                    if ($item === null || ! $item->is_active) {
                        throw ValidationException::withMessages([
                            "items.{$i}.item_id" => 'Barang tidak ditemukan atau tidak aktif.',
                        ]);
                    }
                }

                $sale = SalesHeader::create([
                    'branch_id' => $branchId,
                    'cashier_shift_id' => $shift->id,
                    'customer_id' => $customerId,
                    'invoice_no' => $no,
                    'sale_date' => now(),
                    'total' => 0,
                    'status' => SaleStatus::Completed,
                    'note' => $data['note'] ?? null,
                    'created_by' => $user->id,
                ]);

                $total = '0';

                // BR-01: urut item_id menaik supaya kunci stock_balances konsisten.
                foreach (collect($lines)->sortBy('item_id') as $line) {
                    $item = $items->get($line['item_id']);

                    $computed = $this->units->line($item, $line['unit'], $line['qty_input']);

                    $mutation = $this->stock->applyOut(
                        $branchId,
                        $item->id,
                        $computed['qty'],
                        StockMutationType::SaleOut,
                        $user->id,
                        $sale,
                    );

                    $sale->details()->create([
                        'item_id' => $item->id,
                        'unit' => $computed['unit'],
                        'qty_input' => $computed['qty_input'],
                        'conversion_qty' => $computed['conversion_qty'],
                        'qty' => $computed['qty'],
                        'unit_price' => $computed['unit_price'],
                        'subtotal' => $computed['subtotal'],
                        // cost_at_sale = snapshot avg_cost saat mutasi (BR §5 no. 13),
                        // diambil langsung dari unit_cost yang dicatat applyOut().
                        'cost_at_sale' => $mutation->unit_cost,
                    ]);

                    $total = bcadd($total, $computed['subtotal'], 2);
                }

                $paymentTotal = collect($payments)->reduce(fn ($carry, $p) => bcadd($carry, $p['amount'], 2), '0');

                if (bccomp($paymentTotal, $total, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'payments' => "Total pembayaran ({$paymentTotal}) harus sama persis dengan total nota ({$total}).",
                    ]);
                }

                $cashPortion = collect($payments)
                    ->filter(fn (array $p) => $p['method'] === PaymentMethod::Cash)
                    ->reduce(fn ($carry, $p) => bcadd($carry, $p['amount'], 2), '0');

                $cashReceived = null;
                $changeGiven = null;

                if (bccomp($cashPortion, '0', 2) > 0) {
                    $raw = $data['cash_received'] ?? null;

                    if ($raw === null || $raw === '') {
                        throw ValidationException::withMessages([
                            'cash_received' => 'Jumlah uang diterima wajib diisi untuk pembayaran tunai.',
                        ]);
                    }

                    $cashReceived = $this->money($raw, 'cash_received');

                    if (bccomp($cashReceived, $cashPortion, 2) < 0) {
                        throw ValidationException::withMessages([
                            'cash_received' => 'Uang diterima kurang dari bagian tunai yang harus dibayar.',
                        ]);
                    }

                    $changeGiven = bcsub($cashReceived, $cashPortion, 2);
                }

                foreach ($payments as $p) {
                    $sale->payments()->create([
                        'method' => $p['method'],
                        'amount' => $p['amount'],
                        'reference_no' => $p['reference_no'],
                    ]);
                }

                $sale->update([
                    'total' => $total,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                ]);

                return $sale->fresh(['details', 'payments']);
            }
        );
    }

    /**
     * Void nota (UC-SAL-03). Hanya status completed & shift transaksi masih open.
     * Ditolak kalau nota memuat pembayaran credit dan void akan membuat saldo
     * piutang pelanggan negatif (BR §5 no. 14).
     */
    public function void(SalesHeader $sale, User $user, string $reason): SalesHeader
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'Nota ini sudah void.',
            ]);
        }

        return DB::transaction(function () use ($sale, $user, $reason) {
            $shift = $sale->shift()->lockForUpdate()->firstOrFail();

            if ($shift->status->value !== 'open') {
                throw ValidationException::withMessages([
                    'status' => 'Shift transaksi ini sudah ditutup, tidak bisa void. Gunakan penyesuaian stok manual + catatan.',
                ]);
            }

            $sale->load('details', 'payments');

            $creditAmount = $sale->payments
                ->filter(fn ($p) => $p->method === PaymentMethod::Credit)
                ->reduce(fn ($carry, $p) => bcadd($carry, (string) $p->amount, 2), '0');

                if (bccomp($creditAmount, '0', 2) > 0 && $sale->customer_id) {
                $customer = Customer::findOrFail($sale->customer_id);
                $currentReceivable = $this->receivables->balance($customer);
                $afterVoid = bcsub($currentReceivable, $creditAmount, 2);

                if (bccomp($afterVoid, '0', 2) < 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Void ditolak: akan membuat saldo piutang pelanggan menjadi negatif. Batalkan pelunasan terkait dulu dengan baris pembalik.',
                    ]);
                }
            }

            foreach ($sale->details->sortBy('item_id') as $detail) {
                $this->stock->applyIn(
                    $sale->branch_id,
                    $detail->item_id,
                    $detail->qty,
                    $detail->cost_at_sale,
                    StockMutationType::SaleVoid,
                    $user->id,
                    $sale,
                    "Void nota {$sale->invoice_no}",
                );
            }

            $sale->update([
                'status' => SaleStatus::Void,
                'voided_by' => $user->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            return $sale->fresh(['details', 'payments']);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @return array<int, array{item_id: int, unit: string, qty_input: string}>
     */
    private function normalizeItems(array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu barang harus diisi.']);
        }

        $seen = [];
        $lines = [];

        foreach ($rawItems as $i => $raw) {
            $itemId = (int) ($raw['item_id'] ?? 0);
            $unit = trim((string) ($raw['unit'] ?? ''));
            $qtyInput = (string) ($raw['qty_input'] ?? '');

            if ($itemId <= 0) {
                throw ValidationException::withMessages(["items.{$i}.item_id" => 'Barang wajib dipilih.']);
            }

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages([
                    "items.{$i}.item_id" => 'Barang yang sama tidak boleh diulang dalam satu nota, gabungkan qty-nya.',
                ]);
            }
            $seen[$itemId] = true;

            if ($unit === '') {
                throw ValidationException::withMessages(["items.{$i}.unit" => 'Satuan wajib diisi.']);
            }

            if (! preg_match('/^\d+(\.\d{1,4})?$/', $qtyInput) || bccomp($qtyInput, '0', 4) <= 0) {
                throw ValidationException::withMessages(["items.{$i}.qty_input" => 'Qty harus angka lebih dari 0.']);
            }

            $lines[] = ['item_id' => $itemId, 'unit' => $unit, 'qty_input' => $qtyInput];
        }

        return $lines;
    }

    /**
     * @param array<int, array<string, mixed>> $rawPayments
     * @return array<int, array{method: PaymentMethod, amount: string, reference_no: ?string}>
     */
    private function normalizePayments(array $rawPayments): array
    {
        if ($rawPayments === []) {
            throw ValidationException::withMessages(['payments' => 'Minimal satu metode pembayaran harus diisi.']);
        }

        $payments = [];

        foreach ($rawPayments as $i => $raw) {
            $methodValue = (string) ($raw['method'] ?? '');
            $method = PaymentMethod::tryFrom($methodValue);

            if ($method === null) {
                throw ValidationException::withMessages(["payments.{$i}.method" => 'Metode pembayaran tidak valid.']);
            }

            $amount = (string) ($raw['amount'] ?? '');

            if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(["payments.{$i}.amount" => 'Jumlah bayar harus angka lebih dari 0.']);
            }

            $reference = isset($raw['reference_no']) && trim((string) $raw['reference_no']) !== ''
                ? trim((string) $raw['reference_no'])
                : null;

            $payments[] = ['method' => $method, 'amount' => $amount, 'reference_no' => $reference];
        }

        return $payments;
    }

    private function money(string|int|float $value, string $label): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([$label => 'Nilai harus angka 0 atau lebih.']);
        }

        return $value;
    }
}