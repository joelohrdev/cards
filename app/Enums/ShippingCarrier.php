<?php

namespace App\Enums;

enum ShippingCarrier: string
{
    case Usps = 'usps';
    case Ups = 'ups';
    case Fedex = 'fedex';
    case Dhl = 'dhl';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Usps => 'USPS',
            self::Ups => 'UPS',
            self::Fedex => 'FedEx',
            self::Dhl => 'DHL',
            self::Other => 'Other',
        };
    }

    /**
     * The carrier's public tracking page for a tracking number, or null when we don't know it.
     */
    public function trackingUrl(string $trackingNumber): ?string
    {
        $number = rawurlencode(trim($trackingNumber));

        return match ($this) {
            self::Usps => "https://tools.usps.com/go/TrackConfirmAction?tLabels={$number}",
            self::Ups => "https://www.ups.com/track?tracknum={$number}",
            self::Fedex => "https://www.fedex.com/fedextrack/?trknbr={$number}",
            self::Dhl => "https://www.dhl.com/us-en/home/tracking/tracking-express.html?tracking-id={$number}",
            self::Other => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
