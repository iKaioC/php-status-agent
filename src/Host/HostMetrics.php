<?php

declare(strict_types=1);

namespace StatusAgent\Host;

final class HostMetrics
{
    public static function hostname(): string
    {
        return \gethostname() ?: 'unknown';
    }

    public static function uptimeSeconds(): ?int
    {
        $content = @\file_get_contents('/proc/uptime');
        if ($content === false) return null;

        $parts = \preg_split('/\s+/', \trim($content));
        if (!$parts || !isset($parts[0])) return null;

        return (int) \floor((float) $parts[0]);
    }

    public static function loadAvg(): ?array
    {
        $content = @\file_get_contents('/proc/loadavg');
        if ($content === false) return null;

        $parts = \preg_split('/\s+/', \trim($content));
        if (!$parts || \count($parts) < 3) return null;

        return [(float) $parts[0], (float) $parts[1], (float) $parts[2]];
    }

    public static function memory(): ?array
    {
        $content = @\file_get_contents('/proc/meminfo');
        if ($content === false) return null;

        $memTotalKb = self::meminfoValueKb($content, 'MemTotal');
        $memAvailableKb = self::meminfoValueKb($content, 'MemAvailable');

        if ($memTotalKb === null || $memAvailableKb === null) return null;

        $totalBytes = $memTotalKb * 1024;
        $freeBytes = $memAvailableKb * 1024;
        $usedBytes = \max(0, $totalBytes - $freeBytes);

        $usedPercent = self::percent($usedBytes, $totalBytes);

        $totalMb = (int) \floor($totalBytes / 1024 / 1024);
        $freeMb = (int) \floor($freeBytes / 1024 / 1024);
        $usedMb = \max(0, $totalMb - $freeMb);

        return [
            'total_bytes' => (int) $totalBytes,
            'used_bytes' => (int) $usedBytes,
            'free_bytes' => (int) $freeBytes,
            'used_percent' => $usedPercent,

            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'free_mb' => $freeMb,
        ];
    }

    public static function cpu(): ?array
    {
        $content = @\file_get_contents('/proc/cpuinfo');
        if ($content === false) return null;

        \preg_match_all('/^processor\s*:\s*\d+\s*$/m', $content, $m);
        $cores = isset($m[0]) ? \count($m[0]) : 0;

        $model = null;
        if (\preg_match('/^model name\s*:\s*(.+)$/m', $content, $mm)) {
            $model = \trim($mm[1]);
        }

        if ($cores <= 0 && $model === null) return null;

        return [
            'cores' => \max(1, $cores),
            'model' => $model,
        ];
    }

    public static function swap(): ?array
    {
        $content = @\file_get_contents('/proc/meminfo');
        if ($content === false) return null;

        $swapTotalKb = self::meminfoValueKb($content, 'SwapTotal');
        $swapFreeKb = self::meminfoValueKb($content, 'SwapFree');

        if ($swapTotalKb === null || $swapFreeKb === null) return null;

        $totalBytes = $swapTotalKb * 1024;
        $freeBytes = $swapFreeKb * 1024;
        $usedBytes = \max(0, $totalBytes - $freeBytes);

        $usedPercent = self::percent($usedBytes, $totalBytes);

        $totalMb = (int) \floor($totalBytes / 1024 / 1024);
        $freeMb = (int) \floor($freeBytes / 1024 / 1024);
        $usedMb = \max(0, $totalMb - $freeMb);

        return [
            'total_bytes' => (int) $totalBytes,
            'used_bytes' => (int) $usedBytes,
            'free_bytes' => (int) $freeBytes,
            'used_percent' => $usedPercent,

            'total_mb' => $totalMb,
            'used_mb' => $usedMb,
            'free_mb' => $freeMb,
        ];
    }

    public static function disks(): ?array
    {
        $disks = self::disksFromStatvfs();

        $hasRoot = false;
        if (\is_array($disks)) {
            foreach ($disks as $d) {
                if (($d['mount'] ?? null) === '/') {
                    $hasRoot = true;
                    break;
                }
            }
        }

        if (!$disks || !$hasRoot) {
            $fallback = self::disksFromDf();
            if ($fallback) return $fallback;
        }

        return $disks ?: null;
    }

