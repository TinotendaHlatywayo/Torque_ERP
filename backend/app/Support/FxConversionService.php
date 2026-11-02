<?php

namespace App\Support;

use App\Models\FxRate;

final class FxConversionService
{
    public function rate(string $fromCurrency, string $toCurrency, ?\DateTimeInterface $at = null): string
    {
        if ($fromCurrency === $toCurrency) {
            return '1.00000000';
        }

        $at = $at ?? now();

        $rate = FxRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        if (! $rate) {
            throw new \RuntimeException('No FX rate is available for the requested currencies and time.');
        }

        return (string) $rate->rate;
    }

    public function convert(int $amountMinor, string $fromCurrency, string $toCurrency, ?\DateTimeInterface $at = null): int
    {
        if ($fromCurrency === $toCurrency) {
            return $amountMinor;
        }

        $rate = $this->rate($fromCurrency, $toCurrency, $at);

        return (int) Decimal::roundHalfUp(
            bcmul((string) $amountMinor, $rate, 8),
            0,
        );
    }
}
