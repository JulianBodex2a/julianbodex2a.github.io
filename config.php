<?php
declare(strict_types=1);

// ============ AJUSTES ============
const ADMIN_PASS = '140198';
const TOKEN_TTL  = 1800;
const PRETTY_URLS = true;
const BASE_OVERRIDE = 'https://jubodex.gamer.free';
const DATA_FILE  = __DIR__ . '/data.json';
const CACHE_FILE = __DIR__ . '/tokencache.json';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:158.0) Gecko/20100101 Firefox/158.0';
const REFERER = 'https://www.yourupload.com/';

// ============ utilidades ============
function jload(string $f, $def = []) {
    if (!is_file($f)) return $def;
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : $def;
}
function jsave(string $f, $v): void {
    file_put_contents($f,
        json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX);
}
function base_url(): string {
    if (BASE_OVERRIDE !== '') return rtrim(BASE_OVERRIDE, '/');
    $s   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $s . '://' . $_SERVER['HTTP_HOST'] . $dir;
}
function stream_url(string $id): string {
    return PRETTY_URLS
        ? base_url() . '/stream/' . $id . '.mp4'
        : base_url() . '/api.php?action=stream&id=' . $id;
}
function slug_from_url(string $u): ?string {
    return preg_match('#(?:watch|embed)/([A-Za-z0-9]+)#', $u, $m) ? $m[1] : null;
}
function curl_get(string $url, array $headers = [], int $timeout = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => UA,
    ]);
    $r    = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($r !== false && $code < 400) ? $r : null;
}
function probe(string $url): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RANGE          => '0-0',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Referer: ' . REFERER],
        CURLOPT_USERAGENT      => UA,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
function resolve_media(string $slug): ?string {
    $cache = jload(CACHE_FILE, []);
    $now   = time();

    if (!empty($cache[$slug]['url']) && $cache[$slug]['exp'] > $now) {
        if ($now - ($cache[$slug]['born'] ?? 0) <= 60) return $cache[$slug]['url'];
        if (probe($cache[$slug]['url'])) return $cache[$slug]['url'];
    }

    $html = curl_get('https://www.yourupload.com/embed/' . $slug, ['Referer: ' . REFERER]);
    if (!$html) return null;

    if (!preg_match("/file:\s*'([^']+\.mp4[^']*)'/", $html, $m)
        && !preg_match('/og:video"\s+content="([^"]+)"/', $html, $m)) return null;

    $cache[$slug] = ['url' => $m[1], 'exp' => $now + TOKEN_TTL, 'born' => $now];
    jsave(CACHE_FILE, $cache);
    return $m[1];
}
