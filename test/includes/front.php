<?php
declare(strict_types=1);

function front_category_key(string $slug): string
{
    return match ($slug) {
        'dagens-ret' => 'dagens',
        'cafe-sandwich' => 'cafe',
        'catering-fest' => 'catering',
        default => preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)),
    };
}

function front_category_icon(string $key): string
{
    return match ($key) {
        'dagens' => 'fa-bowl-food',
        'cafe' => 'fa-mug-hot',
        'tapas' => 'fa-wine-glass',
        'catering' => 'fa-champagne-glasses',
        default => 'fa-utensils',
    };
}


function front_meeting_default_points(): array
{
    return [
        '1 bolle med smør, ost og marmelade',
        '1 morgenkage',
        'Kaffe/the',
        '1 stk. pålæg',
        'Frisk skåret frugt',
        'Juice',
    ];
}

function front_meeting_points_setting_value(): string
{
    $default = implode("\n", front_meeting_default_points());
    $stored = trim((string)setting('meeting_points', ''));
    if ($stored === '') return $default;

    $normalize = static function (string $value): string {
        $lines = front_meeting_points($value, []);
        $lines = array_map(static function (string $line): string {
            $line = function_exists('mb_strtolower') ? mb_strtolower(trim($line), 'UTF-8') : strtolower(trim($line));
            return preg_replace('/\s+/u', ' ', $line) ?? $line;
        }, $lines);
        return implode('|', $lines);
    };

    $legacyValues = [
        "Morgenmad\nFrokost\nSnacks\nAftensmad eller buffet",
        "Morgenmad\nFrokost\nSnaks\nAftensmad - buffet",
        "Morgenmad\nFrokost\nSnacks\nAftensmad – buffet",
    ];
    $storedNormalized = $normalize($stored);
    foreach ($legacyValues as $legacy) {
        if ($storedNormalized === $normalize($legacy)) return $default;
    }
    return $stored;
}

function front_group_nav_description(array $group): string
{
    $titles = [];
    foreach (($group['items'] ?? []) as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title !== '') $titles[] = $title;
        if (count($titles) >= 3) break;
    }
    if (!$titles) return 'Se aktuelle retter og priser';
    $text = implode(' · ', $titles);
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 74) {
        $text = rtrim(mb_substr($text, 0, 71, 'UTF-8')) . '…';
    }
    return $text;
}

function front_weekday_sort_value(string $title): int
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($title), 'UTF-8') : strtolower(trim($title));
    $weekdays = [
        'mandag' => 1,
        'tirsdag' => 2,
        'onsdag' => 3,
        'torsdag' => 4,
        'fredag' => 5,
        'lørdag' => 6,
        'søndag' => 7,
    ];
    foreach ($weekdays as $day => $order) {
        if (preg_match('/(?<![\p{L}])' . preg_quote($day, '/') . '(?![\p{L}])/u', $normalized) === 1) return $order;
    }
    return 99;
}

function front_has_manual_sort_order(array $items): bool
{
    foreach ($items as $item) {
        if ((int)($item['sort_order'] ?? 0) > 0) return true;
    }
    return false;
}

function front_size_base_key(string $value): string
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
    $normalized = preg_replace('/(?:\s*[-–—]?\s*)(?:xl|alm\.?|almindelig)\s*$/u', '', $normalized) ?? $normalized;
    $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
    return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
}

function front_is_legacy_xl_item(array $item): bool
{
    $slug = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['slug'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['slug'] ?? '')));
    $title = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['title'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['title'] ?? '')));
    return str_ends_with($slug, '-xl') || preg_match('/(?:^|[\s\-–—])xl\s*$/u', $title) === 1;
}

