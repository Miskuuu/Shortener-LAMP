<?php

function getAppConfigPath()
{
    return __DIR__ . '/app_config.json';
}

function readAppConfig()
{
    $path = getAppConfigPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeAppConfig($config)
{
    $path = getAppConfigPath();
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return false;
    }

    return @file_put_contents($path, $encoded) !== false;
}

function getDatabaseConnection()
{
    $host = 'localhost';
    $dbname = 'url_shortener_db';
    $username = 'shortener_user';
    $password = 'Shortener2026!';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Database connection failed'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function getBaseUrl()
{
    $appConfig = readAppConfig();
    if (!empty($appConfig['baseUrl'])) {
        return rtrim($appConfig['baseUrl'], '/');
    }

    $configuredBaseUrl = getenv('BASE_URL');
    if ($configuredBaseUrl) {
        return rtrim($configuredBaseUrl, '/');
    }

    $httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $httpsEnabled ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    $basePath = '';
    if ($scriptName !== '') {
        $backendPos = strpos($scriptName, '/backend/');
        if ($backendPos !== false) {
            $basePath = substr($scriptName, 0, $backendPos);
        } else {
            $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($basePath === '/' || $basePath === '.') {
                $basePath = '';
            }
        }
    }

    return rtrim($scheme . '://' . $host . $basePath, '/');
}

function getRedirectPath()
{
    $appConfig = readAppConfig();
    if (!empty($appConfig['redirectPath'])) {
        return '/' . ltrim($appConfig['redirectPath'], '/');
    }

    $configuredRedirectPath = getenv('REDIRECT_PATH');
    if ($configuredRedirectPath) {
        return '/' . ltrim($configuredRedirectPath, '/');
    }

    return '/backend/redirect/index.php';
}