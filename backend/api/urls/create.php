<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'error' => 'Method not allowed'
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['originalUrl']) || empty(trim($input['originalUrl']))) {
    sendJsonResponse(400, [
        'error' => 'originalUrl is required'
    ]);
}

try {
    $url = createUrl($input['originalUrl']);
    sendJsonResponse(201, $url);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    sendJsonResponse($code, [
        'error' => $e->getMessage()
    ]);
}