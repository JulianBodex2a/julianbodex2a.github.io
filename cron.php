<?php
// Refresca todos los tokens y avisa de los que fallan.
// cron: */20 * * * * php /ruta/cron.php >> /ruta/cron.log 2>&1
require __DIR__ . '/config.php';

$items = jload(DATA_FILE, []);
if (!$items) { echo date('c') . " catálogo vacío\n"; exit; }

foreach ($items as $it) {
    $u = resolve_media($it['slug']);
    if ($u && probe($u)) {
        echo date('c') . " OK   {$it['slug']} {$it['title']}\n";
    } else {
        echo date('c') . " FALLO {$it['slug']} {$it['title']} — revisa si sigue en pie\n";
    }
}