<?php
declare(strict_types=1);

function normalize_promocode(string $code): string
{
    $normalized = mb_strtoupper(trim($code), 'UTF-8');
    if (mb_strlen($normalized, 'UTF-8') < 2 || mb_strlen($normalized, 'UTF-8') > 40
        || !preg_match('/^[\p{L}\p{N}_-]+$/u', $normalized)) {
        throw new InvalidArgumentException('Код должен содержать 2–40 букв, цифр, дефисов или подчёркиваний');
    }
    return $normalized;
}

function promocode_belongs_to_cook(array $promocode, int $cookId): bool
{
    return (int) ($promocode['cook_id'] ?? 0) === $cookId;
}

function promocode_discount_amount(float $subtotal, int $percent): float
{
    return round(max(0, $subtotal) * max(0, min(100, $percent)) / 100, 2);
}

/**
 * Apply one seller's discount to item line totals, distributing cents so
 * the returned line totals add up exactly to subtotal - discount.
 *
 * @return array{discount: float, line_totals: list<float>}
 */
function promocode_discount_lines(array $lineTotals, int $percent): array
{
    $lines = array_map(static fn ($amount): float => max(0, (float) $amount), $lineTotals);
    $subtotal = round(array_sum($lines), 2);
    $discount = promocode_discount_amount($subtotal, $percent);
    $remainingDiscount = $discount;
    $result = [];
    $lastIndex = count($lines) - 1;

    foreach ($lines as $index => $amount) {
        if ($index === $lastIndex) {
            $lineDiscount = $remainingDiscount;
        } elseif ($subtotal > 0) {
            $lineDiscount = min($amount, round($discount * $amount / $subtotal, 2));
            $lineDiscount = min($lineDiscount, $remainingDiscount);
        } else {
            $lineDiscount = 0;
        }
        $result[] = round($amount - $lineDiscount, 2);
        $remainingDiscount = round($remainingDiscount - $lineDiscount, 2);
    }

    return ['discount' => $discount, 'line_totals' => $result];
}
