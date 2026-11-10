<?php

namespace app\controller\admin;

use app\BaseController;
use app\model\BalanceLog;
use app\model\User as UserModel;
use think\facade\Db;

class Withdrawal extends BaseController
{
    public function getList()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, (int) $this->request->get('pageSize', 20));
        $status = trim((string) $this->request->get('status', ''));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = Db::table('tp_withdrawals')->alias('w')
            ->leftJoin('tp_users u', 'u.id = w.user_id')
            ->field('w.*, u.phone, u.nickname, u.balance');

        if ($status !== '') {
            $query->where('w.status', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('u.phone', "%{$keyword}%")
                    ->whereOr('u.nickname', 'like', "%{$keyword}%")
                    ->whereOr('w.wallet_address', 'like', "%{$keyword}%");
            });
        }

        $total = (clone $query)->count();
        $list = $query->order('w.id', 'desc')->page($page, $pageSize)->select();

        return json(['code' => 200, 'data' => [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]]);
    }

    public function approve()
    {
        return $this->review('approved');
    }

    public function reject()
    {
        return $this->review('rejected');
    }

    private function review(string $targetStatus)
    {
        $id = (int) $this->request->post('id', 0);
        $remark = trim((string) $this->request->post('remark', ''));
        $adminId = (int) ($this->request->adminId ?? 0);

        if (!$id) {
            return json(['code' => 400, 'msg' => 'Invalid withdrawal id']);
        }

        Db::startTrans();
        try {
            $withdrawal = Db::table('tp_withdrawals')->where('id', $id)->find();
            if (!$withdrawal) {
                Db::rollback();
                return json(['code' => 404, 'msg' => 'Withdrawal request not found']);
            }
            if (($withdrawal['status'] ?? '') !== 'pending') {
                Db::rollback();
                return json(['code' => 400, 'msg' => 'This withdrawal has already been reviewed']);
            }

            $userId = (int) $withdrawal['user_id'];
            $amount = round((float) $withdrawal['amount'], 2);

            if ($targetStatus === 'rejected') {
                $user = UserModel::find($userId);
                if (!$user) {
                    Db::rollback();
                    return json(['code' => 404, 'msg' => 'User not found']);
                }

                $beforeBalance = (float) $user->balance;
                $afterBalance = round($beforeBalance + $amount, 2);
                Db::table('tp_users')->where('id', $userId)->update([
                    'balance' => $afterBalance,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                BalanceLog::create([
                    'user_id' => $userId,
                    'change_amount' => $amount,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $afterBalance,
                    'type' => 'withdraw_reject_refund',
                    'remark' => 'Withdrawal rejected and refunded',
                    'operator_id' => $adminId,
                ]);
            } else {
                $user = UserModel::find($userId);
                $currentBalance = $user ? (float) $user->balance : 0.0;
                Db::table('tp_users')->where('id', $userId)->inc('total_withdrawn', $amount)->update([
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                BalanceLog::create([
                    'user_id' => $userId,
                    'change_amount' => 0,
                    'before_balance' => $currentBalance,
                    'after_balance' => $currentBalance,
                    'type' => 'withdraw_approved',
                    'remark' => 'Withdrawal approved. Amount was deducted when submitted.',
                    'operator_id' => $adminId,
                ]);
            }

            Db::table('tp_withdrawals')->where('id', $id)->update([
                'status' => $targetStatus,
                'admin_id' => $adminId,
                'admin_remark' => $remark,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Db::commit();
            return json(['code' => 200, 'msg' => $targetStatus === 'approved' ? 'Withdrawal approved' : 'Withdrawal rejected']);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => 'Review failed']);
        }
    }

}
