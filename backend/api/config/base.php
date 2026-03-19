<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    sendJsonResponse(200, [
        'baseUrl' => getBaseUrl(),
        'redirectPath' => getRedirectPath()
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'error' => 'Method not allowed'
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input['baseUrl'])) {
    sendJsonResponse(400, [
        'error' => 'baseUrl is required'
    ]);
}

$baseUrl = rtrim(trim($input['baseUrl']), '/');
if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    sendJsonResponse(400, [
        'error' => 'baseUrl must be a valid URL'
    ]);
}

$redirectPath = '/backend/redirect/index.php';
if (!empty($input['redirectPath'])) {
    $redirectPath = '/' . ltrim(trim($input['redirectPath']), '/');
}

$config = readAppConfig();
$config['baseUrl'] = $baseUrl;
$config['redirectPath'] = $redirectPath;

if (!writeAppConfig($config)) {
    sendJsonResponse(500, [
        'error' => 'Failed to persist base configuration'
    ]);
}

sendJsonResponse(200, [
    'baseUrl' => $baseUrl,
    'redirectPath' => $redirectPath
]);
