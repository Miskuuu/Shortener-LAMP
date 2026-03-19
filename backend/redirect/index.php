<?php

require_once __DIR__ . '/../api/helpers/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

if (!isset($_GET['code']) || empty(trim($_GET['code']))) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'shortCode is required';
    exit;
}

try {
    $shortCode = trim($_GET['code']);

    // 1) Buscar URL por shortCode
    $url = getUrlByShortCode($shortCode);

    // 2) Obtener IP y User-Agent
    $ip = getClientIpAddress();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 3) Guardar visita (incluye resolución de país)
    logVisit($url['_id'], $ip, $userAgent);

    // 4) Incrementar contador
    incrementClickCount($url['_id']);

    // 5) Redirigir
    header('Location: ' . $url['originalUrl']);
    exit;
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 404;
    }

    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage() ?: 'Not found';
    exit;
}