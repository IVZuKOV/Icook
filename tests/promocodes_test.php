<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/lib/promocodes.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

assert_same('CHEF_10', normalize_promocode('  chef_10 '), 'Code normalization');
assert_same(30.0, promocode_discount_amount(300, 10), 'Discount amount');
assert_same(true, promocode_belongs_to_cook(['cook_id' => 7], 7), 'Owner matches');
assert_same(false, promocode_belongs_to_cook(['cook_id' => 8], 7), 'Other cook is isolated');

$allocated = promocode_discount_lines([10.01, 20.02], 10);
assert_same(3.0, $allocated['discount'], 'Discount rounds to cents');
assert_same(27.03, round(array_sum($allocated['line_totals']), 2), 'Discounted lines sum correctly');

foreach (['x', 'bad code', str_repeat('a', 41)] as $invalidCode) {
    try {
        normalize_promocode($invalidCode);
        throw new RuntimeException('Invalid code was accepted: ' . $invalidCode);
    } catch (InvalidArgumentException $expected) {
        // Expected validation failure.
    }
}

echo "Promocode unit tests passed.\n";
