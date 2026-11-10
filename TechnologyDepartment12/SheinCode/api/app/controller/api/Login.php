<?php

namespace app\controller\api;

use app\BaseController;
use app\model\User;
use app\utils\ImTokenBridge;
use app\utils\JwtTokenUtil;
use think\facade\Db;

class Login extends BaseController
{
    public function login()
    {
        $phone = $this->request->post('phone');
        $password = $this->request->post('password');

        if (!$phone || !$password) {
            return json(['code' => 400, 'msg' => 'Please complete login information']);
        }

        $user = User::where('phone', $phone)->find();
        if (!$user && str_starts_with((string) $phone, '+1')) {
            $user = User::where('phone', substr((string) $phone, 2))->find();
        }
        if (!$user && preg_match('/^\d+$/', (string) $phone)) {
            $user = User::where('phone', '+1' . $phone)->find();
        }
        if (!$user || !password_verify($password, $user->password)) {
            return json(['code' => 400, 'msg' => 'Phone or password is incorrect']);
        }

        if ((int) $user->status !== 1) {
            return json(['code' => 400, 'msg' => 'Account disabled']);
        }

        $this->recordLogin($user);
        return json([
            'code' => 200,
            'msg' => 'Login successful',
            'data' => $this->userPayload($user) + [
                'token' => $this->generateToken($user->id)
            ],
        ]);
    }

    public function getUserInfo()
    {
        $userId = $this->getUserId();
        $user = User::find($userId);

        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        return json([
            'code' => 200,
            'data' => $this->userPayload($user),
        ]);
    }

    public function heartbeat()
    {
        $userId = $this->getUserId();
        if ($userId <= 0) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        Db::name('users')->where('id', $userId)->update([
            'last_active_at' => date('Y-m-d H:i:s'),
        ]);

        return json(['code' => 200, 'msg' => 'ok']);
    }
 
    public function getPublicUserInfo()
    {
        $userId = $this->request->get('user_id',0);
        $user = User::find($userId);
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        return json([
            'code' => 200,
            'data' => $this->userPayload($user),
        ]);
    }

    

    public function changeLoginPassword()
    {
        $user = $this->currentUser();
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        $oldPassword = (string) $this->request->post('old_password', '');
        $newPassword = (string) $this->request->post('new_password', '');
        $confirmPassword = (string) $this->request->post('confirm_password', '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return json(['code' => 400, 'msg' => 'Please fill in all fields']);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return json(['code' => 400, 'msg' => 'Old password is incorrect']);
        }

        $validation = $this->validatePassword($newPassword, $confirmPassword);
        if ($validation) {
            return $validation;
        }

        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        return json(['code' => 200, 'msg' => 'Login password changed successfully']);
    }

    public function changeWithdrawalPassword()
    {
        $user = $this->currentUser();
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        $oldPassword = (string) $this->request->post('old_password', '');
        $newPassword = (string) $this->request->post('new_password', '');
        $confirmPassword = (string) $this->request->post('confirm_password', '');

        if ($oldPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return json(['code' => 400, 'msg' => 'Please fill in all fields']);
        }

        if (!password_verify($oldPassword, (string) $user->withdrawal_password)) {
            return json(['code' => 400, 'msg' => 'Old withdrawal password is incorrect']);
        }

        $validation = $this->validatePassword($newPassword, $confirmPassword, 'Withdrawal password');
        if ($validation) {
            return $validation;
        }

        $user->withdrawal_password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        return json(['code' => 200, 'msg' => 'Withdrawal password changed successfully']);
    }

    public function updateProfile()
    {
        $user = $this->currentUser();
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        $nickname = trim((string) $this->request->post('nickname', ''));
        if ($nickname === '') {
            return json(['code' => 400, 'msg' => 'Nickname cannot be empty']);
        }

        if (mb_strlen($nickname) > 40) {
            return json(['code' => 400, 'msg' => 'Nickname must be 40 characters or less']);
        }

        $user->nickname = $nickname;
        $user->save();

        return json(['code' => 200, 'msg' => 'Nickname updated successfully', 'data' => $this->userPayload($user)]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'user_id' => $user->id,
            'phone' => $user->phone,
            'nickname' => $user->nickname ?: '',
            'invite_code' => $user->invite_code,
            'my_invite_code' => $user->my_invite_code,
            'account_role' => $user->account_role ?: 'customer',
            'is_fake_account' => $user->is_fake_account,
            'real_user_id' => $user->real_user_id,
            'agent_id' => $user->agent_id,
            'paired_user_id' => $user->paired_user_id,
            'managed_by' => $user->managed_by,
            'balance' => $user->balance,
            'frozen_balance' => $user->frozen_balance,
            'task_commission' => $this->sumBalanceLogs((int) $user->id, 'task_commission'),
            'team_commission' => $this->sumBalanceLogs((int) $user->id, 'linked_profit_share'),
            'total_commission' => $user->total_commission,
            'daily_commission' => $user->daily_commission,
            'total_recharge' => $user->total_recharge ?? 0,
            'total_withdrawn' => $user->total_withdrawn,
            'wallet_currency' => $user->wallet_currency ?? 'BTC',
            'wallet_address' => $user->wallet_address ?? '',
            'wallet_note' => $user->wallet_note ?? '',
            'consecutive_days' => $user->consecutive_days,
            'last_login_ip' => $user->last_login_ip ?? '',
            'last_login_at' => $user->last_login_at ?? '',
            'login_count' => (int) ($user->login_count ?? 0),
        ];
    }

    private function sumBalanceLogs(int $userId, string $type): float
    {
        try {
            return (float) Db::name('balance_logs')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->sum('change_amount');
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function getUserId(): int
    {
        return (int) ($this->request->userId ?? 0);
    }

    private function currentUser(): ?User
    {
        return User::find($this->getUserId());
    }

    private function validatePassword(string $newPassword, string $confirmPassword, string $label = 'Login password')
    {
        if ($newPassword !== $confirmPassword) {
            return json(['code' => 400, 'msg' => 'Passwords do not match']);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 20) {
            return json(['code' => 400, 'msg' => $label . ' must be 6-20 characters']);
        }

        return null;
    }

    private function recordLogin(User $user): void
    {
        $user->last_login_ip = $this->clientIp();
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->login_count = ((int) ($user->login_count ?? 0)) + 1;
        $user->save();
    }

    private function clientIp(): string
    {
        foreach (['x-forwarded-for', 'x-real-ip', 'client-ip'] as $header) {
            $value = trim((string) $this->request->header($header, ''));
            if ($value === '') {
                continue;
            }

            $ip = trim(explode(',', $value)[0]);
            if ($ip !== '' && strtolower($ip) !== 'unknown') {
                return substr($ip, 0, 45);
            }
        }

        if (method_exists($this->request, 'ip')) {
            return substr((string) $this->request->ip(), 0, 45);
        }

        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function generateToken(int $userId): string
    {
        $now = time();
        $expiresIn = (int) $this->envValue('JWT_EXPIRE', '86400');

        return JwtTokenUtil::generateToken([
            'uid' => $userId,
            'id' => $userId,
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
