<?php
require_once __DIR__ . '/config.php';

const ROOM_UPLOAD_DIR  = __DIR__ . '/uploads/rooms';
const ROOM_PHOTO_MAX   = 2 * 1024 * 1024; // 2 MB per photo (after browser resize)
const ROOM_PHOTO_LIMIT = 10;              // photos per room (the API enforces the same limit)

/** Public web address for a saved photo file (set PUBLIC_URL in config.php to override). */
function roomPhotoUrl(string $file): string
{
    if (defined('PUBLIC_URL')) {
        $base = rtrim(PUBLIC_URL, '/') . '/';
    } else {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $base  = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
    }
    return $base . 'uploads/rooms/' . $file;
}

/** Normalizes $_FILES['photos'] (multiple) into a list keyed by the file's position. */
function collectUploads(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) return [];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[$i] = ['tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Validates and saves one uploaded photo. Returns [filename|null, error|null]. */
function saveUpload(array $f): array
{
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'A photo is too large (max 2 MB each).'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'A photo failed to upload. Please try again.'];
    if ($f['size'] > ROOM_PHOTO_MAX)   return [null, 'A photo is too large (max 2 MB each).'];

    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($types[$mime]) || !@getimagesize($f['tmp_name'])) {
        return [null, 'Photos must be JPG, PNG or WebP images.'];
    }

    if (!is_dir(ROOM_UPLOAD_DIR) && !mkdir(ROOM_UPLOAD_DIR, 0755, true)) {
        return [null, 'Could not create the uploads folder.'];
    }
    $name = 'room-' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
    if (!move_uploaded_file($f['tmp_name'], ROOM_UPLOAD_DIR . '/' . $name)) {
        return [null, 'Could not save a photo.'];
    }
    return [$name, null];
}

/** Deletes a photo file from disk by its URL. Only touches files this system created (room-<hex>.ext). */
function deleteStoredPhoto(string $url): void
{
    $path = preg_match('#^https?://#i', $url) ? (string)parse_url($url, PHP_URL_PATH) : $url;
    if (!preg_match('#(?:^|/)(room-[a-f0-9]{16}\.(?:jpg|png|webp))$#', $path, $m)) return;
    $file = ROOM_UPLOAD_DIR . '/' . $m[1];
    if (is_file($file)) @unlink($file);
}