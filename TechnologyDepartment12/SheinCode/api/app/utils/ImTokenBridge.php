<?php

namespace app\utils;

use app\model\User;

class ImTokenBridge
{
    public static function issueForUser(User $user): ?array
    {
        if (self::envValue('IM_AUTH_ENABLED', 'true') === 'false') {
            return null;
        }

        if (self::envValue('IM_AUTH_MODE', 'local') !== 'remote') {
            return self::localTokenData($user);
        }

        $baseUrl = rtrim(self::envValue('IM_AUTH_API_BASE_URL', 'https://api.sukim.top'), '/');
        if ($baseUrl === '') {
            return null;
        }

        $profile = self::remoteProfile($user);
        $login = self::postJson($baseUrl . '/api/v1/user/login', [
            'username' => $profile['username'],
            'password' => $profile['password'],
            'lang' => 'en',
            'version' => self::envValue('IM_AUTH_VERSION', 'newpurple'),
        ]);

        $tokenData = self::tokenData($login);
        if ($tokenData) {
            return $tokenData;
        }

        self::postJson($baseUrl . '/api/v1/user/register', [
            'username' => $profile['username'],
            'realname' => $profile['realname'],
            'account_type' => '1',
            'password' => $profile['password'],
            'password_confirm' => $profile['password'],
            'phone' => $profile['phone'],
            'qh' => '+91',
            'email' => '',
            'verify_code' => '',
            'pix' => '',
            'platform' => '',
            'click_id' => '',
            'lang' => 'en',
            'fbp' => '',
            'fbc' => '',
            'inv_code' => self::envValue('IM_AUTH_INV_CODE', 'USA2024999'),
            'version' => self::envValue('IM_AUTH_VERSION', 'newpurple'),
        ]);

        $login = self::postJson($baseUrl . '/api/v1/user/login', [
            'username' => $profile['username'],
            'password' => $profile['password'],
            'lang' => 'en',
            'version' => self::envValue('IM_AUTH_VERSION', 'newpurple'),
        ]);

        return self::tokenData($login);
    }

    private static function localTokenData(User $user): array
    {
        $userId = (string) $user->id;
        $expiresIn = (int) self::envValue('IM_AUTH_EXPIRE', '14688000');
        $now = time();
        $nickname = (string) ($user->nickname ?: $user->phone ?: ('User ' . $userId));

        return [
            'token' => JwtTokenUtil::generateToken([
                'id' => (int) $user->id,
                'uid' => $userId,
                'userId' => $userId,
                'role' => (string) ($user->account_role ?: 'member'),
                'create_time' => $now,
                'nickname' => $nickname,
                'expires_in' => $expiresIn,
                'type' => 'login',
                'token_type' => 'member',
            ], self::envValue('JWT_SECRET', 'default_secret_key_change_this')),
            'user_id' => (int) $user->id,
            'expires_time' => $expiresIn,
        ];
    }

    private static function remoteProfile(User $user): array
    {
        $seed = (string) $user->id . ':' . (string) $user->phone;
        $hash = hash('sha256', self::envValue('IM_AUTH_SALT', 'fraudulent-order-processing') . ':' . $seed);
        $numeric = sprintf('%09u', crc32('im:' . $seed) % 1000000000);

        return [
            'username' => 'fop' . substr($hash, 0, 10),
            'realname' => 'FOP User ' . (int) $user->id,
            'phone' => '9' . $numeric,
            'password' => 'Fop' . substr($hash, 10, 12),
        ];
    }

    private static function tokenData(?array $response): ?array
    {
        $data = $response['data'] ?? null;
        if (!is_array($data) || empty($data['token'])) {
            return null;
        }

        return [
            'token' => (string) $data['token'],
            'user_id' => (int) ($data['id'] ?? 0),
            'expires_time' => (int) ($data['expires_time'] ?? 0),
        ];
    }

    private static function postJson(string $url, array $payload): ?array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return null;
        }

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $verifySsl = self::envValue('IM_AUTH_SSL_VERIFY', 'false') !== 'false';
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_SSL_VERIFYPEER => $verifySsl,
                    CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
            } else {
                $raw = file_get_contents($url, false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'timeout' => 6,
                        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                        'content' => $body,
                    ],
                ]));
            }

            if (!is_string($raw) || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function envValue(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

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
