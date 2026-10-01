<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CashMovementService
{
    private const MONEY_SCALE = 2;

    /**
     * Catat uang masuk/keluar laci (ERD Â§5 no. 12).
     * Hanya boleh pada shift 'open' di cabang aktif user; siapa pun yang berhak di cabang itu
     * boleh mencatat, bukan hanya pembuka shift. Baris shift di-sharedLock dulu supaya tidak
     * bentrok dengan CashierShiftService::close() yang memakai lockForUpdate.
     *
     * type='out' yang membuat proyeksi kas negatif tidak diblok di sini (peringatan konfirmasi
     * ada di layer UI/controller, bukan block keras -- ERD Â§5 no. 12).
     */
    public function record(
        int $branchId,
        User $actor,
        CashMovementType $type,
        string|int|float $amount,
        string $reason,
        ?string $note = null,
    ): CashMovement {
        $this->assertCanRecord($actor, $branchId);

        $value = $this->positiveMoney($amount, 'amount');
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('reason wajib diisi.');
        }

        return DB::transaction(function () use ($branchId, $actor, $type, $value, $reason, $note) {
            /** @var CashierShift|null $shift */
            $shift = CashierShift::where('branch_id', $branchId)
                ->where('status', 'open')
                ->sharedLock()
                ->first();

            if ($shift === null) {
                throw new RuntimeException('Tidak ada shift yang sedang terbuka di cabang ini.');
            }

            return CashMovement::create([
                'cashier_shift_id' => $shift->id,
                'type' => $type,
                'amount' => $value,
                'reason' => $reason,
                'note' => $note,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function assertCanRecord(User $user, int $branchId): void
    {
        if ($user->isOwner()) {
            return;
        }

        if ((int) $user->branch_id !== $branchId) {
            throw new RuntimeException('Tidak berhak mencatat uang masuk/keluar di cabang ini.');
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
            throw new InvalidArgumentException("Nilai bukan angka desimal yang valid: '{$value}'.");
        }

        return $value;
    }
}