<?php

declare(strict_types=1);

namespace StatusAgent\Host;

final class HostMetrics
{
    public static function hostname(): string
    {
        return gethostname() ?: 'unknown';
    }

    public static function uptimeSeconds(): ?int
    {
        $content = @file_get_contents('/proc/uptime');
        if ($content === false) return null;

        $parts = preg_split('/\s+/', trim($content));
        if (!$parts || !isset($parts[0])) return null;

        return (int) floor((float) $parts[0]);
    }

    public static function loadAvg(): ?array
    {
        $content = @file_get_contents('/proc/loadavg');
        if ($content === false) return null;

        $parts = preg_split('/\s+/', trim($content));
        if (!$parts || count($parts) < 3) return null;

        return [(float) $parts[0], (float) $parts[1], (float) $parts[2]];
    }

    public static function memory(): ?array
    {
        $content = @file_get_contents('/proc/meminfo');
        if ($content === false) return null;

        $memTotal = self::meminfoValueKb($content, 'MemTotal');
        $memAvailable = self::meminfoValueKb($content, 'MemAvailable');

        if ($memTotal === null || $memAvailable === null) return null;

        $totalMb = (int) floor($memTotal / 1024);
        $freeMb  = (int) floor($memAvailable / 1024);
        $usedMb  = max(0, $totalMb - $freeMb);

        return [
            'total_mb' => $totalMb,
            'used_mb'  => $usedMb,
            'free_mb'  => $freeMb,
        ];
    }

    public static function cpu(): ?array
    {
        $content = @file_get_contents('/proc/cpuinfo');
        if ($content === false) return null;

        preg_match_all('/^processor\s*:\s*\d+\s*$/m', $content, $m);
        $cores = isset($m[0]) ? count($m[0]) : 0;

        $model = null;
        if (preg_match('/^model name\s*:\s*(.+)$/m', $content, $mm)) {
            $model = trim($mm[1]);
        }

        if ($cores <= 0 && $model === null) return null;

        return [
            'cores' => max(1, $cores),
            'model' => $model,
        ];
    }

    public static function swap(): ?array
    {
        $content = @file_get_contents('/proc/meminfo');
        if ($content === false) return null;

        $swapTotal = self::meminfoValueKb($content, 'SwapTotal');
        $swapFree  = self::meminfoValueKb($content, 'SwapFree');

        if ($swapTotal === null || $swapFree === null) return null;

        $totalMb = (int) floor($swapTotal / 1024);
        $freeMb  = (int) floor($swapFree / 1024);
        $usedMb  = max(0, $totalMb - $freeMb);

        return [
            'total_mb' => $totalMb,
            'used_mb'  => $usedMb,
            'free_mb'  => $freeMb,
        ];
    }

    public static function disks(): ?array
    {
        $output = @shell_exec('df -P -B1 2>/dev/null');
        if (!$output) return null;

        $lines = preg_split("/\r\n|\n|\r/", trim($output));
        if (!$lines || count($lines) < 2) return null;

        array_shift($lines);

        $disks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = preg_split('/\s+/', $line);
            if (!$parts || count($parts) < 6) continue;

            $size  = (int) $parts[1];
            $used  = (int) $parts[2];
            $avail = (int) $parts[3];
            $mount = (string) $parts[5];

            if (str_starts_with($mount, '/snap/')) continue;

            if ($mount !== '/' && !preg_match('~^/mnt/[a-zA-Z]$~', $mount)) {
                continue;
            }

            $disks[] = [
                'mount'    => $mount,
                'total_gb' => (int) floor($size / 1024 / 1024 / 1024),
                'used_gb'  => (int) floor($used / 1024 / 1024 / 1024),
                'free_gb'  => (int) floor($avail / 1024 / 1024 / 1024),
            ];
        }

        return $disks;
    }

    private static function meminfoValueKb(string $meminfo, string $key): ?int
    {
        if (!preg_match('/^' . preg_quote($key, '/') . ':\s+(\d+)\s+kB$/m', $meminfo, $m)) {
            return null;
        }

        return (int) $m[1];
    }
}