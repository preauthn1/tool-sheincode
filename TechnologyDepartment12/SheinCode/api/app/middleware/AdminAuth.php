<?php
namespace app\middleware;

use app\service\AdminPermissionService;

class AdminAuth
{
    public function handle($request, \Closure $next)
    {
        $token = (string) $request->header('Authorization');
        $token = str_replace('Bearer ', '', $token);

        if ($token === '') {
            return json(['code' => 401, 'msg' => 'Not logged in']);
        }

        $payload = json_decode(base64_decode($token), true);
        if (!$payload || !isset($payload['admin_id']) || (($payload['exp'] ?? 0) < time())) {
            return json(['code' => 401, 'msg' => 'Login expired']);
        }

        $admin = AdminPermissionService::resolvePayload((array) $payload);
        if (!$admin) {
            return json(['code' => 401, 'msg' => 'Admin account disabled or missing']);
        }

        $request->adminId = (int) $admin['id'];
        $request->adminUser = $admin;

        $permission = AdminPermissionService::permissionForRequest(
            strtoupper((string) $request->method()),
            (string) $request->pathinfo()
        );

        if ($permission && !AdminPermissionService::can($admin, $permission)) {
            return json(['code' => 403, 'msg' => 'No permission']);
        }

        return $next($request);
    }
}
