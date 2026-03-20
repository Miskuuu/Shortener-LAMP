<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, [
        'error' => 'Method not allowed'
    ]);
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendJsonResponse(400, [
        'error' => 'id is required'
    ]);
}

$urlId = (int) $_GET['id'];

try {
    $data = getDailyFrequencyChartData($urlId);
    sendJsonResponse(200, $data);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    sendJsonResponse($code, [
        'error' => $e->getMessage()
    ]);
}