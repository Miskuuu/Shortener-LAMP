<?php

require_once __DIR__ . '/../config/database.php';

function generateShortCode($length = 5)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $shortCode = '';

    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $shortCode;
}

function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($value)
{
    return trim($value ?? '');
}

function shortCodeExists($shortCode)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("SELECT id FROM urls WHERE short_code = :short_code LIMIT 1");
    $stmt->execute(['short_code' => $shortCode]);

    return $stmt->fetch() !== false;
}

function generateUniqueShortCode($length = 5)
{
    do {
        $shortCode = generateShortCode($length);
    } while (shortCodeExists($shortCode));

    return $shortCode;
}

function formatUrlRow($url)
{
    $baseUrl = getBaseUrl();

    return [
        '_id' => (int)$url['id'],
        'originalUrl' => $url['original_url'],
        'shortCode' => $url['short_code'],
        'clickCount' => (int)$url['click_count'],
        'createdAt' => $url['created_at'],
        'updatedAt' => $url['updated_at'] ?? null,
        'shortUrl' => $baseUrl . '/redirect/index.php?code=' . $url['short_code']
    ];
}

function createUrl($originalUrl)
{
    $originalUrl = sanitizeInput($originalUrl);

    if (!isValidUrl($originalUrl)) {
        throw new Exception('Invalid URL format', 400);
    }

    $pdo = getDatabaseConnection();
    $shortCode = generateUniqueShortCode(5);

    $stmt = $pdo->prepare("
        INSERT INTO urls (original_url, short_code, click_count)
        VALUES (:original_url, :short_code, 0)
    ");

    $stmt->execute([
        'original_url' => $originalUrl,
        'short_code' => $shortCode
    ]);

    $id = $pdo->lastInsertId();

    return getUrlById($id);
}

function getAllUrls()
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->query("
        SELECT id, original_url, short_code, click_count, created_at, updated_at
        FROM urls
        ORDER BY created_at DESC
    ");

    $rows = $stmt->fetchAll();
    $result = [];

    foreach ($rows as $row) {
        $result[] = formatUrlRow($row);
    }

    return $result;
}

function getUrlById($id)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT id, original_url, short_code, click_count, created_at, updated_at
        FROM urls
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $id]);
    $url = $stmt->fetch();

    if (!$url) {
        throw new Exception('URL not found', 404);
    }

    return formatUrlRow($url);
}

function getUrlByShortCode($shortCode)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT id, original_url, short_code, click_count, created_at, updated_at
        FROM urls
        WHERE short_code = :short_code
        LIMIT 1
    ");

    $stmt->execute(['short_code' => $shortCode]);
    $url = $stmt->fetch();

    if (!$url) {
        throw new Exception('Short code not found', 404);
    }

    return formatUrlRow($url);
}

function incrementClickCount($id)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        UPDATE urls
        SET click_count = click_count + 1
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);

    return getUrlById($id);
}

function logVisit($urlId, $ipAddress = null, $userAgent = null)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        INSERT INTO visits (url_id, ip_address, user_agent)
        VALUES (:url_id, :ip_address, :user_agent)
    ");

    $stmt->execute([
        'url_id' => $urlId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'url_id' => (int)$urlId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ];
}

function toDateString($dateTime)
{
    return date('Y-m-d', strtotime($dateTime));
}

function toChartLabel($dateStr)
{
    return date('M j', strtotime($dateStr));
}

function assertUrlExists($urlId)
{
    return getUrlById($urlId);
}

function getVisitsByUrlId($urlId)
{
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT id, url_id, ip_address, user_agent, visited_at
        FROM visits
        WHERE url_id = :url_id
        ORDER BY visited_at DESC
    ");

    $stmt->execute(['url_id' => $urlId]);
    return $stmt->fetchAll();
}

function getStatsByUrlId($urlId)
{
    assertUrlExists($urlId);

    $visits = getVisitsByUrlId($urlId);
    $dailyBreakdown = [];

    foreach ($visits as $visit) {
        $day = toDateString($visit['visited_at']);
        if (!isset($dailyBreakdown[$day])) {
            $dailyBreakdown[$day] = 0;
        }
        $dailyBreakdown[$day]++;
    }

    return [
        'totalVisits' => count($visits),
        'visits' => $visits,
        'dailyBreakdown' => $dailyBreakdown
    ];
}

function getCountryFromIp($ipAddress)
{
    if (empty($ipAddress)) {
        return 'Unknown';
    }

    if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        return 'Local';
    }

    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'Unknown';
    }

    return 'Unknown';
}

function getCountriesByUrlId($urlId)
{
    assertUrlExists($urlId);

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT ip_address
        FROM visits
        WHERE url_id = :url_id
    ");

    $stmt->execute(['url_id' => $urlId]);
    $visits = $stmt->fetchAll();

    $countryCount = [];

    foreach ($visits as $visit) {
        $country = getCountryFromIp($visit['ip_address']);

        if (!isset($countryCount[$country])) {
            $countryCount[$country] = 0;
        }

        $countryCount[$country]++;
    }

    $countries = [];
    foreach ($countryCount as $country => $count) {
        $countries[] = [
            'country' => $country,
            'count' => $count
        ];
    }

    usort($countries, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    return ['countries' => $countries];
}

function getDailyFrequencyChartData($urlId)
{
    assertUrlExists($urlId);

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT visited_at
        FROM visits
        WHERE url_id = :url_id
        ORDER BY visited_at ASC
    ");

    $stmt->execute(['url_id' => $urlId]);
    $visits = $stmt->fetchAll();

    $dateCount = [];

    foreach ($visits as $visit) {
        $day = toDateString($visit['visited_at']);

        if (!isset($dateCount[$day])) {
            $dateCount[$day] = 0;
        }

        $dateCount[$day]++;
    }

    $sortedDates = array_keys($dateCount);
    sort($sortedDates);

    $labels = [];
    $data = [];

    foreach ($sortedDates as $date) {
        $labels[] = toChartLabel($date);
        $data[] = $dateCount[$date];
    }

    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Visits',
                'data' => $data
            ]
        ]
    ];
}

function getClientIpAddress()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }

    return null;
}