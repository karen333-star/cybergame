<?php

header('Content-Type: application/json; charset=utf-8');

function responder_musica(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$musicDir = __DIR__ . DIRECTORY_SEPARATOR . 'music';
$extensiones = ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'webm'];
$archivos = [];

foreach ($extensiones as $extension) {
    $coincidencias = glob($musicDir . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [];
    if (!empty($coincidencias)) {
        $archivos = array_merge($archivos, $coincidencias);
    }
}

$archivos = array_values(array_unique($archivos));

if (empty($archivos)) {
    responder_musica(['ok' => false, 'error' => 'NO_AUDIO_FILES'], 404);
}

$archivoSeleccionado = $archivos[random_int(0, count($archivos) - 1)];
$nombreArchivo = basename($archivoSeleccionado);

responder_musica([
    'ok' => true,
    'file' => 'music/' . rawurlencode($nombreArchivo),
    'name' => $nombreArchivo,
]);
