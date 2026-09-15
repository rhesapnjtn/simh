<?php

namespace App\Services;

class PricingService
{
    public static function compute(
        int $nights,
        float $roomRate,
        float $extraPersonRate,
        int $capacity,
        int $adults,
        float $discount = 0,
        float $taxRate = 0
    ): array {
        $subtotal = round($roomRate * $nights, 2);
        $extraPersons = max(0, $adults - max(1, $capacity));
        $extraPersonFee = round($extraPersons * $extraPersonRate * $nights, 2);
        $taxAmount = round(($subtotal - $discount + $extraPersonFee) * $taxRate / 100, 2);
        $total = round($subtotal - $discount + $extraPersonFee + $taxAmount, 2);

        return [
            'nights' => $nights,
            'subtotal' => $subtotal,
            'extra_person_fee' => $extraPersonFee,
            'extra_persons' => $extraPersons,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }
}