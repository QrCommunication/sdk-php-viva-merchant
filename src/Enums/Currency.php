<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Enums;

enum Currency: int
{
    case EUR = 978;
    case GBP = 826;
    case USD = 840;
    case PLN = 985;
    case RON = 946;
    case BGN = 975;
    case CZK = 203;
    case HRK = 191;
    case HUF = 348;
    case DKK = 208;
    case SEK = 752;
    case NOK = 578;

    public function iso(): string
    {
        return $this->name;
    }

    public static function fromIso(string $iso): self
    {
        return self::from(self::tryFrom(
            match (strtoupper($iso)) {
                'EUR' => 978, 'GBP' => 826, 'USD' => 840,
                'PLN' => 985, 'RON' => 946, 'BGN' => 975,
                'CZK' => 203, 'HRK' => 191, 'HUF' => 348,
                'DKK' => 208, 'SEK' => 752, 'NOK' => 578,
                default => throw new \ValueError("Unknown currency: {$iso}"),
            }
        )?->value ?? throw new \ValueError("Unknown currency: {$iso}"));
    }
}
