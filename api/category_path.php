<?php
declare(strict_types=1);

function resolve_dish_category(PDO $pdo, array $data, ?int $fallback = null): ?int
{
    $mainId = (int) ($data['main_category_id'] ?? 0);
    $subId = (int) ($data['subcategory_id'] ?? 0);
    $subsubId = (int) ($data['subsubcategory_id'] ?? 0);
    if ($mainId < 1 || $subId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT parent_id FROM categories WHERE id = ?');
    $stmt->execute([$mainId]);
    $mainParent = $stmt->fetchColumn();
    if ($mainParent === false || $mainParent !== null) {
        return null;
    }
    $stmt->execute([$subId]);
    if ((int) $stmt->fetchColumn() !== $mainId) {
        return null;
    }

    if ($subsubId > 0) {
        $stmt->execute([$subsubId]);
        if ((int) $stmt->fetchColumn() !== $subId) {
            return null;
        }
        return $subsubId;
    }
    return $subId;
}
