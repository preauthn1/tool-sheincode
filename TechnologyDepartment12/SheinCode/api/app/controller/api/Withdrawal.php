<?php

namespace app\controller\api;

use app\BaseController;
use app\model\BalanceLog;
use app\model\User;
use app\service\TaskService;
use think\facade\Db;

class Withdrawal extends BaseController
{
    private const DEFAULT_MIN_AMOUNT = 100.0;
    private const DEFAULT_REQUIRED_TASKS = 40;

    public function wallet()
    {
        $user = $this->currentUser();
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        return json(['code' => 200, 'data' => $this->walletPayload($user)]);
    }

    public function saveWallet()
    {
        $userId = $this->getUserId();
        $user = User::find($userId);
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        $currency = strtoupper(trim((string) $this->request->post('currency', 'BTC')));
        $address = trim((string) $this->request->post('address', ''));
        $note = trim((string) $this->request->post('note', ''));

        if (!in_array($currency, ['BTC', 'USDT', 'ETH'], true)) {
            return json(['code' => 400, 'msg' => 'Unsupported wallet currency']);
        }
        if ($address === '') {
            return json(['code' => 400, 'msg' => 'Please enter wallet address']);
        }
        if (mb_strlen($address) > 255) {
            return json(['code' => 400, 'msg' => 'Wallet address is too long']);
        }
        if (mb_strlen($note) > 255) {
            return json(['code' => 400, 'msg' => 'Note is too long']);
        }

        Db::table('tp_users')->where('id', $userId)->update([
            'wallet_currency' => $currency,
            'wallet_address' => $address,
            'wallet_note' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $user = User::find($userId);
        return json(['code' => 200, 'msg' => 'Wallet address linked successfully', 'data' => $this->walletPayload($user)]);
    }

    public function submit()
    {
        $userId = $this->getUserId();
        $user = User::find($userId);
        if (!$user) {
            return json(['code' => 401, 'msg' => 'User not found']);
        }

        $amount = round((float) $this->request->post('amount', 0), 2);
        $withdrawPassword = (string) $this->request->post('withdrawal_password', '');
        $walletAddress = trim((string) ($user->wallet_address ?? ''));
        $walletCurrency = strtoupper(trim((string) ($user->wallet_currency ?? 'BTC')));
        $minAmount = $this->withdrawMinAmount();
        $maxAmount = $this->withdrawMaxAmount();
        $taskProgress = $this->taskProgress($userId);
        $requiredTasks = $taskProgress['total'];
        $completedTasks = $taskProgress['completed'];

        if ($amount <= 0) {
            return json(['code' => 400, 'msg' => 'Please enter withdrawal amount']);
        }
        if ($amount < $minAmount) {
            return json(['code' => 400, 'msg' => 'Minimum withdrawal amount is ' . number_format($minAmount, 2, '.', '')]);
        }
        if ($maxAmount > 0 && $amount > $maxAmount) {
            return json(['code' => 400, 'msg' => 'Maximum withdrawal amount is ' . number_format($maxAmount, 2, '.', '')]);
        }
        if ($completedTasks < $requiredTasks) {
            return json(['code' => 400, 'msg' => 'You need to complete all the order quantities.']);
        }
        if ($walletAddress === '') {
            return json(['code' => 400, 'msg' => 'Please link your wallet address first']);
        }
        if ($withdrawPassword === '' || !password_verify($withdrawPassword, (string) $user->withdrawal_password)) {
            return json(['code' => 400, 'msg' => 'Withdrawal password is incorrect']);
        }

        Db::startTrans();
        try {
            $freshUser = User::find($userId);
            if (!$freshUser) {
                Db::rollback();
                return json(['code' => 401, 'msg' => 'User not found']);
            }

            $beforeBalance = (float) $freshUser->balance;
            if ($beforeBalance < $amount) {
                Db::rollback();
                return json(['code' => 400, 'msg' => 'Insufficient balance']);
            }

            $afterBalance = round($beforeBalance - $amount, 2);
            Db::table('tp_users')->where('id', $userId)->update([
                'balance' => $afterBalance,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            BalanceLog::create([
                'user_id' => $userId,
                'change_amount' => -$amount,
                'before_balance' => $beforeBalance,
                'after_balance' => $afterBalance,
                'type' => 'withdraw_pending',
                'remark' => 'Withdrawal request submitted. Wallet address: ' . $walletAddress,
                'operator_id' => null,
            ]);

            $withdrawalId = Db::table('tp_withdrawals')->insertGetId([
                'user_id' => $userId,
                'amount' => $amount,
                'wallet_currency' => $walletCurrency ?: 'BTC',
                'wallet_address' => $walletAddress,
                'wallet_note' => (string) ($freshUser->wallet_note ?? ''),
                'status' => 'pending',
                'admin_id' => null,
                'admin_remark' => '',
                'reviewed_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => 'Withdrawal request submitted', 'data' => ['id' => $withdrawalId, 'balance' => $afterBalance]]);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => 'Withdrawal request failed']);
        }
    }

    private function walletPayload(?User $user): array
    {
        return [
            'currency' => (string) ($user->wallet_currency ?? 'BTC') ?: 'BTC',
            'address' => (string) ($user->wallet_address ?? ''),
            'note' => (string) ($user->wallet_note ?? ''),
        ];
    }

    private function currentUser(): ?User
    {
        return User::find($this->getUserId());
    }

    private function getUserId(): int
    {
        return (int) ($this->request->userId ?? 0);
    }

    private function taskProgress(int $userId): array
    {
        $taskService = new TaskService();
        $total = $taskService->getUserDailyTaskCount($userId);
        $completed = $taskService->currentCompletedCount($userId);

        return [
            'completed' => $completed,
            'total' => $total > 0 ? $total : self::DEFAULT_REQUIRED_TASKS,
        ];
    }

    private function withdrawMinAmount(): float
    {
        $value = (float) $this->settingValue('withdraw_min_amount', (string) self::DEFAULT_MIN_AMOUNT);
        return $value > 0 ? $value : self::DEFAULT_MIN_AMOUNT;
    }

    private function withdrawMaxAmount(): float
    {
        return (float) $this->settingValue('withdraw_max_amount', '100000');
    }

    private function settingValue(string $key, string $default): string
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', $key)->find();
            return (string) ($row['setting_value'] ?? $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
