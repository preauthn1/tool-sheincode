<?php
$env = static function (string $key, string $default = ''): string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = root_path() . '.env';
        if (is_file($path) && is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                if (str_starts_with($line, 'export ')) {
                    $line = trim(substr($line, 7));
                }

                $position = strpos($line, '=');
                if ($position === false) {
                    continue;
                }

                $name = trim(substr($line, 0, $position));
                $value = trim(substr($line, $position + 1));
                if ($name === '') {
                    continue;
                }

                if (
                    strlen($value) >= 2
                    && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                $values[$name] = $value;
            }
        }
    }

    return array_key_exists($key, $values) ? $values[$key] : $default;
};

$dbType = $env('DB_TYPE', 'mysql');

return [
    'default' => $dbType,
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => $env('DB_HOST', '127.0.0.1'),
            'database' => $env('DB_NAME', 'costco_work'),
            'username' => $env('DB_USER', 'root'),
            'password' => $env('DB_PASS', ''),
            'hostport' => $env('DB_PORT', '3306'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'tp_',
            'trigger_sql' => false,   // 关闭 SQL 监听/日志
        ],
        'sqlite' => [
            'type' => 'sqlite',
            'database' => $env('DB_DATABASE', root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'local.sqlite'),
            'prefix' => '',
            'charset' => 'utf8',
            'fields_cache' => false,
        ],
    ],
];
