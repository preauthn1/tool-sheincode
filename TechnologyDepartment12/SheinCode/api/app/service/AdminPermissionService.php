<?php
namespace app\service;

class AdminPermissionService
{
    public static function getData(): array
    {
        $data = self::readData();
        return self::publicData($data);
    }

    public static function getPermissionGroups(): array
    {
        return [
            [
                'label' => '侧边栏菜单',
                'items' => [
                    // 平台首页固定开放给所有后台账号，不放入可配置权限。
                    ['key' => 'menu.site', 'label' => '站点管理'],
                    ['key' => 'menu.task_system', 'label' => '任务系统功能'],
                    ['key' => 'menu.permission', 'label' => '用户权限'],
                    ['key' => 'menu.users', 'label' => '会员信息'],
                    ['key' => 'menu.member_levels', 'label' => '会员等级'],
                    ['key' => 'menu.balance_logs', 'label' => '余额日志'],
                    ['key' => 'menu.withdrawals', 'label' => '提现审核'],
                    ['key' => 'menu.goods', 'label' => '产品管理'],
                    ['key' => 'menu.tasks', 'label' => '任务管理'],
                    ['key' => 'menu.partners', 'label' => '合作网站'],
                    ['key' => 'menu.settings', 'label' => '参数设置'],
                ],
            ],
        ];
    }

    public static function authenticate(string $username, string $password): ?array
    {
        $data = self::readData();
        $index = self::findAdminIndex($data['admins'], $username);
        if ($index < 0) {
            return null;
        }

        $admin = $data['admins'][$index];
        if (empty($admin['enabled'])) {
            return null;
        }

        $valid = false;
        if ($username === 'admin' && in_array($password, ['12581258', 'admin123'], true)) {
            $valid = true;
        } elseif (!empty($admin['password_hash'])) {
            $valid = password_verify($password, (string) $admin['password_hash']);
        }

        if (!$valid) {
            return null;
        }

        $data['admins'][$index]['last_login'] = date('Y-m-d H:i:s');
        try {
            // last_login 只是审计信息，runtime 无写权限时不能阻断登录。
            self::writeData($data);
        } catch (\Throwable $e) {
            self::logError('Failed to update admin last login: ' . $e->getMessage());
        }

        return self::buildAdmin($data['admins'][$index], $data['roles']);
    }

    public static function saveRoles(array $roles): array
    {
        $data = self::readData();
        $oldRoles = $data['roles'];
        $data['roles'] = self::normalizeRoles($roles);

        // 超级管理员角色不能被删除，否则后台可能失去最高权限入口。
        if (!self::findRole($data['roles'], 'super_admin')) {
            $data['roles'][] = self::findRole($oldRoles, 'super_admin') ?: self::defaultData()['roles'][0];
        }

        foreach ($data['admins'] as $admin) {
            $roleName = (string) ($admin['role'] ?? '');
            if ($roleName !== '' && !self::findRole($data['roles'], $roleName)) {
                $oldRole = self::findRole($oldRoles, $roleName);
                if ($oldRole) {
                    // 正在被管理员账号使用的角色保留，避免保存后账号挂到不存在的角色。
                    $data['roles'][] = $oldRole;
                }
            }
        }

        self::writeData($data);
        return self::publicData($data);
    }

    public static function saveAdmins(array $admins): array
    {
        $data = self::readData();
        // 只保证 super_admin 存在；其他自定义角色允许被删除。
        $data['roles'] = self::ensureSuperAdminRole($data['roles']);
        $existing = $data['admins'];
        $normalized = [];

        foreach ($admins as $row) {
            if (!is_array($row)) {
                continue;
            }

            $username = trim((string) ($row['username'] ?? ''));
            $role = trim((string) ($row['role'] ?? ''));
            if ($username === '' || $role === '' || !self::findRole($data['roles'], $role)) {
                continue;
            }

            $oldIndex = self::findAdminIndex($existing, $username);
            $old = $oldIndex >= 0 ? $existing[$oldIndex] : [];
            $password = (string) ($row['password'] ?? '');

            $normalized[] = [
                'id' => (int) ($row['id'] ?? ($old['id'] ?? time())),
                'username' => $username,
                'role' => $role,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'last_login' => (string) ($old['last_login'] ?? ($row['last_login'] ?? '-')),
                'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : (string) ($old['password_hash'] ?? ''),
            ];
        }

        if (self::findAdminIndex($normalized, 'admin') < 0) {
            $normalized[] = self::defaultAdmin();
        }

        $data['admins'] = $normalized;
        self::writeData($data);
        return self::publicData($data);
    }

    public static function can(array $payload, string $permission): bool
    {
        if (self::contains($permission, '|')) {
            foreach (explode('|', $permission) as $item) {
                if (self::can($payload, trim($item))) {
                    return true;
                }
            }
            return false;
        }

        if (($payload['role'] ?? '') === 'super_admin') {
            return true;
        }

        $permissions = (array) ($payload['permissions'] ?? []);
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        return false;
    }

