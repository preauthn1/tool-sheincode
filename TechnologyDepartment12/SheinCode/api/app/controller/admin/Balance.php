<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\BalanceLog;
use app\model\User as UserModel;
use think\facade\Db;

class Balance extends BaseController
{
    public function update()
    {
        $userId = (int) $this->request->post('user_id');
        $operationType = (string) $this->request->post('operation_type');
        $amount = (float) $this->request->post('amount');
        $remark = (string) $this->request->post('remark', '');
        $adminId = (int) ($this->request->adminId ?? 0);

        if (!$userId || $operationType === '' || $amount == 0) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => '用户不存在']);
        }

        Db::startTrans();
        try {
            $oldBalance = (float) $user->balance;
            $changeAmount = 0.0;

            if ($operationType === 'deposit') {
                if ($amount <= 0) {
                    Db::rollback();
                    return json(['code' => 400, 'msg' => '充值金额必须大于0']);
                }
                $changeAmount = $amount;
                $user->balance = $oldBalance + $amount;
                $user->total_recharge = ((float) ($user->total_recharge ?? 0)) + $amount;
            } elseif ($operationType === 'adjust') {
                if ($oldBalance + $amount < 0) {
                    Db::rollback();
                    return json(['code' => 400, 'msg' => '调整后余额不能小于0']);
                }
                $changeAmount = $amount;
                $user->balance = $oldBalance + $amount;
            } elseif ($operationType === 'withdraw') {
                if ($amount <= 0) {
                    Db::rollback();
                    return json(['code' => 400, 'msg' => '提现金额必须大于0']);
                }
                if ($oldBalance < $amount) {
                    Db::rollback();
                    return json(['code' => 400, 'msg' => '余额不足']);
                }
                $changeAmount = -$amount;
                $user->balance = $oldBalance - $amount;
                $user->total_withdrawn = ((float) ($user->total_withdrawn ?? 0)) + $amount;
            } else {
                Db::rollback();
                return json(['code' => 400, 'msg' => '无效操作类型']);
            }

            $user->save();

            BalanceLog::create([
                'user_id' => $userId,
                'change_amount' => $changeAmount,
                'before_balance' => $oldBalance,
                'after_balance' => $user->balance,
                'type' => $operationType,
                'remark' => $remark,
                'operator_id' => $adminId,
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => '操作成功']);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => '操作失败']);
        }
    }

    public function getRecords()
    {
        $userId = (int) $this->request->get('user_id', 0);
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);

        $query = BalanceLog::where('id', '>', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        $total = $query->count();
        $list = $query->order('id', 'desc')->page($page, $pageSize)->select();
        $userIds = [];
        foreach ($list as $record) {
            $userIds[] = (int) $record->user_id;
        }
        $users = $userIds ? UserModel::whereIn('id', array_values(array_unique($userIds)))->select()->column(null, 'id') : [];

        foreach ($list as $record) {
            $recordUserId = (int) $record->user_id;
            $user = $users[$recordUserId] ?? null;
            $changeAmount = (float) $record->change_amount;
            $beforeBalance = (float) $record->before_balance;
            $afterBalance = (float) $record->after_balance;

            $record->related_user = [
                'id' => $user ? $user->id : $recordUserId,
                'nickname' => $user ? ($user->nickname ?: '') : '',
                'account' => $user ? ($user->phone ?: ('user' . $user->id)) : '',
                'phone' => $user ? $user->phone : '',
            ];
            $record->display_type = $this->displayType((string) $record->type, $changeAmount);
            $record->display_amount = abs($changeAmount);
            $record->change_record = number_format($beforeBalance, 2, '.', '') . ($changeAmount >= 0 ? '+' : '-') . number_format(abs($changeAmount), 2, '.', '') . '=' . number_format($afterBalance, 2, '.', '');
        }

        return json([
            'code' => 200,
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    private function displayType(string $type, float $changeAmount): string
    {
        if ($type === 'withdraw' || $type === 'withdraw_pending') {
            return '提现';
        }
        if ($type === 'withdraw_reject_refund' || $type === 'withdraw_approved') {
            return '交易';
        }
        if ($type === 'task_commission' || $type === 'linked_profit_share' || $type === 'bonus') {
            return '返佣';
        }
        if ($type === 'deposit' || $type === 'adjust') {
            return '交易';
        }

        return $changeAmount >= 0 ? '交易' : '提现';
    }
}
