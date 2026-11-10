<?php

namespace app\utils;

use Aws\S3\S3Client;

class S3Uploader
{
    private static ?S3Client $client = null;
    private static ?string $bucket = null;
    private static ?string $publicBaseUrl = null;
    private static ?string $lastError = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function getClient(): S3Client
    {
        if (self::$client === null) {
            if (!class_exists('\Aws\S3\S3Client')) {
                throw new \RuntimeException('AWS SDK not installed. Run: composer require aws/aws-sdk-php');
            }
            self::$client = new S3Client([
                'version' => 'latest',
                'region' => self::getConfig('region', 'us-east-1'),
                'credentials' => [
                    'key' => self::getConfig('access-key', ''),
                    'secret' => self::getConfig('secret-key', ''),
                ],
            ]);
        }
        return self::$client;
    }

    public static function getBucket(): string
    {
        if (self::$bucket === null) {
            self::$bucket = self::getConfig('bucket-name', '');
        }
        return self::$bucket;
    }

    public static function getPublicBaseUrl(): string
    {
        if (self::$publicBaseUrl === null) {
            self::$publicBaseUrl = rtrim(self::getConfig('public-base-url', ''), '/');
        }
        return self::$publicBaseUrl;
    }

    private static function getConfig(string $key, string $default = ''): string
    {
        static $config = null;
        if ($config === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
            $config = [];
            if (is_file($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    $pos = strpos($line, '=');
                    if ($pos === false) {
                        continue;
                    }
                    $name = trim(substr($line, 0, $pos));
                    $value = trim(substr($line, $pos + 1));
                    if (
                        strlen($value) >= 2
                        && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
                    ) {
                        $value = substr($value, 1, -1);
                    }
                    $config[$name] = $value;
                }
            }
        }
        $keyMap = [
            'access-key' => 'S3_ACCESS_KEY',
            'secret-key' => 'S3_SECRET_KEY',
            'region' => 'S3_REGION',
            'bucket-name' => 'S3_BUCKET',
            'public-base-url' => 'S3_PUBLIC_URL',
            'acl' => 'S3_ACL',
        ];
        $envKey = $keyMap[$key] ?? "S3_{$key}";
        $envValue = getenv($envKey);
        if ($envValue !== false && $envValue !== '') {
            return (string) $envValue;
        }
        return $config[$envKey] ?? $default;
    }

    private static function withOptionalAcl(array $params): array
    {
        $acl = trim(self::getConfig('acl', ''));
        if ($acl !== '') {
            $params['ACL'] = $acl;
        }
        return $params;
    }

    public static function upload(string $filePath, string $key, string $contentType = 'image/jpeg'): ?string
    {
        self::$lastError = null;
        try {
            if (!class_exists('\Aws\S3\S3Client')) {
                self::$lastError = 'AWS SDK not installed';
                error_log('S3 upload failed: AWS SDK not installed');
                return null;
            }
            self::getClient()->putObject(self::withOptionalAcl([
                'Bucket' => self::getBucket(),
                'Key' => $key,
                'SourceFile' => $filePath,
                'ContentType' => $contentType,
            ]));
            return self::getPublicBaseUrl() . '/' . $key;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('S3 upload failed: ' . $e->getMessage() . ' | Bucket: ' . self::getBucket() . ' | Key: ' . $key);
            return null;
        }
    }

    public static function uploadContent(string $content, string $key, string $contentType = 'image/jpeg'): ?string
    {
        self::$lastError = null;
        try {
            self::getClient()->putObject(self::withOptionalAcl([
                'Bucket' => self::getBucket(),
                'Key' => $key,
                'Body' => $content,
                'ContentType' => $contentType,
            ]));
            return self::getPublicBaseUrl() . '/' . $key;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('S3 upload failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function delete(string $key): bool
    {
        try {
            self::getClient()->deleteObject([
                'Bucket' => self::getBucket(),
                'Key' => $key,
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('S3 delete failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function generateKey(string $prefix, string $extension): string
    {
        $date = date('Y/m/d');
        $filename = bin2hex(random_bytes(12));
        return "{$prefix}/{$date}/{$filename}.{$extension}";
    }
}
