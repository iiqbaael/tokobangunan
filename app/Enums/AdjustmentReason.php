<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case Restock      = 'restock';
    case InitialStock = 'initial_stock';
    case Damaged      = 'damaged';
    case Lost         = 'lost';
    case Correction   = 'correction';
    case Other        = 'other';

    /**
     * Restock/stok awal langsung approved (ERD §5 no. 15).
     */
    public function autoApproved(): bool
    {
        return match ($this) {
            self::Restock, self::InitialStock => true,
            default => false,
        };
    }

    /**
     * Tipe adjustment yang diizinkan untuk alasan ini (ERD §4.6).
     *
     * @return array<int, AdjustmentType>
     */
    public function allowedTypes(): array
    {
        return match ($this) {
            self::Restock, self::InitialStock => [AdjustmentType::In],
            self::Damaged, self::Lost         => [AdjustmentType::Out],
            self::Correction, self::Other     => [AdjustmentType::In, AdjustmentType::Out],
        };
    }
}