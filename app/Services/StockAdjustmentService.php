<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\DifferenceResolution;
use App\Enums\StockMutationType;
use App\Enums\TransferStatus;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\TransferDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Barang masuk & penyesuaian stok (ERD §4.6, §5 no. 15/16/18).
 * Otorisasi kasar (siapa boleh akses fitur ini sama sekali) dicek Gate di
 * controller; service ini menegakkan aturan yang butuh data (cabang mana yang
 * boleh, kombinasi type+reason, larangan tabrakan dengan transfer berjalan).
 */
class StockAdjustmentService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /**
     * Buat dokumen. restock/initial_stock langsung approved oleh pembuat sendiri
     * (§5 no. 15); alasan lain tetap pending menunggu UC-ADJ-02.
     *
     * $data: branch_id, adjustment_date, adjustment_type, reason, reference_no,
     * description, items: [['item_id','qty','unit_cost'?,'note'?], ...]
     */
    public function create(array $data, User $user): StockAdjustment
    {
        $reason = $data['reason'] instanceof AdjustmentReason
            ? $data['reason']
            : AdjustmentReason::from($data['reason']);

        $type = $data['adjustment_type'] instanceof AdjustmentType
            ? $data['adjustment_type']
            : AdjustmentType::from($data['adjustment_type']);

        $branchId = (int) $data['branch_id'];

        if (! in_array($type, $reason->allowedTypes(), true)) {
            throw ValidationException::withMessages([
                'adjustment_type' => "Alasan '{$reason->value}' tidak boleh dipakai untuk tipe '{$type->value}'.",
            ]);
        }

        $this->assertBranchAllowed($user, $branchId, $reason);

        $lines = $this->normalizeLines($data['items'] ?? [], $reason, $type);

        if ($type === AdjustmentType::In) {
            foreach ($lines as $line) {
                $this->assertNotBlockedByPendingTransfer($branchId, (int) $line['item_id']);
            }
        }

        return app(DocumentNumberService::class)->create(
            'adjustment',
            $branchId,
            function (string $no) use ($branchId, $data, $type, $reason, $lines, $user) {
                $autoApproved = $reason->autoApproved();

                $adjustment = StockAdjustment::create([
                    'branch_id' => $branchId,
                    'adjustment_no' => $no,
                    'adjustment_date' => $data['adjustment_date'],
                    'adjustment_type' => $type,
                    'reason' => $reason,
                    'reference_no' => $data['reference_no'] ?? null,
                    'description' => $data['description'] ?? null,
                    'status' => $autoApproved ? AdjustmentStatus::Approved : AdjustmentStatus::Pending,
                    'created_by' => $user->id,
                    'approved_by' => $autoApproved ? $user->id : null,
                    'approved_at' => $autoApproved ? now() : null,
                ]);

                foreach ($lines as $line) {
                    $adjustment->details()->create($line);
                }

                if ($autoApproved) {
                    $this->applyMutations($adjustment->fresh('details'), $user);
                }

                return $adjustment->fresh('details');
            }
        );
    }

    /**
     * Setujui dokumen pending (UC-ADJ-02). Penyetuju bukan pembuat, kecuali Owner
     * (dicek Gate 'adjustment.approve' di controller -- di sini hanya jaga-jaga).
     */
    public function approve(StockAdjustment $adjustment, User $approver): StockAdjustment
    {
        if ($adjustment->status !== AdjustmentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus pending yang bisa disetujui.',
            ]);
        }

        if (! $approver->isOwner() && (int) $adjustment->created_by === $approver->id) {
            throw ValidationException::withMessages([
                'status' => 'Penyetuju tidak boleh pembuat dokumen sendiri.',
            ]);
        }

        if ($adjustment->adjustment_type === AdjustmentType::In) {
            foreach ($adjustment->details as $detail) {
                $this->assertNotBlockedByPendingTransfer($adjustment->branch_id, $detail->item_id);
            }
        }

        return DB::transaction(function () use ($adjustment, $approver) {
            $adjustment->update([
                'status' => AdjustmentStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->applyMutations($adjustment->fresh('details'), $approver);

            return $adjustment->fresh(['details', 'approver']);
        });
    }

    public function reject(StockAdjustment $adjustment, User $approver, string $reason): StockAdjustment
    {
        if ($adjustment->status !== AdjustmentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen berstatus pending yang bisa ditolak.',
            ]);
        }

        $adjustment->update([
            'status' => AdjustmentStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        return $adjustment->fresh();
    }

    /** Terapkan mutasi stok untuk semua baris dokumen yang sudah approved. */
    private function applyMutations(StockAdjustment $adjustment, User $actor): void
    {
        DB::transaction(function () use ($adjustment, $actor) {
            foreach ($adjustment->details as $detail) {
                if ($adjustment->adjustment_type === AdjustmentType::In) {
                    $cost = $detail->unit_cost ?? $this->currentAvgCost($adjustment->branch_id, $detail->item_id);

                    $this->stock->applyIn(
                        $adjustment->branch_id,
                        $detail->item_id,
                        $detail->qty,
                        $cost,
                        StockMutationType::AdjustmentIn,
                        $actor->id,
                        $adjustment,
                        $detail->note,
                    );
                } else {
                    $this->stock->applyOut(
                        $adjustment->branch_id,
                        $detail->item_id,
                        $detail->qty,
                        StockMutationType::AdjustmentOut,
                        $actor->id,
                        $adjustment,
                        $detail->note,
                    );
                }
            }
        });
    }

    private function currentAvgCost(int $branchId, int $itemId): string
    {
        return (string) (StockBalance::where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->value('avg_cost') ?? '0');
    }

    /**
     * restock/initial_stock: Admin cabang mana pun, Owner cabang mana pun
     * (matriks akses ERD §2.2 use-case v2, "Admin (cabang manapun)").
     * Alasan lain: Admin hanya cabangnya sendiri, Owner bebas.
     */
    private function assertBranchAllowed(User $user, int $branchId, AdjustmentReason $reason): void
    {
        if ($user->isOwner()) {
            return;
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Anda tidak berwenang membuat dokumen ini.',
            ]);
        }

        if ($reason->autoApproved()) {
            return;
        }

        if ((int) $user->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Untuk alasan ini, Anda hanya bisa membuat dokumen di cabang sendiri.',
            ]);
        }
    }

    /**
     * ERD §5 no. 18: barang dari transfer sent/partial menuju cabang ini tidak
     * boleh dicatat via adjustment 'in' sebelum transfer itu received (per item).
     */
    private function assertNotBlockedByPendingTransfer(int $branchId, int $itemId): void
    {
        $blocked = TransferDetail::query()
            ->where('item_id', $itemId)
            ->whereHas('transfer', fn ($q) => $q->where('to_branch_id', $branchId)
                ->whereIn('status', [TransferStatus::Sent, TransferStatus::Partial]))
            ->whereHas('transfer', function ($q) {
                $q->where('status', TransferStatus::Sent)
                    ->orWhere('status', TransferStatus::Partial);
            })
            ->get()
            ->contains(function (TransferDetail $detail) {
                if ($detail->transfer->status === TransferStatus::Sent) {
                    return true;
                }

                return $detail->difference_resolution === DifferenceResolution::None;
            });

        if ($blocked) {
            throw ValidationException::withMessages([
                'items' => 'Barang ini sedang dalam transfer menuju cabang ini dan belum diterima sepenuhnya; tidak bisa dicatat lewat adjustment.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $rawItems, AdjustmentReason $reason, AdjustmentType $type): array
    {
        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'Minimal satu barang harus diisi.']);
        }

        $requireCost = $reason->autoApproved(); // restock/initial_stock wajib unit_cost (§5 no. 15)

        $seen = [];
        $lines = [];

        foreach ($rawItems as $i => $raw) {
            $itemId = (int) ($raw['item_id'] ?? 0);
            $qty = (string) ($raw['qty'] ?? '');
            $cost = array_key_exists('unit_cost', $raw) && $raw['unit_cost'] !== '' && $raw['unit_cost'] !== null
                ? (string) $raw['unit_cost']
                : null;

            if ($itemId <= 0) {
                throw ValidationException::withMessages(["items.{$i}.item_id" => 'Barang wajib dipilih.']);
            }

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages(["items.{$i}.item_id" => 'Barang yang sama tidak boleh diulang dalam satu dokumen.']);
            }
            $seen[$itemId] = true;

            if (! preg_match('/^\d+(\.\d{1,4})?$/', $qty) || bccomp($qty, '0', 4) <= 0) {
                throw ValidationException::withMessages(["items.{$i}.qty" => 'Qty harus angka lebih dari 0.']);
            }

            if ($cost !== null && ! preg_match('/^\d+(\.\d{1,4})?$/', $cost)) {
                throw ValidationException::withMessages(["items.{$i}.unit_cost" => 'Harga satuan harus angka 0 atau lebih.']);
            }

            if ($type === AdjustmentType::In && $requireCost && $cost === null) {
                throw ValidationException::withMessages(["items.{$i}.unit_cost" => 'Harga satuan wajib diisi untuk restock/stok awal.']);
            }

            $lines[] = [
                'item_id' => $itemId,
                'qty' => $qty,
                'unit_cost' => $type === AdjustmentType::In ? $cost : null,
                'note' => isset($raw['note']) && trim((string) $raw['note']) !== '' ? trim((string) $raw['note']) : null,
            ];
        }

        return $lines;
    }
}