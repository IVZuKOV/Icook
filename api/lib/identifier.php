<?php
declare(strict_types=1);

require_once __DIR__ . '/phone.php';

/**
 * Classify a login identifier: email, phone or login (name).
 * Returns [kind, normalized value].
 */
function iks_identify_input(string $input): array
{
    $input = trim($input);

    if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
        return ['email', mb_strtolower($input)];
    }

    if (preg_match('/^\+?[0-9\s\-()]{7,20}$/', $input)) {
        return ['phone', $input];
    }

    return ['login', $input];
}

/**
 * Find users by email, phone or name.
 * Returns [kind, rows].
 */
function iks_find_users_by_identifier(PDO $pdo, string $input, int $limit = 5): array
{
    [$kind, $value] = iks_identify_input($input);
    $limit = max(1, min(20, $limit));

    if ($kind === 'email') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ? ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute([$value]);
        return [$kind, $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } elseif ($kind === 'phone') {
        $variants = iks_phone_variants($value);
        if ($variants === []) {
            return [$kind, []];
        }
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE REGEXP_REPLACE(phone, '[^0-9]', '') IN ($placeholders) ORDER BY id ASC LIMIT " . $limit
        );
        $stmt->execute($variants);
        return [$kind, $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(name) = LOWER(?) ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute([$value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            return [$kind, $rows];
        }
        // Fallback: try matching by email local-part or partial name
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(SUBSTRING_INDEX(email,"@",1)) = LOWER(?) OR LOWER(name) LIKE LOWER(?) ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute([$value, '%' . $value . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [$kind, $rows];
    }
}

function iks_mask_email(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }

    return mb_substr($parts[0], 0, 1) . '***@' . $parts[1];
}
