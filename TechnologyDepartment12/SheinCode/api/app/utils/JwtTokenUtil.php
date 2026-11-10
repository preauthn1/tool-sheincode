<?php

namespace app\utils;

class JwtTokenUtil
{
    public static function generateToken(array $payload, string $secret): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerBase64 = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadBase64 = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $data = $headerBase64 . '.' . $payloadBase64;

        return $data . '.' . self::hmacSha256Base64Url($data, $secret);
    }

    public static function parse(string $token, ?string $secret = null): array
    {
        $token = trim(str_replace('Bearer ', '', $token));
        if ($token === '') {
            return [];
        }

        if (substr_count($token, '.') === 2) {
            return self::parseJwt($token, $secret);
        }

        $payload = json_decode(base64_decode($token), true);
        return is_array($payload) ? $payload : [];
    }

    public static function getUserId(string $token): ?string
    {
        $payload = self::parse($token);
        $uid = $payload['uid'] ?? $payload['user_id'] ?? null;
        return $uid !== null ? (string) $uid : null;
    }

    private static function parseJwt(string $token, ?string $secret = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        [$headerBase64, $payloadBase64, $signature] = $parts;
        if ($secret !== null && $secret !== '') {
            $expected = self::hmacSha256Base64Url($headerBase64 . '.' . $payloadBase64, $secret);
            if (!hash_equals($expected, $signature)) {
                return [];
            }
        }

        $payload = json_decode(self::base64UrlDecode($payloadBase64), true);
        return is_array($payload) ? $payload : [];
    }

    private static function hmacSha256Base64Url(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
