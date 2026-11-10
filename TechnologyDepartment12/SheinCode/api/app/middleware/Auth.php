<?php

namespace app\middleware;

use app\utils\JwtTokenUtil;

class Auth
{
    public function handle($request, \Closure $next)
    {
        $token = (string) $request->header('Authorization');
        $token = str_replace('Bearer ', '', $token);

        if ($token === '') {
            return json(['code' => 401, 'msg' => 'Not logged in']);
        }

        $payload = JwtTokenUtil::parse($token, $this->jwtSecret());
        $userId = $payload['uid'] ?? $payload['user_id'] ?? null;
        $expired = $this->isExpired($payload);

        if (!$payload || !$userId || $expired) {
            return json(['code' => 401, 'msg' => 'Login expired']);
        }

        $request->userId = (int) $userId;
        return $next($request);
    }

    private function isExpired(array $payload): bool
    {
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return true;
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 0);
        $createTime = (int) ($payload['create_time'] ?? 0);
        return $expiresIn > 0 && $createTime > 0 && $createTime + $expiresIn < time();
    }

    private function jwtSecret(): string
    {
        return (string) (getenv('JWT_SECRET') ?: $this->envValue('JWT_SECRET', 'default_secret_key_change_this'));
    }

    private function envValue(string $key, string $default = ''): string
    {
        $path = root_path() . '.env';
        if (!is_file($path) || !is_readable($path)) {
            return $default;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }

            $name = trim(substr($line, 0, $position));
            if ($name !== $key) {
                continue;
            }

            $value = trim(substr($line, $position + 1));
            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            return $value;
        }

        return $default;
    }
}
