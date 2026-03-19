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

$id = (int) $_GET['id'];

try {
    $stats = getStatsByUrlId($id);
    $countries = getCountriesByUrlId($id);
    $chart = getDailyFrequencyChartData($id);

    sendJsonResponse(200, [
        'urlId' => (string) $id,
        'totalVisits' => $stats['totalVisits'],
        'visits' => $stats['visits'],
        'dailyBreakdown' => $stats['dailyBreakdown'],
        'countries' => $countries['countries'],
        'chart' => $chart
    ]);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    sendJsonResponse($code, [
        'error' => $e->getMessage()
    ]);
}