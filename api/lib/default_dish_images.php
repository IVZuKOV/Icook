<?php
declare(strict_types=1);

/**
 * Keep uploaded images untouched; provide a bundled illustration only when
 * the dish/recipe has no user-supplied photo.
 */
function apply_default_dish_images(array $items): array
{
    $rules = [
        'salad' => '/салат|salad/u',
        'dessert' => '/торт|пирог|десерт|пирож|морож|кекс|конфет|чизкейк|cake|dessert/u',
        'juice' => '/напит|сок|морс|лимонад|коктейл|смузи|juice|drink|beverage/u',
        'noodles' => '/рамен|лапш|удон|суп|борщ|бульон|ramen|noodle|soup/u',
    ];
    $paths = [
        'salad' => '/assets/images/default-dishes/salad.jpg',
        'dessert' => '/assets/images/default-dishes/dessert.jpg',
        'juice' => '/assets/images/default-dishes/juice.jpg',
        'noodles' => '/assets/images/default-dishes/noodles.jpg',
    ];

    foreach ($items as &$item) {
        if (!empty(trim((string) ($item['image'] ?? '')))) {
            continue;
        }

        $title = mb_strtolower((string) ($item['title'] ?? ''), 'UTF-8');
        $category = mb_strtolower((string) ($item['category_name'] ?? ''), 'UTF-8');
        $text = $title . ' ' . $category;
        $matched = null;
        foreach ($rules as $key => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                $matched = $key;
                break;
            }
        }
        if ($matched === null) {
            foreach ($rules as $key => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $matched = $key;
                    break;
                }
            }
        }

        // Use the closest general food illustration for savory/unclassified items.
        $item['image'] = $paths[$matched ?? 'noodles'];
    }
    unset($item);

    return $items;
}
