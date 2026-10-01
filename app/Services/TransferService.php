<?php

namespace App\Services;

use App\Enums\DifferenceResolution;
use App\Enums\StockMutationType;
use App\Enums\TransferStatus;
use App\Models\StockBalance;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfer antar cabang (ERD §4.6, §5 no. 17/18/19).
 * Otorisasi kasar (boleh akses modul ini sama sekali / boleh terima transfer
 * cabang tertentu) dicek Gate 'transfer.create' & 'transfer.approve' di
 * controller. Service ini menegakkan aturan yang butuh data: from <> to,
 * Admin hanya dari/ke cabangnya sendiri, kunci unit_cost dari avg_cost cabang
 * asal saat kirim, qty_received <= qty_sent, dan resolusi selisih per item.
 */
class TransferService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * Buat dokumen draft. Belum menyentuh stok (§2 no. 17: stok cabang asal
     * baru berkurang saat send()).
     *
     * $data: from_branch_id, to_branch_id, transfer_date, note,
     * items: [['item_id','qty_sent','note'?], ...]
     */
    public function create(array $data, User $user): Transfer
    {
        $fromBranchId = (int) $data['from_branch_id'];
        $toBranchId = (int) $data['to_branch_id'];

        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Cabang tujuan harus berbeda dari cabang asal.',
            ]);
        }

        $this->assertBranchAllowed($user, $fromBranchId, $toBranchId);

        $lines = $this->normalizeLines($data['items'] ?? []);

        return app(DocumentNumberService::class)->create(
            'transfer',
            $fromBranchId,
            function (string $no) use ($fromBranchId, $toBranchId, $data, $lines, $user) {
                $transfer = Transfer::create([
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'transfer_no' => $no,
                    'transfer_date' => $data['transfer_date'],
                    'status' => TransferStatus::Draft,
                    'note' => $data['note'] ?? null,
                    'created_by' => $user->id,
                ]);

                foreach ($lines as $line) {
                    $transfer->details()->create([
                        'item_id' => $line['item_id'],
                        'qty_sent' => $line['qty_sent'],
                        'qty_received' => 0,
                        'unit_cost' => 0,
                        'difference_resolution' => DifferenceResolution::None,
                        'note' => $line['note'],
                    ]);
                }

                return $transfer->fresh('details');
            }
        );
    }

    /**
     * Kirim dokumen draft: kunci unit_cost dari avg_cost cabang asal, catat
     * mutasi transfer_out sebesar qty_sent (§5 no. 17). avg_cost cabang asal
     * tidak berubah oleh mutasi keluar (StockService::applyOut).
     */
    public function send(Transfer $transfer, User $user): Transfer
    {
        if ($transfer->status !== TransferStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus draft yang bisa dikirim.',
            ]);
        }

        $this->assertBranchAllowed($user, $transfer->from_branch_id, $transfer->to_branch_id);

                return DB::transaction(function () use ($transfer, $user) {
            $details = $transfer->details()->lockForUpdate()->get();

            foreach ($details as $detail) {
                $balance = StockBalance::where('branch_id', $transfer->from_branch_id)
                    ->where('item_id', $detail->item_id)
                    ->value('avg_cost');

                $unitCost = (string) ($balance ?? '0');

                $detail->update(['unit_cost' => $unitCost]);

                    $this->stock->applyOut(
                    $transfer->from_branch_id,
                    $detail->item_id,
                    $detail->qty_sent,
                    StockMutationType::TransferOut,
                    $user->id,
                    $transfer,
                    $detail->note,
                );
            }

            $transfer->update([
                'status' => TransferStatus::Sent,
                'sent_by' => $user->id,
                'sent_at' => now(),
            ]);

            return $transfer->fresh(['details']);
        });
    }

    /**
     * Terima dokumen sent/partial. $received: [transfer_detail_id => qty_received].
     * Item yang tidak disebut atau kosong dianggap 0 diterima kalau belum pernah
     * diterima sama sekali (tidak mengubah baris yang sudah final di panggilan
     * sebelumnya -- qty_received hanya boleh naik, tidak pernah mundur).
     * Mutasi transfer_in dicatat sebesar selisih (qty diterima kali ini),
     * bukan qty_received total, supaya penerimaan bertahap tidak dobel hitung.
     */
    public function receive(Transfer $transfer, array $received, User $user): Transfer
    {
        if (! in_array($transfer->status, [TransferStatus::Sent, TransferStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus terkirim/sebagian yang bisa diterima.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $received, $user) {
            $details = $transfer->details()->lockForUpdate()->get();

            $stillOpen = false;

            foreach ($details as $detail) {
                $alreadyReceived = (string) $detail->qty_received;
                $qtySent = (string) $detail->qty_sent;

                // Baris yang sudah final (diterima penuh, atau selisihnya sudah
                // diselesaikan) tidak disentuh lagi.
                $isFinal = bccomp($alreadyReceived, $qtySent, 4) >= 0
                    || $detail->difference_resolution !== DifferenceResolution::None;

                if ($isFinal) {
                    continue;
                }

                $input = $received[$detail->id] ?? null;
                $newTotal = $input === null || $input === '' ? $alreadyReceived : $this->numeric((string) $input, "items.{$detail->id}");

                if (bccomp($newTotal, $alreadyReceived, 4) < 0) {
                    throw ValidationException::withMessages([
                        "items.{$detail->id}" => 'Qty diterima tidak boleh berkurang dari penerimaan sebelumnya.',
                    ]);
                }

                if (bccomp($newTotal, $qtySent, 4) > 0) {
                    throw ValidationException::withMessages([
                        "items.{$detail->id}" => 'Qty diterima tidak boleh melebihi qty dikirim.',
                    ]);
                }

                $increment = bcsub($newTotal, $alreadyReceived, 4);

                if (bccomp($increment, '0', 4) > 0) {
                    $this->stock->applyIn(
                        $transfer->to_branch_id,
                        $detail->item_id,
                        $increment,
                        $detail->unit_cost,
                        StockMutationType::TransferIn,
                        $user->id,
                        $transfer,
                        $detail->note,
                    );
                }

                $detail->update(['qty_received' => $newTotal]);

                $hasDifference = bccomp($newTotal, $qtySent, 4) < 0;

                if (! $hasDifference) {
                    // pas -- baris ini selesai, tidak butuh resolusi.
                } elseif ($detail->difference_resolution === DifferenceResolution::None) {
                    $stillOpen = true;
                }
            }

            // Baris lama yang masih belum final juga menahan dokumen di 'partial'.
            foreach ($transfer->details()->get() as $detail) {
                $hasGap = bccomp((string) $detail->qty_received, (string) $detail->qty_sent, 4) < 0;

                if ($hasGap && $detail->difference_resolution === DifferenceResolution::None) {
                    $stillOpen = true;
                }
            }

            $transfer->update([
                'status' => $stillOpen ? TransferStatus::Partial : TransferStatus::Received,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            return $transfer->fresh(['details']);
        });
    }

    /**
     * Selesaikan selisih satu baris (§2 no. 9). 'returned' mencatat transfer_in
     * di cabang asal sebesar selisih (barang dianggap kembali fisik); 'written_off'
     * tidak menambah mutasi apa pun (kerugian, barang dianggap hilang).
     * Setelah semua baris bersalisih pada dokumen ini punya resolusi, dokumen
     * yang berstatus partial otomatis naik jadi received.
     */
    public function resolveDifference(
        TransferDetail $detail,
        DifferenceResolution $resolution,
        User $user,
        ?string $note = null,
    ): TransferDetail {
        if ($resolution === DifferenceResolution::None) {
            throw ValidationException::withMessages([
                'difference_resolution' => 'Resolusi harus returned atau written_off.',
            ]);
        }

        $transfer = $detail->transfer;

        if (! in_array($transfer->status, [TransferStatus::Sent, TransferStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini tidak dalam status yang bisa diselesaikan selisihnya.',
            ]);
        }

        if ($detail->difference_resolution !== DifferenceResolution::None) {
            throw ValidationException::withMessages([
                'difference_resolution' => 'Selisih baris ini sudah diselesaikan sebelumnya.',
            ]);
        }

        $gap = bcsub((string) $detail->qty_sent, (string) $detail->qty_received, 4);

        if (bccomp($gap, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'difference_resolution' => 'Baris ini tidak memiliki selisih.',
            ]);
        }

        return DB::transaction(function () use ($detail, $resolution, $user, $note, $gap, $transfer) {
            if ($resolution === DifferenceResolution::Returned) {
                $this->stock->applyIn(
                    $transfer->from_branch_id,
                    $detail->item_id,
                    $gap,
                    $detail->unit_cost,
                    StockMutationType::TransferIn,
                    $user->id,
                    $transfer,
                    $note ?? $detail->note,
                );
            }

            $detail->update([
                'difference_resolution' => $resolution,
                'note' => $note ?? $detail->note,
            ]);

            $stillOpen = $transfer->details()
                ->get()
                ->contains(function (TransferDetail $d) {
                    $hasGap = bccomp((string) $d->qty_received, (string) $d->qty_sent, 4) < 0;

                    return $hasGap && $d->difference_resolution === DifferenceResolution::None;
                });

            if (! $stillOpen) {
                $transfer->update(['status' => TransferStatus::Received]);
            }

            return $detail->fresh();
        });
    }

    /** Batalkan dokumen draft. Belum ada mutasi stok yang perlu dibatalkan. */
    public function cancel(Transfer $transfer, User $user): Transfer
    {
        if ($transfer->status !== TransferStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus draft yang bisa dibatalkan.',
            ]);
        }

        $this->assertBranchAllowed($user, $transfer->from_branch_id, $transfer->to_branch_id);

        $transfer->update(['status' => TransferStatus::Cancelled]);

        return $transfer->fresh();
    }

    /** Admin hanya dari/ke cabangnya sendiri (salah satu dari from/to); Owner bebas. */
    private function assertBranchAllowed(User $user, int $fromBranchId, int $toBranchId): void
    {
        if ($user->isOwner()) {
            return;
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'from_branch_id' => 'Anda tidak berwenang mengelola transfer.',
            ]);
        }

        if ((int) $user->branch_id !== $fromBranchId && (int) $user->branch_id !== $toBranchId) {
            throw ValidationException::withMessages([
                'from_branch_id' => 'Transfer harus melibatkan cabang Anda sendiri (asal atau tujuan).',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @return array<int, array{item_id: int, qty_sent: string, note: ?string}>
     */
    private function normalizeLines(array $rawItems): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu barang harus diisi.']);
        }

        $seen = [];
        $lines = [];

        foreach ($rawItems as $i => $raw) {
            $itemId = (int) ($raw['item_id'] ?? 0);
            $qty = (string) ($raw['qty_sent'] ?? '');

            if ($itemId <= 0) {
                throw ValidationException::withMessages(["items.{$i}.item_id" => 'Barang wajib dipilih.']);
            }

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages(["items.{$i}.item_id" => 'Barang yang sama tidak boleh diulang dalam satu dokumen.']);
            }
            $seen[$itemId] = true;

            if (! preg_match('/^\d+(\.\d{1,4})?$/', $qty) || bccomp($qty, '0', 4) <= 0) {
                throw ValidationException::withMessages(["items.{$i}.qty_sent" => 'Qty dikirim harus angka lebih dari 0.']);
            }

            $lines[] = [
                'item_id' => $itemId,
                'qty_sent' => $qty,
                'note' => isset($raw['note']) && trim((string) $raw['note']) !== '' ? trim((string) $raw['note']) : null,
            ];
        }

        return $lines;
    }

    private function numeric(string $value, string $field): string
    {
        $value = trim($value);

        if (! preg_match('/^\d+(\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Nilai harus angka 0 atau lebih.']);
        }

        return $value;
    }
}