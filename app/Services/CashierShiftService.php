<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CashierShiftService
{
    private const MONEY_SCALE = 2;

    /**
     * Buka shift baru di suatu cabang (ERD Â§5 no. 11).
     * Yang boleh: cashier/admin di cabangnya sendiri, atau owner (cabang aktif ditentukan pemanggil).
     * Satu shift 'open' per cabang dijaga UNIQUE(open_branch_id) di DB; duplikat diterjemahkan
     * jadi pesan yang jelas, bukan error SQL mentah.
     */
    public function open(int $branchId, User $opener, string|int|float $openingBalance, ?string $note = null): CashierShift
    {
        $this->assertCanOpen($opener, $branchId);

        $balance = $this->money($openingBalance, 'opening_balance');

        return DB::transaction(function () use ($branchId, $opener, $balance, $note) {
            try {
                return CashierShift::create([
                    'branch_id' => $branchId,
                    'user_id' => $opener->id,
                    'opening_balance' => $balance,
                    'status' => 'open',
                    'opened_at' => now(),
                    'note' => $note,
                ]);
            } catch (QueryException $e) {
                if ($this->isDuplicateKey($e)) {
                    throw new RuntimeException('Sudah ada shift yang sedang terbuka di cabang ini.');
                }

                throw $e;
            }
        });
    }

    /**
     * Tutup shift (ERD Â§5 no. 11). Mengunci baris shift (lockForUpdate) supaya tidak bertabrakan
     * dengan penjualan/cash_movements yang sedang sharedLock pada baris yang sama.
     * Pembuka shift boleh menutup shiftnya sendiri; selain itu wajib admin cabang ini atau owner
     * (tutup paksa) -> shift.force_close.
     */
    public function close(
        CashierShift $shift,
        User $closer,
        string|int|float $closingBalance,
        ?string $note = null,
    ): CashierShift {
        return DB::transaction(function () use ($shift, $closer, $closingBalance, $note) {
            /** @var CashierShift $locked */
            $locked = CashierShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->value !== 'open') {
                throw new RuntimeException('Shift ini sudah ditutup.');
            }

            $isOpener = $closer->id === $locked->user_id;

            if (! $isOpener) {
                $this->assertCanForceClose($closer, $locked->branch_id);
            }

            $balance = $this->money($closingBalance, 'closing_balance');
            $expected = $this->calculateExpectedBalance($locked);
            $difference = bcsub($balance, $expected, self::MONEY_SCALE);

            $locked->closing_balance = $balance;
            $locked->expected_balance = $expected;
            $locked->difference = $difference;
            $locked->closed_by = $closer->id;
            $locked->closed_at = now();
            $locked->status = 'closed';

            if ($note !== null && $note !== '') {
                $locked->note = $locked->note ? "{$locked->note}\n{$note}" : $note;
            }

            $locked->save();

            return $locked;
        });
    }

    /**
     * Ambil shift 'open' di suatu cabang untuk dipakai service lain (penjualan, cash_movements).
     * $shared = true -> sharedLock (banyak boleh baca bersamaan, tapi memblokir close() yang
     * memakai lockForUpdate). Wajib dipanggil di dalam DB::transaction() milik pemanggil.
     */
    public function openShiftForUpdate(int $branchId, bool $shared = true): ?CashierShift
    {
        $this->assertInTransaction();

        $query = CashierShift::where('branch_id', $branchId)->where('status', 'open');

        return $shared ? $query->sharedLock()->first() : $query->lockForUpdate()->first();
    }

    /**
     * Rumus expected_balance (ERD Â§4.4): opening + pembayaran cash yang completed + cash_movements in
     * - cash_movements out. Pembayaran credit/transfer/ewallet/card tidak dihitung.
     */
    private function calculateExpectedBalance(CashierShift $shift): string
    {
        $cashSales = DB::table('sale_payments')
            ->join('sales_headers', 'sales_headers.id', '=', 'sale_payments.sale_id')
            ->where('sales_headers.cashier_shift_id', $shift->id)
            ->where('sale_payments.method', 'cash')
            ->where('sales_headers.status', 'completed')
            ->sum('sale_payments.amount');

        $cashIn = DB::table('cash_movements')
            ->where('cashier_shift_id', $shift->id)
            ->where('type', CashMovementType::In->value)
            ->sum('amount');

        $cashOut = DB::table('cash_movements')
            ->where('cashier_shift_id', $shift->id)
            ->where('type', CashMovementType::Out->value)
            ->sum('amount');

        $expected = bcadd($this->num($shift->opening_balance), $this->num($cashSales), self::MONEY_SCALE);
        $expected = bcadd($expected, $this->num($cashIn), self::MONEY_SCALE);
        $expected = bcsub($expected, $this->num($cashOut), self::MONEY_SCALE);

        return $expected;
    }

    private function assertCanOpen(User $user, int $branchId): void
    {
        if ($user->isOwner()) {
            return;
        }

        if ((int) $user->branch_id !== $branchId) {
            throw new RuntimeException('Tidak berhak membuka shift di cabang ini.');
        }
    }

    private function assertCanForceClose(User $user, int $branchId): void
    {
        if ($user->isOwner()) {
            return;
        }

        if ($user->isAdmin() && (int) $user->branch_id === $branchId) {
            return;
        }

        throw new RuntimeException('Hanya pembuka shift, Admin cabang ini, atau Owner yang boleh menutup shift ini.');
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Metode ini wajib dipanggil di dalam DB::transaction().');
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private function money(string|int|float $value, string $label): string
    {
        $fixed = $this->round($this->num($value), self::MONEY_SCALE);

        if (bccomp($fixed, '0', self::MONEY_SCALE) < 0) {
            throw new InvalidArgumentException("{$label} tidak boleh negatif.");
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