function front_remove_legacy_xl_duplicates(array $items): array
{
    $combinedSizeKeys = [];
    foreach ($items as $item) {
        if ((float)($item['xl_price'] ?? 0) <= 0) continue;
        $slugKey = front_size_base_key((string)($item['slug'] ?? ''));
        $titleKey = front_size_base_key((string)($item['title'] ?? ''));
        if ($slugKey !== '') $combinedSizeKeys['slug:' . $slugKey] = true;
        if ($titleKey !== '') $combinedSizeKeys['title:' . $titleKey] = true;
    }

    return array_values(array_filter($items, static function (array $item) use ($combinedSizeKeys): bool {
        if (!front_is_legacy_xl_item($item) || (float)($item['xl_price'] ?? 0) > 0) return true;
        $slugKey = front_size_base_key((string)($item['slug'] ?? ''));
        $titleKey = front_size_base_key((string)($item['title'] ?? ''));
        return !isset($combinedSizeKeys['slug:' . $slugKey]) && !isset($combinedSizeKeys['title:' . $titleKey]);
    }));
}

function front_find_menu_item(array $groups, string $slug): ?array
{
    $needle = function_exists('mb_strtolower') ? mb_strtolower(trim($slug), 'UTF-8') : strtolower(trim($slug));
    foreach ($groups as $group) {
        foreach (($group['items'] ?? []) as $item) {
            $itemSlug = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['slug'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['slug'] ?? '')));
            $itemTitle = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($item['title'] ?? '')), 'UTF-8') : strtolower(trim((string)($item['title'] ?? '')));
            if ($itemSlug === $needle || $itemTitle === $needle) return $item;
        }
    }
    return null;
}

function front_is_meeting_item(array $item): bool
{
    $haystack = trim((string)($item['slug'] ?? '') . ' ' . (string)($item['title'] ?? ''));
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, 'mødeforplejning') || str_contains($haystack, 'moedeforplejning');
}

function front_meeting_item(): ?array
{
    try {
        $rows = db()->query('SELECT * FROM menu_items ORDER BY id')->fetchAll();
        foreach ($rows as $item) {
            if (front_is_meeting_item($item)) return $item;
        }
    } catch (Throwable) {
    }
    return null;
}

function front_meeting_points(string $value, array $fallback): array
{
    $points = [];
    foreach (preg_split('/\R+/', trim($value)) ?: [] as $line) {
        $line = trim((string)$line);
        $line = preg_replace('/^[*•-]\s*/u', '', $line) ?? $line;
        if ($line !== '') $points[] = $line;
    }
    return $points ?: $fallback;
}

function front_menu_item_text_parts(?array $item): array
{
    $fallback = [
        'intro' => 'Vi tilbyder mødeforplejning efter jeres ønsker – både i vores mødelokaler og ud af huset.',
        'points' => front_meeting_default_points(),
    ];
    if (!$item) return $fallback;

    $description = trim(str_replace(["\r\n", "\r"], "\n", (string)($item['description'] ?? '')));
    if ($description === '') return $fallback;

    $intro = [];
    $points = [];
    $hasStartedPoints = false;
    foreach (preg_split('/\n+/', $description) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^[*•-]\s*(.+)$/u', $line, $match) === 1) {
            $hasStartedPoints = true;
            $point = trim($match[1]);
            $pointNormalized = function_exists('mb_strtolower') ? mb_strtolower($point, 'UTF-8') : strtolower($point);
            if ($pointNormalized === 'snaks') $point = 'Snacks';
            if (preg_match('/^aftensmad\s*[-–—]\s*buffet$/u', $pointNormalized) === 1) $point = 'Aftensmad eller buffet';
            $points[] = $point;
        } elseif (!$hasStartedPoints) {
            // Tekst efter punktlisten (fx “Kontakt os pr. mail”) hører ikke
            // til introduktionen i den selvstændige sektion.
            $intro[] = $line;
        }
    }

    return [
        'intro' => $intro ? implode(' ', $intro) : $fallback['intro'],
        'points' => $points ?: $fallback['points'],
    ];
}

