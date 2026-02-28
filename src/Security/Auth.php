<?php

declare(strict_types=1);

namespace StatusAgent\Security;

final class Auth
{
    public static function requireBearerToken(?string $expectedToken, bool $allowOpen = false): void
    {
        if (!$expectedToken) {
            if ($allowOpen) {
                return;
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Misconfigured: STATUS_AGENT_TOKEN is required'], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $header = self::getAuthorizationHeader();

        $provided = null;
        if ($header && preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $m)) {
            $provided = trim($m[1]);
        }

        if (!$provided || !hash_equals($expectedToken, $provided)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    private static function getAuthorizationHeader(): ?string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return (string) $_SERVER['HTTP_AUTHORIZATION'];
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

        return null;
    }
}