<?php
declare(strict_types=1);

function store_uploaded_image(array $file, string $folder = 'menu'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Billedet kunne ikke uploades. Prøv et andet billede eller gem som JPG/PNG først.');
    }

    if (($file['size'] ?? 0) > 12 * 1024 * 1024) {
        throw new RuntimeException('Billedet må højst fylde 12 MB. Beskær eller komprimer billedet og prøv igen.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Billedet kunne ikke læses efter upload. Prøv igen.');
    }

    $info = @getimagesize($tmp);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Kun JPG, PNG og WebP er tilladt. Hvis billedet kommer fra iPhone, så gem det som JPG først.');
    }

    $safeFolder = $folder === 'posts' ? 'posts' : 'menu';
    $targetDir = dirname(__DIR__) . '/uploads/' . $safeFolder;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Uploadmappen kunne ikke oprettes. Tjek rettigheder på uploads-mappen.');
    }

    $basename = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
    $canWebp = extension_loaded('gd') && function_exists('imagewebp');

    if ($canWebp) {
        [$width, $height] = $info;
        $max = 1600;
        $scale = min(1, $max / max($width, $height));
        $newW = max(1, (int)round($width * $scale));
        $newH = max(1, (int)round($height * $scale));

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmp) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmp) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };

        if ($src) {
            $relative = '/uploads/' . $safeFolder . '/' . $basename . '.webp';
            $target = dirname(__DIR__) . $relative;
            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
            $saved = @imagewebp($dst, $target, 84);
            imagedestroy($src);
            imagedestroy($dst);
            if ($saved && is_file($target)) {
                @chmod($target, 0644);
                return $relative;
            }
        }
    }

    $ext = $allowed[$info[2]];
    $relative = '/uploads/' . $safeFolder . '/' . $basename . '.' . $ext;
    $target = dirname(__DIR__) . $relative;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Billedet kunne ikke gemmes. Tjek rettigheder på uploads-mappen.');
    }
    @chmod($target, 0644);
    return $relative;
}