function front_menu_data(): array
{
    try {
        $cats = db()->query('SELECT * FROM menu_categories WHERE is_active=1 ORDER BY sort_order,name')->fetchAll();
        $stmt = db()->query('SELECT m.*, c.slug category_slug, c.name category_name, c.description category_description
            FROM menu_items m
            JOIN menu_categories c ON c.id=m.category_id
            WHERE c.is_active=1
            ORDER BY c.sort_order,m.sort_order,m.title');
        $items = $stmt->fetchAll();
        $grouped = [];
        foreach ($cats as $c) {
            $key = front_category_key((string)$c['slug']);
            $grouped[$key] = ['category' => $c, 'key' => $key, 'items' => []];
        }
        foreach ($items as $item) {
            $key = front_category_key((string)$item['category_slug']);
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['category' => ['name' => $item['category_name'], 'description' => '', 'slug' => $item['category_slug']], 'key' => $key, 'items' => []];
            }
            $grouped[$key]['items'][] = $item;
        }

        // Ældre særskilte XL-rækker skjules automatisk på kundesiden, når den
        // tilsvarende ret allerede har et udfyldt XL-prisfelt.
        foreach ($grouped as &$group) {
            $group['items'] = front_remove_legacy_xl_duplicates($group['items']);
            $group['items'] = array_values(array_filter(
                $group['items'],
                static fn(array $item): bool => (int)($item['is_available'] ?? 0) === 1 && !front_is_meeting_item($item)
            ));
        }
        unset($group);

        // Dagens ret bruger ugedagsrækkefølge som sikker standard, indtil Eva
        // aktivt har gemt en manuel rækkefølge i administrationen.
        if (isset($grouped['dagens']) && !front_has_manual_sort_order($grouped['dagens']['items'])) {
            usort($grouped['dagens']['items'], static function (array $a, array $b): int {
                $dayCompare = front_weekday_sort_value((string)$a['title']) <=> front_weekday_sort_value((string)$b['title']);
                if ($dayCompare !== 0) return $dayCompare;
                return strnatcasecmp((string)$a['title'], (string)$b['title']);
            });
        }

        return array_values(array_filter($grouped, fn($g) => count($g['items']) > 0));
    } catch (Throwable) {
        return [];
    }
}

function front_gallery_images(): array
{
    $fallback = [
        ['src' => 'assets/img/714144165_122132170959006079_5434425460200594952_n.jpg', 'alt' => 'Ribbensandwich fra Café LIF'],
        ['src' => 'assets/img/720317659_122132568771006079_5289448677620056085_n.jpg', 'alt' => 'Hakkebøf fra Café LIF'],
        ['src' => 'assets/img/714759570_122132170983006079_8702016659159945239_n.jpg', 'alt' => 'Chicken panang fra Café LIF'],
        ['src' => 'assets/img/722704837_122132835309006079_4165552897409574002_n.jpg', 'alt' => 'Boller i karry fra Café LIF'],
        ['src' => 'assets/img/718153672_122132639439006079_867205327621101498_n.jpg', 'alt' => 'Clubsandwich fra Café LIF'],
        ['src' => 'assets/img/721644312_122132834997006079_5261245969256518294_n.jpg', 'alt' => 'Sliders fra Café LIF'],
        ['src' => 'assets/img/719573141_122132835315006079_4234774349399408606_n.jpg', 'alt' => 'Forloren hare fra Café LIF'],
    ];
    try {
        $rows = db()->query("SELECT title, image_path FROM food_posts WHERE is_active=1 AND image_path IS NOT NULL AND image_path<>'' ORDER BY sort_order,published_at DESC LIMIT 12")->fetchAll();
        if (!$rows) return $fallback;
        return array_map(fn($r) => ['src' => (string)$r['image_path'], 'alt' => (string)$r['title']], $rows);
    } catch (Throwable) {
        return $fallback;
    }
}

function front_money_label(array $item): string
{
    if ($item['price'] === null || $item['price'] === '' || (float)$item['price'] <= 0) return 'Efter aftale';

    $normal = money($item['price']);
    $xl = isset($item['xl_price']) && $item['xl_price'] !== null && $item['xl_price'] !== '' && (float)$item['xl_price'] > 0
        ? money($item['xl_price'])
        : null;

    $label = $xl !== null
        ? '<span class="menu-price-option"><small>Alm.</small> ' . h($normal) . '</span><span class="menu-price-separator">·</span><span class="menu-price-option"><small>XL</small> ' . h($xl) . '</span>'
        : h($normal);

    if (!empty($item['price_suffix'])) $label .= ' <small>' . h((string)$item['price_suffix']) . '</small>';
    return $label;
}

function front_tel_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}
