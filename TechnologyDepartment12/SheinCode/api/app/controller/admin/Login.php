<?php
namespace app\controller\admin;

use app\BaseController;
use app\service\AdminPermissionService;
use think\facade\Log;

class Login extends BaseController
{
    public function login()
    {
        // param() 同时兼容 JSON 与 form-data，Apifox/前端两种提交方式都能登录。
        $username = (string) $this->request->param('username', '');
        $password = (string) $this->request->param('password', '');

        if ($username === '' || $password === '') {
            return json(['code' => 400, 'msg' => 'Please enter username and password']);
        }

        try {
            $admin = AdminPermissionService::authenticate($username, $password);
        } catch (\Throwable $e) {
            Log::error('Admin login failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return json(['code' => 500, 'msg' => 'Login service error', 'error' => $e->getMessage()]);
        }

        if (!$admin) {
            return json(['code' => 400, 'msg' => 'Invalid username or password']);
        }

        $expiresAt = time() + 86400;

        return json([
            'code' => 200,
            'msg' => 'Login successful',
            'data' => [
                'token' => base64_encode(json_encode([
                    'admin_id' => $admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role'],
                    'permissions' => $admin['permissions'],
                    'exp' => $expiresAt,
                ])),
                'user' => $admin,
            ],
        ]);
    }
}