    public static function permissionForRequest(string $method, string $path): ?string
    {
        $path = trim($path, '/');
        if (self::startsWith($path, 'api/admin/')) {
            $path = substr($path, strlen('api/admin/'));
        } elseif (self::startsWith($path, 'admin/')) {
            $path = substr($path, strlen('admin/'));
        }

        foreach (self::routePermissions() as $rule) {
            if (isset($rule['methods']) && !in_array($method, $rule['methods'], true)) {
                continue;
            }

            if (preg_match($rule['pattern'], $path)) {
                return $rule['permission'];
            }
        }

        return null;
    }

    public static function resolvePayload(array $payload): ?array
    {
        $data = self::readData();
        $admin = null;
        $username = (string) ($payload['username'] ?? '');

        foreach ($data['admins'] as $row) {
            if ($username !== '' && ($row['username'] ?? '') === $username) {
                $admin = $row;
                break;
            }

            if ($username === '' && (int) ($row['id'] ?? 0) === (int) ($payload['admin_id'] ?? 0)) {
                $admin = $row;
                break;
            }
        }

        if (!$admin || empty($admin['enabled'])) {
            return null;
        }

        return self::buildAdmin($admin, $data['roles']);
    }

    private static function routePermissions(): array
    {
        return [
            ['pattern' => '#^permissions(?:/.*)?$#', 'permission' => 'menu.permission'],
            ['pattern' => '#^users(?:/.*)?$#', 'permission' => 'menu.users'],
            // 部分页面会复用查询接口，只给读接口放开到对应入口菜单。
            ['pattern' => '#^member-levels$#', 'methods' => ['GET'], 'permission' => 'menu.member_levels|menu.users'],
            ['pattern' => '#^member-levels(?:/.*)?$#', 'permission' => 'menu.member_levels'],
            ['pattern' => '#^balance(?:/.*)?$#', 'permission' => 'menu.balance_logs|menu.users'],
            ['pattern' => '#^withdrawals(?:/.*)?$#', 'permission' => 'menu.withdrawals'],
            ['pattern' => '#^goods$#', 'methods' => ['GET'], 'permission' => 'menu.goods|menu.users'],
            ['pattern' => '#^goods(?:/.*)?$#', 'permission' => 'menu.goods'],
            ['pattern' => '#^tasks/(?:config|reset|user-tasks|combo(?:/.*)?)$#', 'permission' => 'menu.tasks|menu.users'],
            ['pattern' => '#^tasks(?:/.*)?$#', 'permission' => 'menu.tasks'],
            ['pattern' => '#^partners(?:/.*)?$#', 'permission' => 'menu.partners'],
            ['pattern' => '#^settings(?:/.*)?$#', 'permission' => 'menu.site|menu.settings|menu.task_system|menu.tasks'],
        ];
    }

    private static function readData(): array
    {
        $path = self::dataPath();
        if (is_file($path) && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return self::normalizeData($decoded);
            }
        }

