<?php

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, [
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['url_id'])) {
    sendJsonResponse(400, [
        'success' => false,
        'message' => 'url_id is required'
    ]);
}

$urlId = (int)$input['url_id'];
$ipAddress = isset($input['ip_address']) ? sanitizeInput($input['ip_address']) : null;
$userAgent = isset($input['user_agent']) ? sanitizeInput($input['user_agent']) : null;

try {
    $visit = logVisit($urlId, $ipAddress, $userAgent);

    sendJsonResponse(201, [
        'success' => true,
        'message' => 'Visit registered successfully',
        'data' => $visit
    ]);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    sendJsonResponse($code, [
        'success' => false,
        'message' => $e->getMessage()
    ]);
}