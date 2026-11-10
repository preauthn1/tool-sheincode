<?php

namespace app\controller\api;

use app\BaseController;
use app\model\InviteCode;
use app\model\User;
use app\utils\ImTokenBridge;
use app\utils\JwtTokenUtil;
use think\facade\Validate;

class Register extends BaseController
{
    public function register()
    {
        $data = [
            'phone' => $this->request->post('phone'),
            'password' => $this->request->post('password'),
            'confirm_password' => $this->request->post('confirm_password'),
            'withdrawal_password' => $this->request->post('withdrawal_password'),
            'invite_code' => $this->request->post('invite_code'),
        ];

        $validate = Validate::rule([
            'phone' => 'require',
            'password' => 'require|min:6|max:20',
            'confirm_password' => 'require',
            'withdrawal_password' => 'require|min:6|max:20',
            'invite_code' => 'require',
        ]);

        if (!$validate->check($data)) {
            return json(['code' => 400, 'msg' => $validate->getError()]);
        }

        if ($data['password'] !== $data['confirm_password']) {
            return json(['code' => 400, 'msg' => 'Passwords do not match']);
        }

        if (!User::validatePhone((string)$data['phone'])) {
            return json(['code' => 400, 'msg' => 'Please enter a valid US phone number']);
        }

        if (User::where('phone', $data['phone'])->find()) {
            return json(['code' => 400, 'msg' => 'Phone number already registered']);
        }

        $inviter = User::where('my_invite_code', $data['invite_code'])->find();
        $invite = InviteCode::where('code', $data['invite_code'])->where('is_used', 0)->find();

        if (!$inviter && !$invite) {
            return json(['code' => 400, 'msg' => 'Invalid invitation code']);
        }

        $role = 'customer';
        $agentId = 0;
        $pairedUserId = null;
        $managedBy = null;
        $invitedBy = $inviter ? $inviter->id : 0;

        if ($inviter) {
            if ((int)$inviter->user_type === 1 || $inviter->account_role === 'agent') {
                $role = 'customer';
                $agentId = (int)$inviter->id;
            } else {
                $role = 'managed';
                $pairedUserId = (int)$inviter->id;
                $managedBy = (int)($inviter->agent_id ?: $inviter->invited_by ?: 0);
                $agentId = $managedBy;
            }
        }

        $user = User::create([
            'phone' => $data['phone'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'withdrawal_password' => password_hash($data['withdrawal_password'], PASSWORD_DEFAULT),
            'invite_code' => $data['invite_code'],
            'my_invite_code' => User::generateInviteCode(),
            'invited_by' => $invitedBy,
            'user_type' => 2,
            'agent_id' => $agentId,
            'account_role' => $role,
            'paired_user_id' => $pairedUserId,
            'managed_by' => $managedBy,
            'is_fake_account' => 0,
            'real_user_id' => $role === 'managed' ? $pairedUserId : null,
            'balance' => 10,
        ]);

        if ($role === 'managed' && $pairedUserId) {
            $pairedUser = User::find($pairedUserId);
            if ($pairedUser && !$pairedUser->paired_user_id) {
                $pairedUser->paired_user_id = $user->id;
                $pairedUser->save();
            }
        }

        if ($invite) {
            $invite->is_used = 1;
            $invite->used_by = $user->id;
            $invite->save();
        }

        return json([
            'code' => 200,
            'msg' => 'Registration successful',
            'data' => [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'my_invite_code' => $user->my_invite_code,
                'balance' => $user->balance,
                'account_role' => $user->account_role,
                'paired_user_id' => $user->paired_user_id,
                'agent_id' => $user->agent_id,
                'token' => $this->generateToken($user->id)
            ],
        ]);
    }

    public function checkInviteCode()
    {
        $inviteCode = (string)$this->request->get('invite_code', '');
        $valid = InviteCode::where('code', $inviteCode)->where('is_used', 0)->find()
            || User::where('my_invite_code', $inviteCode)->find();

        return json([
            'code' => 200,
            'data' => ['valid' => (bool)$valid],
        ]);
    }

    private function generateToken(int $userId): string
    {
        $now = time();
        $expiresIn = (int)$this->envValue('JWT_EXPIRE', '86400');

        return JwtTokenUtil::generateToken([
            'uid' => $userId,
            'user_id' => $userId,
            'create_time' => $now,
            'expires_in' => $expiresIn,
            'exp' => $now + $expiresIn,
        ], $this->envValue('JWT_SECRET', 'default_secret_key_change_this'));
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
