<?php
namespace app\controller\admin;

use app\BaseController;
use app\service\AdminPermissionService;
use think\facade\Log;

class AdminPermission extends BaseController
{
    public function index()
    {
        if (!$this->canManage()) {
            return json(['code' => 403, 'msg' => 'No permission']);
        }

        return json(['code' => 200, 'data' => AdminPermissionService::getData()]);
    }

    public function saveRoles()
    {
        if (!$this->canManage()) {
            return json(['code' => 403, 'msg' => 'No permission']);
        }

        $roles = $this->request->post('roles', []);
        if (!is_array($roles)) {
            return json(['code' => 400, 'msg' => 'Invalid roles']);
        }

        try {
            return json(['code' => 200, 'data' => AdminPermissionService::saveRoles($roles)]);
        } catch (\Throwable $e) {
            Log::error('Failed to save admin roles: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return json(['code' => 500, 'msg' => 'Failed to save roles', 'error' => $e->getMessage()]);
        }
    }

    public function saveAdmins()
    {
        if (!$this->canManage()) {
            return json(['code' => 403, 'msg' => 'No permission']);
        }

        $admins = $this->request->post('admins', []);
        if (!is_array($admins)) {
            return json(['code' => 400, 'msg' => 'Invalid admins']);
        }

        try {
            return json(['code' => 200, 'data' => AdminPermissionService::saveAdmins($admins)]);
        } catch (\Throwable $e) {
            Log::error('Failed to save admin accounts: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return json(['code' => 500, 'msg' => 'Failed to save admins', 'error' => $e->getMessage()]);
        }
    }

    private function canManage(): bool
    {
        return AdminPermissionService::can((array) ($this->request->adminUser ?? []), 'menu.permission');
    }
}