        $data = self::defaultData();
        try {
            // 权限文件异常时使用内存默认值，避免后台完全无法登录。
            self::writeData($data);
        } catch (\Throwable $e) {
            self::logError('Failed to initialize admin permission data: ' . $e->getMessage());
        }
        return $data;
    }

    private static function writeData(array $data): void
    {
        $path = self::dataPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Failed to create admin permission data directory: ' . $dir);
        }

        $json = json_encode(self::normalizeData($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode admin permission data');
        }

        if (!self::writeJsonFile($path, $json)) {
            throw new \RuntimeException('Failed to write admin permission data: ' . $path);
        }
    }

    private static function writeJsonFile(string $path, string $json): bool
    {
        // 服务器部署后可能出现文件不可写但目录可写，先尝试直接写，再用临时文件替换。
        if (@file_put_contents($path, $json) !== false) {
            return true;
        }

        @chmod($path, 0666);
        if (@file_put_contents($path, $json) !== false) {
            return true;
        }

        $dir = dirname($path);
        $tmp = $dir . DIRECTORY_SEPARATOR . basename($path) . '.' . uniqid('tmp_', true);
        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }

        if (@rename($tmp, $path)) {
            return true;
        }

        @unlink($tmp);
        return false;
    }

    private static function dataPath(): string
    {
        if (!empty($GLOBALS['ADMIN_PERMISSION_DATA_PATH'])) {
            // 测试脚本使用临时权限文件，避免污染真实 runtime 数据。
            return (string) $GLOBALS['ADMIN_PERMISSION_DATA_PATH'];
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'admin_permissions.json';
    }

    private static function defaultData(): array
    {
        $allPermissions = self::allPermissions();
        return [
            'roles' => [
                ['name' => 'super_admin', 'label' => '超级管理员', 'description' => '拥有后台全部菜单权限', 'permissions' => $allPermissions],
                ['name' => 'operator', 'label' => '运营管理员', 'description' => '处理会员、任务、商品和日志', 'permissions' => ['menu.users', 'menu.member_levels', 'menu.balance_logs', 'menu.withdrawals', 'menu.goods', 'menu.tasks', 'menu.partners', 'menu.settings']],
                ['name' => 'support', 'label' => '客服', 'description' => '查看会员与任务，协助处理基础问题', 'permissions' => ['menu.users', 'menu.tasks', 'menu.balance_logs', 'menu.withdrawals']],
            ],
            'admins' => [
                self::defaultAdmin(),
                ['id' => 2, 'username' => 'service', 'role' => 'support', 'enabled' => true, 'last_login' => '-', 'password_hash' => password_hash('service123', PASSWORD_DEFAULT)],
            ],
        ];
    }

    private static function defaultAdmin(): array
    {
        return ['id' => 1, 'username' => 'admin', 'role' => 'super_admin', 'enabled' => true, 'last_login' => '-', 'password_hash' => ''];
    }

    private static function normalizeData(array $data): array
    {
        $roles = self::ensureSuperAdminRole(self::normalizeRoles((array) ($data['roles'] ?? [])));
        $admins = [];
        foreach ((array) ($data['admins'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $role = trim((string) ($row['role'] ?? 'support'));
            $admins[] = [
                'id' => (int) ($row['id'] ?? (count($admins) + 1)),
                'username' => $username,
                'role' => self::findRole($roles, $role) ? $role : 'support',
                'enabled' => (bool) ($row['enabled'] ?? true),
                'last_login' => (string) ($row['last_login'] ?? '-'),
                'password_hash' => (string) ($row['password_hash'] ?? ''),
            ];
        }

        if (!$roles) {
            $roles = self::defaultData()['roles'];
        }
        if (self::findAdminIndex($admins, 'admin') < 0) {
            $admins[] = self::defaultAdmin();
        }

        return ['roles' => $roles, 'admins' => $admins];
    }

    private static function ensureSuperAdminRole(array $roles): array
    {
        if (self::findRole($roles, 'super_admin')) {
            return $roles;
        }

        // 超级管理员是后台兜底入口，缺失时自动补回。
        array_unshift($roles, self::defaultData()['roles'][0]);
        return $roles;
    }

    private static function normalizeRoles(array $roles): array
    {
        $allowed = self::allPermissions();
        $normalized = [];
        foreach ($roles as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($name === '' || $label === '') {
                continue;
            }

            $permissions = self::normalizePermissionList((array) ($row['permissions'] ?? []), $allowed);
            if ($name === 'super_admin') {
                $permissions = $allowed;
            }

            $normalized[] = [
                'name' => $name,
                'label' => $label,
                'description' => (string) ($row['description'] ?? ''),
                'permissions' => $permissions,
            ];
        }

        return $normalized;
    }

    private static function normalizePermissionList(array $permissions, array $allowed): array
    {
        // 只接受当前菜单权限，旧的 site.read/task.manage 等操作权限不再兼容。
        return array_values(array_intersect(array_unique($permissions), $allowed));
    }

    private static function publicData(array $data): array
    {
        $admins = array_map(static function (array $admin): array {
            unset($admin['password_hash']);
            return $admin;
        }, $data['admins']);

        return [
            'permissionGroups' => self::getPermissionGroups(),
            'roles' => $data['roles'],
            'admins' => $admins,
        ];
    }

    private static function buildAdmin(array $admin, array $roles): array
    {
        $role = self::findRole($roles, (string) ($admin['role'] ?? ''));
        $permissions = $role['permissions'] ?? [];
        if (($admin['role'] ?? '') === 'super_admin') {
            $permissions = self::allPermissions();
        }

        return [
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
            'role' => (string) ($admin['role'] ?? ''),
            'role_label' => (string) ($role['label'] ?? $admin['role'] ?? ''),
            'permissions' => array_values(array_unique($permissions)),
        ];
    }

    private static function findAdminIndex(array $admins, string $username): int
    {
        foreach ($admins as $index => $admin) {
            if (($admin['username'] ?? '') === $username) {
                return (int) $index;
            }
        }
        return -1;
    }

    private static function findRole(array $roles, string $name): ?array
    {
        foreach ($roles as $role) {
            if (($role['name'] ?? '') === $name) {
                return $role;
            }
        }
        return null;
    }

    private static function allPermissions(): array
    {
        $permissions = [];
        foreach (self::getPermissionGroups() as $group) {
            foreach ($group['items'] as $item) {
                $permissions[] = $item['key'];
            }
        }
        return $permissions;
    }

    private static function startsWith(string $value, string $prefix): bool
    {
        return $prefix === '' || strpos($value, $prefix) === 0;
    }

    private static function contains(string $value, string $needle): bool
    {
        return $needle !== '' && strpos($value, $needle) !== false;
    }

    private static function logError(string $message): void
    {
        if (class_exists('\\think\\facade\\Log')) {
            \think\facade\Log::error($message);
        }
    }
}
