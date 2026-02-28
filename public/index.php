<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use StatusAgent\Host\HostMetrics;
use StatusAgent\Security\Auth;

header('X-Content-Type-Options: nosniff');

$dotenvPath = dirname(__DIR__);
$dotenvFile = $dotenvPath . '/.env';

if (is_file($dotenvFile)) {
    Dotenv::createImmutable($dotenvPath)->safeLoad();
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

const STATUS_AGENT_SCHEMA_VERSION = 1;

function envStr(string $key): ?string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($v === false || $v === null) return null;
    $v = trim((string) $v);
    return $v !== '' ? $v : null;
}

function envBool(string $key, bool $default = false): bool
{
    $v = envStr($key);
    if ($v === null) return $default;

    $v = strtolower($v);
    if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') return true;
    if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off') return false;

    return $default;
}

function jsonOut(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function pct(float $used, float $total): int
{
    if ($total <= 0) return 0;
    $p = (int) round(($used / $total) * 100);
    if ($p < 0) return 0;
    if ($p > 100) return 100;
    return $p;
}

function fmtSeconds(int $seconds): string
{
    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;

    if ($d > 0) return $d . 'd ' . $h . 'h ' . $m . 'm';
    if ($h > 0) return $h . 'h ' . $m . 'm';
    if ($m > 0) return $m . 'm ' . $s . 's';
    return $s . 's';
}

if ($path === '/public') {
    $uptime = HostMetrics::uptimeSeconds();
    $load = HostMetrics::loadAvg();
    $mem = HostMetrics::memory();
    $disks = HostMetrics::disks();

    $memPct = null;
    $memUsedBytes = null;
    $memTotalBytes = null;

    if (is_array($mem) && isset($mem['used_percent'])) {
        $memPct = is_int($mem['used_percent']) ? $mem['used_percent'] : null;
        $memUsedBytes = isset($mem['used_bytes']) && is_int($mem['used_bytes']) ? $mem['used_bytes'] : null;
        $memTotalBytes = isset($mem['total_bytes']) && is_int($mem['total_bytes']) ? $mem['total_bytes'] : null;
    }

    $rootPct = null;
    $rootUsedBytes = null;
    $rootTotalBytes = null;

    if (is_array($disks)) {
        foreach ($disks as $d) {
            if (($d['mount'] ?? null) === '/') {
                if (isset($d['used_percent']) && is_int($d['used_percent'])) $rootPct = $d['used_percent'];
                if (isset($d['used_bytes']) && is_int($d['used_bytes'])) $rootUsedBytes = $d['used_bytes'];
                if (isset($d['total_bytes']) && is_int($d['total_bytes'])) $rootTotalBytes = $d['total_bytes'];
                break;
            }
        }
    }

    jsonOut(200, [
        'ok' => true,
        'schema_version' => STATUS_AGENT_SCHEMA_VERSION,
        'public' => [
            'uptime_seconds' => $uptime,
            'uptime_human' => is_int($uptime) ? fmtSeconds($uptime) : null,
            'loadavg' => $load,

            'memory_percent' => $memPct,
            'memory' => [
                'used_percent' => $memPct,
                'used_bytes' => $memUsedBytes,
                'total_bytes' => $memTotalBytes,
            ],

            'disk_root_percent' => $rootPct,
            'disk_root' => [
                'used_percent' => $rootPct,
                'used_bytes' => $rootUsedBytes,
                'total_bytes' => $rootTotalBytes,
            ],
        ],
        'timestamp' => time(),
    ]);
}

if ($path === '/status') {
    $statusOpen = envBool('STATUS_AGENT_STATUS_OPEN', false);
    $expectedToken = envStr('STATUS_AGENT_TOKEN');

    Auth::requireBearerToken($expectedToken, $statusOpen);

    jsonOut(200, [
        'ok' => true,
        'schema_version' => STATUS_AGENT_SCHEMA_VERSION,
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
    ]);
}

if ($path !== '/' && $path !== '') {
    jsonOut(404, ['ok' => false, 'error' => 'Not Found']);
}

$refreshDefault = (int) (envStr('STATUS_AGENT_PUBLIC_REFRESH') ?? '10');
if ($refreshDefault < 3) $refreshDefault = 3;
if ($refreshDefault > 120) $refreshDefault = 120;

$refresh = isset($_GET['refresh']) ? (int) $_GET['refresh'] : $refreshDefault;
if ($refresh < 3) $refresh = 3;
if ($refresh > 120) $refresh = 120;

header('Content-Type: text/html; charset=utf-8');

$view = __DIR__ . '/views/dashboard.php';
if (!is_file($view)) {
    http_response_code(500);
    echo 'Missing view: public/views/dashboard.php';
    exit;
}

require $view;