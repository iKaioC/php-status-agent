<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use StatusAgent\Host\HostMetrics;

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path !== '/status') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not Found'], JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'host' => [
        'hostname' => HostMetrics::hostname(),
        'uptime_seconds' => HostMetrics::uptimeSeconds(),
        'loadavg' => HostMetrics::loadAvg(),
        'cpu' => HostMetrics::cpu(),
        'memory' => HostMetrics::memory(),
        'swap' => HostMetrics::swap(),
        'disk' => HostMetrics::disks(),
    ],
    'timestamp' => time(),
], JSON_UNESCAPED_SLASHES);