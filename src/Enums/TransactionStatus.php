<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Enums;

enum TransactionStatus: string
{
    case FINALIZED = 'F';
    case PENDING = 'A';
    case CLEARING = 'C';
    case ERROR = 'E';
    case MANUALLY_REVERSED = 'M';
    case REQUIRES_ACTION = 'X';
    case REFUNDED = 'R';

    public function isSuccessful(): bool
    {
        return $this === self::FINALIZED;
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::CLEARING], true);
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::ERROR, self::MANUALLY_REVERSED], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::FINALIZED => 'Finalized',
            self::PENDING => 'Pending',
            self::CLEARING => 'Clearing',
            self::ERROR => 'Error',
            self::MANUALLY_REVERSED => 'Reversed',
            self::REQUIRES_ACTION => 'Requires Action',
            self::REFUNDED => 'Refunded',
        };
    }
}