    private static function disksFromStatvfs(): ?array
    {
        $mounts = self::readMounts();
        if ($mounts === null) return null;

        $disks = [];

        foreach ($mounts as $mount) {
            if ($mount === '') continue;

            if (\str_starts_with($mount, '/snap/')) continue;

            if ($mount !== '/' && !\preg_match('~^/mnt/[a-zA-Z]$~', $mount)) {
                continue;
            }

            $info = self::statFs($mount);
            if ($info === null) continue;

            $disks[] = $info;
        }

        if (!$disks) return null;

        \usort($disks, static function (array $a, array $b): int {
            return \strcmp((string) ($a['mount'] ?? ''), (string) ($b['mount'] ?? ''));
        });

        return $disks;
    }

    private static function statFs(string $mount): ?array
    {
        if (!\function_exists('statvfs')) return null;

        $s = @\statvfs($mount);
        if (!\is_array($s)) return null;

        $frsize = (int) ($s['frsize'] ?? 0);
        $blocks = (int) ($s['blocks'] ?? 0);
        $bavail = (int) ($s['bavail'] ?? 0);

        if ($frsize <= 0 || $blocks <= 0) return null;

        $total = (int) ($blocks * $frsize);
        $free = (int) ($bavail * $frsize);
        $used = \max(0, $total - $free);

        $usedPercent = self::percent($used, $total);

        return [
            'mount' => $mount,

            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'used_percent' => $usedPercent,

            'total_gb' => (int) \floor($total / 1024 / 1024 / 1024),
            'used_gb' => (int) \floor($used / 1024 / 1024 / 1024),
            'free_gb' => (int) \floor($free / 1024 / 1024 / 1024),
        ];
    }

    private static function readMounts(): ?array
    {
        $content = @\file_get_contents('/proc/mounts');
        if ($content === false) return null;

        $lines = \preg_split("/\r\n|\n|\r/", \trim($content));
        if (!$lines) return null;

        $mounts = [];

        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '') continue;

            $parts = \preg_split('/\s+/', $line);
            if (!$parts || \count($parts) < 2) continue;

            $mount = (string) $parts[1];
            $mount = \str_replace('\\040', ' ', $mount);

            $mounts[$mount] = true;
        }

        return \array_keys($mounts);
    }

    private static function disksFromDf(): ?array
    {
        if (!\function_exists('shell_exec')) return null;

        $output = @\shell_exec('df -P -B1 2>/dev/null');
        if (!$output) return null;

        $lines = \preg_split("/\r\n|\n|\r/", \trim($output));
        if (!$lines || \count($lines) < 2) return null;

        \array_shift($lines);

        $disks = [];

        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '') continue;

            $parts = \preg_split('/\s+/', $line);
            if (!$parts || \count($parts) < 6) continue;

            $size = (int) $parts[1];
            $used = (int) $parts[2];
            $avail = (int) $parts[3];
            $mount = (string) $parts[5];

            if (\str_starts_with($mount, '/snap/')) continue;

            if ($mount !== '/' && !\preg_match('~^/mnt/[a-zA-Z]$~', $mount)) {
                continue;
            }

            $total = $size;
            $free = $avail;
            $usedPercent = self::percent($used, $total);

            $disks[] = [
                'mount' => $mount,

                'total_bytes' => $total,
                'used_bytes' => $used,
                'free_bytes' => $free,
                'used_percent' => $usedPercent,

                'total_gb' => (int) \floor($total / 1024 / 1024 / 1024),
                'used_gb' => (int) \floor($used / 1024 / 1024 / 1024),
                'free_gb' => (int) \floor($free / 1024 / 1024 / 1024),
            ];
        }

        if (!$disks) return null;

        \usort($disks, static function (array $a, array $b): int {
            return \strcmp((string) ($a['mount'] ?? ''), (string) ($b['mount'] ?? ''));
        });

        return $disks;
    }

    private static function meminfoValueKb(string $meminfo, string $key): ?int
    {
        if (!\preg_match('/^' . \preg_quote($key, '/') . ':\s+(\d+)\s+kB$/m', $meminfo, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private static function percent(int $used, int $total): int
    {
        if ($total <= 0) return 0;
        $p = (int) \round(($used / $total) * 100);
        if ($p < 0) return 0;
        if ($p > 100) return 100;
        return $p;
    }
}