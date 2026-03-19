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
    $redirectPath = getRedirectPath();

    return [
        '_id' => (int)$url['id'],
        'originalUrl' => $url['original_url'],
        'shortCode' => $url['short_code'],
        'clickCount' => (int)$url['click_count'],
        'createdAt' => $url['created_at'],
        'updatedAt' => $url['updated_at'] ?? null,
        'shortUrl' => $baseUrl . $redirectPath . '?code=' . rawurlencode($url['short_code'])
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

function httpGetJson($url, $timeoutSeconds = 3)
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Shortener-LAMP/1.0',
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $statusCode >= 400) {
            return null;
        }

        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'header' => "User-Agent: Shortener-LAMP/1.0\r\n",
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return null;
    }

    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

function getCountryFromIp($ipAddress)
{
    if (empty($ipAddress)) {
        return 'Unknown';
    }

    if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        return 'Local';
    }

    $isPublicIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($isPublicIp === false) {
        return 'Unknown';
    }

    $ipAddress = rawurlencode($ipAddress);
    $ipInfoToken = getenv('IPINFO_TOKEN');
    $ipInfoUrl = 'https://ipinfo.io/' . $ipAddress . '/json';

    if ($ipInfoToken) {
        $ipInfoUrl .= '?token=' . rawurlencode($ipInfoToken);
    }

    $ipInfo = httpGetJson($ipInfoUrl, 2);
    if (is_array($ipInfo)) {
        if (!empty($ipInfo['country'])) {
            return $ipInfo['country'];
        }
        if (!empty($ipInfo['country_name'])) {
            return $ipInfo['country_name'];
        }
    }

    $fallbackInfo = httpGetJson('https://ipapi.co/' . $ipAddress . '/json/', 2);
    if (is_array($fallbackInfo)) {
        if (!empty($fallbackInfo['country_name'])) {
            return $fallbackInfo['country_name'];
        }
        if (!empty($fallbackInfo['country'])) {
            return $fallbackInfo['country'];
        }
    }

    return 'Unknown';
}

function logVisit($urlId, $ipAddress = null, $userAgent = null, $country = null)
{
    $pdo = getDatabaseConnection();

    if ($country === null) {
        $country = getCountryFromIp($ipAddress);
    }

    $stmt = $pdo->prepare("
        INSERT INTO visits (url_id, ip_address, country, user_agent)
        VALUES (:url_id, :ip_address, :country, :user_agent)
    ");

    $stmt->execute([
        'url_id' => $urlId,
        'ip_address' => $ipAddress,
        'country' => $country,
        'user_agent' => $userAgent
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'url_id' => (int)$urlId,
        'ip_address' => $ipAddress,
        'country' => $country,
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
        SELECT id, url_id, ip_address, country, user_agent, visited_at
        FROM visits
        WHERE url_id = :url_id
        ORDER BY visited_at DESC
    ");

    $stmt->execute(['url_id' => $urlId]);
    return $stmt->fetchAll();
}

function getStatsByUrlId($urlId)
{
    $url = assertUrlExists($urlId);

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
        'createdAt' => $url['createdAt'],
        'totalVisits' => count($visits),
        'visits' => $visits,
        'dailyBreakdown' => $dailyBreakdown
    ];
}

function getCountriesByUrlId($urlId)
{
    assertUrlExists($urlId);

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(TRIM(country), ''), 'Unknown') AS country, COUNT(*) AS count
        FROM visits
        WHERE url_id = :url_id
        GROUP BY COALESCE(NULLIF(TRIM(country), ''), 'Unknown')
        ORDER BY count DESC
    ");

    $stmt->execute(['url_id' => $urlId]);
    $rows = $stmt->fetchAll();

    $countries = [];
    foreach ($rows as $row) {
        $countries[] = [
            'country' => $row['country'],
            'count' => (int)$row['count']
        ];
    }

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