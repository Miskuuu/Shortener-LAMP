<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, [
        'error' => 'Method not allowed'
    ]);
}

try {
    $urls = getAllUrls();
    sendJsonResponse(200, $urls);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    sendJsonResponse($code, [
        'error' => $e->getMessage()
    ]);
}