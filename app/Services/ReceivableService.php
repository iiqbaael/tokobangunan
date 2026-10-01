<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\NoteType;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ReceivableService
{
    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly CashierShiftService $shiftService,
    ) {
    }

    /**
     * Saldo piutang pelanggan (ERD Â§5 no. 8):
     * SUM(sale_payments credit, sales completed) - SUM(notes pembayaran).
     */
    public function balance(Customer $customer): string
    {
        $creditSales = DB::table('sale_payments')
            ->join('sales_headers', 'sales_headers.id', '=', 'sale_payments.sale_id')
            ->where('sales_headers.customer_id', $customer->id)
            ->where('sales_headers.status', 'completed')
            ->where('sale_payments.method', 'credit')
            ->sum('sale_payments.amount');

        $paid = Note::where('noteable_type', $customer->getMorphClass())
            ->where('noteable_id', $customer->id)
            ->where('type', NoteType::Pembayaran)
            ->sum('amount');

        return bcsub($this->num($creditSales), $this->num($paid), self::MONEY_SCALE);
    }

    /**
     * Pelunasan piutang (ERD Â§5 no. 8). Hanya notes.manage (admin, owner).
     * Ditolak jika melebihi saldo. Jika $cash = true, wajib ada shift 'open' di $branchId
     * dan dicatat sekaligus sebagai cash_movements type='in' reason='pelunasan_piutang'
     * dalam satu transaksi DB. $branchId diabaikan jika $cash = false.
     */
    public function pay(
        Customer $customer,
        User $actor,
        string|int|float $amount,
        bool $cash,
        ?int $branchId = null,
        ?string $note = null,
    ): Note {
        $this->assertCanManage($actor);

        $value = $this->positiveMoney($amount, 'amount');

        if ($cash && $branchId === null) {
            throw new InvalidArgumentException('branchId wajib diisi untuk pelunasan tunai.');
        }

        return DB::transaction(function () use ($customer, $actor, $value, $cash, $branchId, $note) {
            $currentBalance = $this->balance($customer);

            if (bccomp($value, $currentBalance, self::MONEY_SCALE) > 0) {
                throw new RuntimeException("Pelunasan ({$value}) melebihi saldo piutang ({$currentBalance}).");
            }

            $paymentNote = Note::create([
                'noteable_type' => $customer->getMorphClass(),
                'noteable_id' => $customer->id,
                'type' => NoteType::Pembayaran,
                'body' => $note ?? 'Pelunasan piutang',
                'amount' => $value,
                'created_by' => $actor->id,
            ]);

            if ($cash) {
                $this->recordCashMovement($branchId, $actor, CashMovementType::In, $value, 'pelunasan_piutang');
            }

            return $paymentNote;
        });
    }

    /**
     * Koreksi pelunasan (ERD Â§5 no. 8, Â§2 no. 20): baris notes baru bernilai negatif,
     * bukan update/delete baris asli. $amount diisi bernilai positif (besarnya koreksi);
     * disimpan sebagai negatif. Jika pelunasan asalnya tunai, dicatat balik sebagai
     * cash_movements type='out' reason='koreksi_pelunasan'.
     */
    public function correct(
        Customer $customer,
        User $actor,
        string|int|float $amount,
        bool $wasCash,
        ?int $branchId = null,
        ?string $note = null,
    ): Note {
        $this->assertCanManage($actor);

        $value = $this->positiveMoney($amount, 'amount');

        if ($wasCash && $branchId === null) {
            throw new InvalidArgumentException('branchId wajib diisi untuk koreksi pelunasan tunai.');
        }

        return DB::transaction(function () use ($customer, $actor, $value, $wasCash, $branchId, $note) {
            $correctionNote = Note::create([
                'noteable_type' => $customer->getMorphClass(),
                'noteable_id' => $customer->id,
                'type' => NoteType::Pembayaran,
                'body' => $note ?? 'Koreksi pelunasan piutang',
                'amount' => bcmul($value, '-1', self::MONEY_SCALE),
                'created_by' => $actor->id,
            ]);

            if ($wasCash) {
                $this->recordCashMovement($branchId, $actor, CashMovementType::Out, $value, 'koreksi_pelunasan');
            }

            return $correctionNote;
        });
    }

    private function recordCashMovement(int $branchId, User $actor, CashMovementType $type, string $amount, string $reason): CashMovement
    {
        $shift = $this->shiftService->openShiftForUpdate($branchId, shared: true);

        if ($shift === null) {
            throw new RuntimeException('Pelunasan/koreksi tunai memerlukan shift yang sedang terbuka di cabang ini.');
        }

        return CashMovement::create([
            'cashier_shift_id' => $shift->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->isOwner() && ! $user->isAdmin()) {
            throw new RuntimeException('Tidak berhak mengelola pelunasan piutang (notes.manage).');
        }
    }

    private function positiveMoney(string|int|float $value, string $label): string
    {
        $fixed = $this->round($this->num($value), self::MONEY_SCALE);

        if (bccomp($fixed, '0', self::MONEY_SCALE) <= 0) {
            throw new InvalidArgumentException("{$label} harus lebih dari 0.");
        }

        return $fixed;
    }

    private function round(string $value, int $scale): string
    {
        $offset = '0.' . str_repeat('0', $scale) . '5';

        return bccomp($value, '0', $scale + 4) >= 0
            ? bcadd($value, $offset, $scale)
            : bcsub($value, $offset, $scale);
    }

    private function num(string|int|float $value): string
    {
        if (is_float($value)) {
            $value = number_format($value, 8, '.', '');
        }

        $value = trim((string) $value);

        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            $value = '0';
        }

        return $value;
    }
}