<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

function json_out($v, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function need_auth(): void {
    if (empty($_SESSION['ok'])) json_out(['error' => 'no autorizado'], 401);
}
function body(): array {
    return json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {

case 'login':
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals(ADMIN_PASS, $pw)) { $_SESSION['ok'] = true; json_out(['ok' => true]); }
    json_out(['error' => 'clave incorrecta'], 401);

case 'logout':
    session_destroy();
    json_out(['ok' => true]);

case 'me':
    json_out(['auth' => !empty($_SESSION['ok'])]);

case 'list':
    json_out(jload(DATA_FILE, []));

case 'add':
case 'update':
    need_auth();
    $in    = body();
    $title = trim((string)($in['title'] ?? ''));
    $slug  = slug_from_url(trim((string)($in['url'] ?? '')));
    if ($title === '' || !$slug) json_out(['error' => 'Falta el título o la URL de YourUpload'], 422);

    $row = [
        'id'      => $action === 'update' ? (string)($in['id'] ?? '') : bin2hex(random_bytes(6)),
        'title'   => $title,
        'type'    => (($in['type'] ?? 'serie') === 'pelicula') ? 'pelicula' : 'serie',
        'poster'  => trim((string)($in['poster'] ?? '')),
        'season'  => ($in['season']  ?? '') !== '' ? (int)$in['season']  : null,
        'episode' => ($in['episode'] ?? '') !== '' ? (int)$in['episode'] : null,
        'epname'  => trim((string)($in['epname'] ?? '')),
        'slug'    => $slug,
        'added'   => time(),
    ];

    $items = jload(DATA_FILE, []);
    if ($action === 'update') {
        $found = false;
        foreach ($items as $i => $it) {
            if ($it['id'] === $row['id']) { $items[$i] = $row; $found = true; break; }
        }
        if (!$found) json_out(['error' => 'no encontrado'], 404);
    } else {
        $items[] = $row;
    }
    jsave(DATA_FILE, $items);
    json_out(['ok' => true, 'item' => $row]);

case 'delete':
    need_auth();
    $id    = (string)(body()['id'] ?? '');
    $items = array_values(array_filter(jload(DATA_FILE, []), fn($x) => $x['id'] !== $id));
    jsave(DATA_FILE, $items);
    json_out(['ok' => true]);

case 'm3u8':
    $items = jload(DATA_FILE, []);
    usort($items, fn($a, $b) =>
        [$a['title'], $a['season'] ?? 0, $a['episode'] ?? 0]
        <=> [$b['title'], $b['season'] ?? 0, $b['episode'] ?? 0]);

    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Cache-Control: no-cache');
    echo "#EXTM3U\n";
    foreach ($items as $it) {
        $name = $it['title'];
        if ($it['type'] === 'serie' && $it['season'] !== null) {
            $name .= sprintf(' S%02dE%02d', $it['season'], (int)$it['episode']);
        }
        if ($it['epname'] !== '') $name .= ' - ' . $it['epname'];
        printf(
            "#EXTINF:-1 tvg-id=\"%s\" tvg-logo=\"%s\" group-title=\"%s\",%s\n%s\n",
            htmlspecialchars($it['id']), htmlspecialchars($it['poster']),
            htmlspecialchars($it['title']), $name, stream_url($it['id'])
        );
    }
    exit;

case 'stream':
    $id    = (string)($_GET['id'] ?? '');
    $items = jload(DATA_FILE, []);
    $it    = null;
    foreach ($items as $x) if ($x['id'] === $id) { $it = $x; break; }
    if (!$it) { http_response_code(404); exit('no encontrado'); }

    $media = resolve_media($it['slug']);
    if (!$media) { http_response_code(404); exit('media no resuelta'); }

    $hdrs = ['Referer: ' . REFERER];
    if (!empty($_SERVER['HTTP_RANGE'])) $hdrs[] = 'Range: ' . $_SERVER['HTTP_RANGE'];

    $ch = curl_init($media);
    $status = 200; $rh = []; $sent = false;

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $hdrs,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$status, &$rh) {
            $l = strlen($h);
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) { $status = (int)$m[1]; $rh = []; return $l; }
            $p = strpos($h, ':');
            if ($p !== false) $rh[strtolower(trim(substr($h, 0, $p)))] = trim(substr($h, $p + 1));
            return $l;
        },
        CURLOPT_WRITEFUNCTION  => function ($c, $d) use (&$status, &$rh, &$sent) {
            if (!$sent) {
                $sent = true;
                http_response_code($status);
                foreach (['content-type','content-length','content-range','accept-ranges','etag','last-modified'] as $k) {
                    if (isset($rh[$k])) header($k . ': ' . $rh[$k]);
                }
                header('Accept-Ranges: bytes');
                header('Access-Control-Allow-Origin: *');
            }
            echo $d;
            return strlen($d);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;

default:
    json_out(['error' => 'acción desconocida'], 404);
}