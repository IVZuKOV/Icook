<?php
declare(strict_types=1);

/**
 * Хелперы для телефонов, используемые в обратном SMS-входе.
 * Хранимые значения не гарантированно каноничны, поэтому поиск идёт по
 * вариантам, содержащим только цифры. Новые значения пишем в виде +7XXXXXXXXXX.
 */

function iks_phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function iks_normalize_phone(string $phone): ?string
{
    $digits = iks_phone_digits($phone);
    if ($digits === '') {
        return null;
    }

    if (strlen($digits) === 10) {
        $digits = '7' . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === '8') {
        $digits = '7' . substr($digits, 1);
    }

    if (strlen($digits) < 10 || strlen($digits) > 15) {
        return null;
    }

    return '+' . $digits;
}

function iks_phone_variants(string $phone): array
{
    $digits = iks_phone_digits($phone);
    if ($digits === '') {
        return [];
    }

    $variants = [$digits];
    if (strlen($digits) === 10) {
        $variants[] = '7' . $digits;
        $variants[] = '8' . $digits;
    } elseif (strlen($digits) === 11 && $digits[0] === '8') {
        $variants[] = '7' . substr($digits, 1);
    } elseif (strlen($digits) === 11 && $digits[0] === '7') {
        $variants[] = '8' . substr($digits, 1);
        $variants[] = substr($digits, 1);
    }

    return array_values(array_unique(array_filter(
        $variants,
        static fn(string $value): bool => strlen($value) >= 7 && strlen($value) <= 15
    )));
}

function iks_find_user_by_phone(PDO $pdo, string $phone, bool $lock = false): ?array
{
    $variants = iks_phone_variants($phone);
    if ($variants === []) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($variants), '?'));
    $sql = "SELECT id, name, email, phone, role
            FROM users
            WHERE REGEXP_REPLACE(phone, '[^0-9]', '') IN ($placeholders)
            ORDER BY id ASC
            LIMIT 1" . ($lock ? ' FOR UPDATE' : '');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($variants);
    $user = $stmt->fetch();

    return $user ?: null;
}

function iks_mask_phone(string $phone): string
{
    $normalized = iks_normalize_phone($phone);
    if ($normalized === null) {
        return '***';
    }

    $digits = substr($normalized, 1);

    return '+' . substr($digits, 0, 1) . '***' . substr($digits, -2);
}

function iks_format_phone(string $phone): string
{
    $normalized = iks_normalize_phone($phone);
    if ($normalized === null) {
        return $phone;
    }

    $digits = substr($normalized, 1);
    if (strlen($digits) === 11 && $digits[0] === '7') {
        return '+7 (' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7, 2) . '-' . substr($digits, 9, 2);
    }

    return $normalized;
}
