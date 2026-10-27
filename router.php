<?php
// router.php — SOLO para el servidor de desarrollo de PHP.
// Traduce /lista.m3u8 y /stream/x.mp4 a api.php (el .htaccess no lo lee este servidor).

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/stream/([A-Za-z0-9]+)\.mp4$#', $path, $m)) {
    $_GET['action'] = 'stream';
    $_GET['id']     = $m[1];
    require __DIR__ . '/api.php';
    return true;
}

if ($path === '/lista.m3u8') {
    $_GET['action'] = 'm3u8';
    require __DIR__ . '/api.php';
    return true;
}

// ficheros estáticos (index.html, admin.html, api.php…) los sirve el propio servidor
if ($path === '/' || is_file(__DIR__ . $path)) return false;

require __DIR__ . '/api.php';
return true;