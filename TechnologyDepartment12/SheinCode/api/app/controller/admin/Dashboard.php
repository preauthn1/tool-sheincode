<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\BalanceLog;
use app\model\TaskConfig;
use app\model\Tasks;
use app\model\User as UserModel;
use app\service\TaskService;
use think\facade\Db;

class Dashboard extends BaseController
{
    public function summary()
    {
        $userCount = UserModel::count();
        $customerCount = UserModel::where('user_type', 2)->count();
        $agentCount = UserModel::where('user_type', 1)->count();
        $managedCount = UserModel::where('account_role', 'managed')->count();
        $activeCount = UserModel::where('status', 1)->count();
        $roundSummary = $this->currentRoundSummary();
        $todayTaskCount = $roundSummary['total'];
        $todayCompletedCount = $roundSummary['completed'];
        $todayFrozenCount = $roundSummary['frozen'];
        $todayCommission = $roundSummary['commission'];
        $balanceTotal = (float) UserModel::sum('balance');
        $withdrawTotal = (float) UserModel::sum('total_withdrawn');
        $linkedShareTotal = (float) BalanceLog::where('type', 'linked_profit_share')->sum('change_amount');
        $logCount = BalanceLog::count();

        return json([
            'code' => 200,
            'data' => [
                'user_count' => $userCount,
                'customer_count' => $customerCount,
                'agent_count' => $agentCount,
                'managed_count' => $managedCount,
                'active_count' => $activeCount,
                'today_task_count' => $todayTaskCount,
                'today_completed_count' => $todayCompletedCount,
                'today_frozen_count' => $todayFrozenCount,
                'today_commission' => $todayCommission,
                'balance_total' => $balanceTotal,
                'withdraw_total' => $withdrawTotal,
                'linked_share_total' => $linkedShareTotal,
                'log_count' => $logCount,
            ],
        ]);
    }

    private function currentRoundSummary(): array
    {
        $configs = TaskConfig::where('is_active', 1)->select();
        $defaultTotal = $this->getDailyTaskCount();
        $total = 0;
        $completed = 0;
        $frozen = 0;
        $commission = 0.0;

        foreach ($configs as $config) {
            $userId = (int) $config->user_id;
            $roundNo = max(1, (int) ($config->current_round ?? 1));
            $total += (int) ($config->total_tasks ?? 0) > 0 ? (int) $config->total_tasks : $defaultTotal;
            $completed += (int) Tasks::where('user_id', $userId)->where('round_no', $roundNo)->where('status', 1)->count();
            $frozen += (int) Tasks::where('user_id', $userId)->where('round_no', $roundNo)->where('status', 2)->count();
            $commission += (float) Tasks::where('user_id', $userId)->where('round_no', $roundNo)->where('status', 1)->sum('profit');
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'frozen' => $frozen,
            'commission' => $commission,
        ];
    }

    private function getDailyTaskCount(): int
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'daily_task_count')->find();
            $value = (int) ($row['setting_value'] ?? 40);
        } catch (\Throwable $e) {
            $value = 40;
        }

        return $value > 0 ? min($value, 999) : 40;
    }
}
