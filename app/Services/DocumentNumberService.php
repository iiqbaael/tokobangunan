<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SalesHeader;
use App\Models\StockAdjustment;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class DocumentNumberService
{
    /** tipe => [model, kolom nomor]. Prefix diambil dari config('toko.prefix.{tipe}'). */
    private const TYPES = [
        'sale' => [SalesHeader::class, 'invoice_no'],
        'transfer' => [Transfer::class, 'transfer_no'],
        'adjustment' => [StockAdjustment::class, 'adjustment_no'],
    ];

    private const MIN_DIGITS = 4;

    /**
     * Buat dokumen bernomor. $callback menerima nomor dan harus menyimpan dokumennya
     * (mengembalikan model). Kena duplicate key -> hitung ulang nomor dan ulangi
     * (ERD §5 no. 21). Aman dipanggil di dalam DB::transaction() lain (jadi savepoint).
     *
     * Catatan: pelanggaran UNIQUE di kolom lain pada $callback juga memicu ulang,
     * lalu dilempar kembali setelah $maxAttempts.
     */
    public function create(
        string $type,
        int $branchId,
        Closure $callback,
        ?CarbonInterface $date = null,
        int $maxAttempts = 5,
    ): mixed {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $callback($this->next($type, $branchId, $date)));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                usleep(random_int(10_000, 50_000));
            }
        }
    }

    /**
     * Nomor berikutnya: {PREFIX}-{BRANCH_CODE}-{YYMM}-{urut min. 4 digit}.
     * Wajib di dalam DB::transaction(). Urutan diambil dari dokumen terakhir
     * berprefix sama (ORDER BY id DESC), bukan urut string; angka boleh melewati 4 digit.
     */
    public function next(string $type, int $branchId, ?CarbonInterface $date = null): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('DocumentNumberService wajib dipanggil di dalam DB::transaction().');
        }

        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Tipe dokumen '{$type}' tidak dikenal.");
        }

        [$model, $column] = self::TYPES[$type];

        $prefix = config("toko.prefix.{$type}");

        if (! is_string($prefix) || $prefix === '') {
            throw new InvalidArgumentException("Prefix untuk tipe '{$type}' belum diatur di config/toko.php.");
        }

        $code = Branch::withTrashed()->findOrFail($branchId)->code;
        $head = $prefix . '-' . $code . '-' . ($date ?? now())->format('ym') . '-';

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $head) . '%';

        $last = $model::query()
            ->where($column, 'like', $like)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value($column);

        $seq = $last === null ? 0 : (int) substr($last, strlen($head));

        return $head . str_pad((string) ($seq + 1), self::MIN_DIGITS, '0', STR_PAD_LEFT);
    }
}