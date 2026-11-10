<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\Good;
use app\model\TaskConfig;
use app\model\TaskPackageItem;
use app\model\Tasks;
use app\model\User as UserModel;
use app\service\TaskService;
use think\App;
use think\facade\Db;
use think\facade\Log;

class Task extends BaseController
{
    public function __construct(App $app, protected TaskService $taskService)
    {
        parent::__construct($app);
    }

    public function getList()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, (int) $this->request->get('pageSize', 20));
        $userId = (int) $this->request->get('user_id', 0);
        $taskDate = (string) $this->request->get('task_date', '');
        $status = $this->request->get('status', '');

        $query = Tasks::where('status', '<>', -1);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($taskDate !== '') {
            $query->where('task_date', $taskDate);
        }

        $currentPendingIds = $this->getCurrentPendingTaskIds($userId, $taskDate);
        if ($status === '' || $status === null) {
            $query->where(function ($q) use ($currentPendingIds) {
                $q->whereIn('status', [1, 2]);
                if ($currentPendingIds) {
                    $q->whereOr('id', 'in', $currentPendingIds);
                }
            });
        } elseif ((int) $status === 0) {
            $query->whereIn('id', $currentPendingIds ?: [0]);
        } else {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $tasks = $query->order('id', 'desc')->page($page, $pageSize)->select();
        $this->attachTaskDetails($tasks);
        $summary = $this->getListSummary($userId, $taskDate, $currentPendingIds);

        return json(['code' => 200, 'data' => ['list' => $tasks, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'summary' => $summary]]);
    }

    public function config()
    {
        $userId = (int) $this->request->post('user_id');
        $taskAmount = (float) $this->request->post('task_amount', 0);
        $completedTasks = (int) $this->request->post('completed_tasks', 0);
        $taskDate = (string) $this->request->post('task_date', '');

        $level = Db::name('users')->where('id',$userId)->value('member_level');

        $levelConfig = Db::name('member_levels')->where('level', (int) $level)->find();
        if (!$levelConfig) {
            return json(['code' => 400, 'msg' => 'Level not found']);
        }

        $dailyOrders = max(0, min((int) ($levelConfig['daily_orders'] ?? 0), 999));
        if (!$userId || $taskAmount <= 0 || $completedTasks < 0) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => '用户不存在']);
        }
        $agentId = (int) ($user->agent_id ?: 1);
        return json($this->taskService->configDailyTasks($agentId, $userId, $taskAmount, $dailyOrders, $completedTasks, $taskDate ?: null));
    }

    public function reset()
    {
        $userId = (int) $this->request->post('user_id');
        $taskAmount = (float) $this->request->post('task_amount', 0);
        $remark = (string) $this->request->post('remark', '');
        $taskDate = (string) $this->request->post('task_date', '');

        if (!$userId || $remark === '') {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return json(['code' => 400, 'msg' => '用户不存在']);
        }

        $agentId = (int) ($user->agent_id ?: 1);
        return json($this->taskService->resetTasks($agentId, $userId, $taskAmount, $remark, $taskDate ?: null));
    }

    public function getUserTasks()
    {
        $userId = (int) $this->request->get('user_id');
        $taskDate = (string) $this->request->get('task_date', '');
        $page = (int) $this->request->get('page', 1);
        $pageSize = (int) $this->request->get('pageSize', 20);

        $query = Tasks::where('user_id', $userId);
        if ($taskDate !== '') {
            $query->where('task_date', $taskDate);
        } else {
            $currentRound = $this->taskService->currentRound($userId);
            $query->where('round_no', $currentRound);
        }
        $total = $query->count();
        $tasks = $query->order('task_index', 'desc')->order('id', 'desc')->page($page, $pageSize)->select();

        $this->attachTaskDetails($tasks);

        return json([
            'code' => 200,
            'data' => [
                'tasks' => $tasks,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    private function attachTaskDetails($tasks): void
    {
        $goodIds = [];
        $userIds = [];
        foreach ($tasks as $task) {
            if ($task->good_id) {
                $goodIds[] = (int) $task->good_id;
            }
            if ($task->user_id) {
                $userIds[] = (int) $task->user_id;
            }
        }
        $map = [];
        if ($goodIds) {
            $goods = Good::whereIn('id', array_values(array_unique($goodIds)))->select();
            foreach ($goods as $good) {
                $map[(int) $good->id] = $good;
            }
        }
        $userMap = [];
        if ($userIds) {
            $users = UserModel::whereIn('id', array_values(array_unique($userIds)))->select();
            foreach ($users as $user) {
                $userMap[(int) $user->id] = $user;
            }
        }

        foreach ($tasks as $task) {
            $good = $map[(int) $task->good_id] ?? null;
            $user = $userMap[(int) $task->user_id] ?? null;
            if ($good) {
                $task->good_name = $good->name;
                $task->good_image = $good->image;
            }
            $task->user_phone = $user ? $user->phone : '';
            $task->display_id = 'UB' . date('ymd', strtotime((string) $task->task_date)) . str_pad((string) $task->id, 8, '0', STR_PAD_LEFT);
            $task->quantity = 1;
            $task->unit_price = (float) $task->task_amount;
            $task->commission = (float) $task->profit;
            $task->is_combo = (int) $task->is_package;
            $task->ordered_at = $task->created_at;
            $task->completed_time = $task->completed_at;
            $task->round_no = max(1, (int) ($task->round_no ?? 1));
            $task->total_count = $this->countUserRoundTasks((int) $task->user_id);
            $task->completed_count = $this->taskService->completedCountForRound((int) $task->user_id, (int) $task->round_no);
        }
    }

    private function getCurrentPendingTaskIds(int $userId = 0, string $taskDate = ''): array
    {
        $query = Tasks::where('status', 0);
        $query->whereNotNull('matched_at');
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($taskDate !== '') {
            $query->where('task_date', $taskDate);
        }

        $pendingTasks = $query
            ->order('user_id', 'asc')
            ->order('round_no', 'desc')
            ->order('task_index', 'asc')
            ->select();

        $ids = [];
        $seen = [];
        foreach ($pendingTasks as $task) {
            $key = $task->user_id . '|' . (int) ($task->round_no ?? 1);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $ids[] = (int) $task->id;
        }

        return $ids;
    }

    private function getListSummary(int $userId, string $taskDate, array $currentPendingIds): array
    {
        $base = Tasks::where('status', '<>', -1);
        if ($userId > 0) {
            $base->where('user_id', $userId);
        }
        if ($taskDate !== '') {
            $base->where('task_date', $taskDate);
        }

        $completed = $this->summaryCompletedCount($base, $userId, $taskDate);
        $frozen = (clone $base)->where('status', 2)->count();
        $combo = (clone $base)->where('is_package', 1)->count();
        $amount = (float) (clone $base)->where(function ($q) use ($currentPendingIds) {
            $q->whereIn('status', [1, 2]);
            if ($currentPendingIds) {
                $q->whereOr('id', 'in', $currentPendingIds);
            }
        })->sum('task_amount');

        return [
            'visible_total' => $completed + $frozen + count($currentPendingIds),
            'pending' => count($currentPendingIds),
            'completed' => $completed,
            'frozen' => $frozen,
            'combo' => $combo,
            'amount' => $amount,
        ];
    }

    private function summaryCompletedCount($base, int $userId, string $taskDate): int
    {
        if ($userId > 0 && $taskDate === '') {
            return $this->taskService->currentCompletedCount($userId);
        }

        $rows = (clone $base)
            ->where('status', 1)
            ->field('user_id, round_no')
            ->group('user_id, round_no')
            ->select();

        $completed = 0;
        foreach ($rows as $row) {
            $completed += $this->taskService->completedCountForRound((int) $row->user_id, max(1, (int) ($row->round_no ?? 1)));
        }

        return $completed;
    }

    private function countUserRoundTasks(int $userId): int
    {
        $config = $this->taskService->getCurrentTaskConfig($userId);
        $configured = $config ? (int) ($config->total_tasks ?? 0) : 0;
        return $configured > 0 ? $configured : $this->getDailyTaskCount();
    }


    public function getComboTasks()
    {
        $userId = (int) $this->request->get('user_id');
        $taskDate = (string) $this->request->get('task_date', '');

        if (!$userId) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $tasks = Tasks::where('user_id', $userId)
            ->where('status', '<>', -1)
            ->where('is_package', 1)
            ->order('task_index', 'asc');
        if ($taskDate !== '') {
            $tasks->where('task_date', $taskDate);
        } else {
            $currentRound = $this->taskService->currentRound($userId);
            $tasks->where('round_no', $currentRound);
        }
        $tasks = $tasks->select();

        $user = UserModel::find($userId);
        $goodIds = [];
        $taskIds = [];
        foreach ($tasks as $task) {
            $taskIds[] = (int) $task->id;
            if ($task->good_id) {
                $goodIds[] = (int) $task->good_id;
            }
        }

        $itemsByTask = [];
        if ($taskIds) {
            $items = TaskPackageItem::whereIn('task_id', $taskIds)->order('sort_order', 'asc')->select();
            foreach ($items as $item) {
                $itemsByTask[(int) $item->task_id][] = $item;
                if ($item->good_id) {
                    $goodIds[] = (int) $item->good_id;
                }
            }
        }

        $goodsMap = [];
        if ($goodIds) {
            $goods = Good::whereIn('id', array_values(array_unique($goodIds)))->select();
            foreach ($goods as $good) {
                $goodsMap[(int) $good->id] = $good;
            }
        }

        $list = [];
        foreach ($tasks as $task) {
            $good = $goodsMap[(int) $task->good_id] ?? null;
            $packages = $this->formatPackageItems($itemsByTask[(int) $task->id] ?? [], $goodsMap);
            if (!$packages && $task->good_id) {
                $packages[] = [
                    'good_id' => (int) $task->good_id,
                    'good_name' => $good ? $good->name : '',
                    'quantity' => 1,
                    'unit_price' => (float) $task->task_amount,
                    'total_price' => (float) $task->task_amount,
                    'commission_rate' => (float) $task->commission_rate,
                ];
            }
            foreach ($packages as $package) {
                $amount = (float) $package['total_price'];
                $rate = (float) ($package['commission_rate'] ?? $task->commission_rate);
                $commission = round($amount * $rate / 100, 2);
                $status = (int) ($package['status'] ?? 0);
                $statusLabel = $status === 1 ? '已完成' : '未完成';
                $list[] = [
                    'id' => $task->id,
                    'item_id' => $package['id'] ?? 0,
                    'package_item_id' => $package['id'] ?? 0,
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                    'account' => $user ? ('user' . substr((string) $user->phone, -4)) : '-',
                    'task_index' => $task->task_index,
                    'good_id' => $package['good_id'],
                    'good_name' => $package['good_name'],
                    'quantity' => (int) ($package['quantity'] ?? 1),
                    'task_amount' => $amount,
                    'commission' => $commission,
                    'commission_rate' => $rate,
                    'task_commission_rate' => (float) $task->commission_rate,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'product_text' => $this->buildSinglePackageProductText($package, $commission),
                    'sort_order' => (int) ($package['sort_order'] ?? 0),
                ];
            }
        }

        return json(['code' => 200, 'data' => ['list' => $list, 'total' => count($list)]]);
    }

    public function saveComboTask()
    {
        $userId = (int) $this->request->post('user_id');
        $taskIndex = (int) $this->request->post('task_index');
        $goodId = (int) $this->request->post('good_id');
        $taskAmount = (float) $this->request->post('task_amount');
        $commissionRate = (float) $this->request->post('commission_rate');
        $taskDate = (string) $this->request->post('task_date', date('Y-m-d'));
        $mode = (string) $this->request->post('mode', 'append');
        $packages = $this->normalizePackagePayload($this->request->post('packages', null), $goodId, $taskAmount, $commissionRate);

        if (!$userId || !$taskIndex || $commissionRate <= 0 || !$packages) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $currentConfig = $this->taskService->getCurrentTaskConfig($userId);
        $currentRound = $this->taskService->currentRound($userId, $currentConfig);

        Db::startTrans();
        try {
        $task = Tasks::where('user_id', $userId)
            ->where('round_no', $currentRound)
            ->where('task_index', $taskIndex)
            ->where('status', '<>', -1)
            ->lock(true)
            ->find();

        if (!$task) {
            $user = UserModel::find($userId);
            if (!$user) {
                Db::rollback();
                return json(['code' => 400, 'msg' => '用户不存在']);
            }

            $totalTasks = max($this->getDailyTaskCount(), $taskIndex);
            $taskAmountForConfig = max((float) $user->balance, $this->getTaskMinBalance());
            $config = $currentConfig ?: TaskConfig::where('user_id', $userId)->where('is_active', 1)->order('id', 'desc')->lock(true)->find();
            if (!$config) {
                $config = new TaskConfig();
                $config->user_id = $userId;
                $config->config_date = $taskDate;
                $config->current_round = $currentRound;
            }
            $config->agent_id = (int) ($user->agent_id ?: 1);
            $config->task_amount = $taskAmountForConfig;
            $config->total_tasks = max($totalTasks, (int) ($config->total_tasks ?? 0));
            $config->current_round = $currentRound;
            $config->is_active = 1;
            $config->save();

            $taskId = (int) Db::table('tp_tasks')->insertGetId([
                'user_id' => $userId,
                'agent_id' => (int) ($user->agent_id ?: 1),
                'task_date' => $taskDate,
                'round_no' => $currentRound,
                'task_index' => $taskIndex,
                'good_id' => (int) $packages[0]['good_id'],
                'task_amount' => round((float) $packages[0]['task_amount'], 2),
                'profit' => 0,
                'is_package' => 0,
                'commission_rate' => 1,
                'profit_shared' => 0,
                'status' => 0,
                'matched_at' => date("Y-m-d H:i:s"),
                'completed_at' => null,
            ]);
            $task = Tasks::find($taskId);
            if (!$task) {
                throw new \RuntimeException('Failed to create combo task');
            }
        }

        $goodMap = [];
        foreach ($packages as $package) {
            $good = Good::find((int) $package['good_id']);
            if (!$good) {
                Db::rollback();
                return json(['code' => 400, 'msg' => '商品不存在']);
            }
            $goodMap[(int) $good->id] = $good;
        }

            if ((int) $task->status === 1) {
//                Db::rollback();
//                return json(['code' => 400, 'msg' => '已完成的连单不能继续追加']);
                // 重置任务状态
                $updateResult = Tasks::where('id', $task->id)->update([
                    'status' => 0,
                    'completed_at' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                if (!$updateResult) {
                    Db::rollback();
                    return json(['code' => 500, 'msg' => '重置任务状态失败']);
                }
            }

            if ($mode === 'replace') {
                TaskPackageItem::where('task_id', (int) $task->id)->where('status', 0)->delete();
            }

            $maxSortOrder = (int) TaskPackageItem::where('task_id', (int) $task->id)->max('sort_order');
            $task->commission_rate = $commissionRate;
            $task->is_package = 1;
            foreach ($packages as $index => $package) {
                $good = $goodMap[(int) $package['good_id']];
                $amount = round((float) $package['task_amount'], 2);
                TaskPackageItem::create([
                    'task_id' => (int) $task->id,
                    'good_id' => (int) $good->id,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total_price' => $amount,
                    'commission_rate' => (float) $package['commission_rate'],
                    'sort_order' => $maxSortOrder + $index + 1,
                    'status' => 0,
                    'reserved_amount' => 0,
                    'completed_at' => null,
                ]);
            }

            $this->refreshPackageTaskTotals($task, $commissionRate);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('Failed to save combo task: ' . json_encode([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId,
                'task_index' => $taskIndex,
                'round_no' => $currentRound,
                'packages' => $packages,
            ], JSON_UNESCAPED_UNICODE));
            return json(['code' => 500, 'msg' => '连单保存失败']);
        }

        return json(['code' => 200, 'msg' => '连单已保存']);
    }

    private function refreshPackageTaskTotals(Tasks $task, float $fallbackCommissionRate): void
    {
        $items = TaskPackageItem::where('task_id', (int) $task->id)->order('sort_order', 'asc')->select();
        $totalAmount = 0.0;
        $totalCommission = 0.0;
        $firstItem = null;
        foreach ($items as $item) {
            if (!$firstItem) {
                $firstItem = $item;
            }
            $amount = round((float) $item->total_price, 2);
            $rate = (float) ($item->commission_rate ?: $fallbackCommissionRate);
            $totalAmount += $amount;
            $totalCommission += round($amount * $rate / 100, 2);
        }

        $data = [
            'task_amount' => round($totalAmount, 2),
            'profit' => round($totalCommission, 2),
            'is_package' => 1,
            'commission_rate' => $fallbackCommissionRate,
        ];
        if ($firstItem) {
            $data['good_id'] = (int) $firstItem->good_id;
        }

        Db::table('tp_tasks')->where('id', (int) $task->id)->update($data);
    }

    public function deleteComboTask()
    {
        $id = (int) $this->request->post('item_id');
        if (!$id) {
            $id = (int) $this->request->post('package_item_id');
        }
        if (!$id) {
            $id = (int) $this->request->post('id');
        }
        if (!$id) {
            return json(['code' => 400, 'msg' => '参数不完整']);
        }

        $item = TaskPackageItem::find($id);
        if (!$item) {
            return json(['code' => 400, 'msg' => '连单不存在']);
        }

        if ((int) ($item->status ?? 0) === 1 || (float) ($item->reserved_amount ?? 0) > 0) {
            return json(['code' => 400, 'msg' => '已执行或已冻结的连单不能删除']);
        }

        $task = Tasks::find((int) $item->task_id);
        if (!$task) {
            return json(['code' => 400, 'msg' => '连单不存在']);
        }

        $item->delete();
        if ((int) TaskPackageItem::where('task_id', (int) $task->id)->count() === 0) {
            $task->is_package = 0;
            $task->commission_rate = 1;
            if ((int) $task->status === 0) {
                $task->profit = 0;
            }
            $task->save();
        } else {
            $this->refreshPackageTaskTotals($task, (float) $task->commission_rate);
        }

        return json(['code' => 200, 'msg' => '已删除']);
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function normalizePackagePayload($rawPackages, int $fallbackGoodId, float $fallbackAmount, float $fallbackCommissionRate): array
    {
        if (is_string($rawPackages) && $rawPackages !== '') {
            $decoded = json_decode($rawPackages, true);
            $rawPackages = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($rawPackages) || !$rawPackages) {
            $rawPackages = $fallbackGoodId > 0 && $fallbackAmount > 0
                ? [['good_id' => $fallbackGoodId, 'task_amount' => $fallbackAmount, 'commission_rate' => $fallbackCommissionRate]]
                : [];
        }

        $packages = [];
        foreach ($rawPackages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $goodId = (int) ($package['good_id'] ?? 0);
            $amount = round((float) ($package['task_amount'] ?? $package['total_price'] ?? 0), 2);
            $commissionRate = round((float) ($package['commission_rate'] ?? $fallbackCommissionRate), 4);
            if ($goodId <= 0 || $amount <= 0 || $commissionRate <= 0) {
                continue;
            }
            $packages[] = ['good_id' => $goodId, 'task_amount' => $amount, 'commission_rate' => $commissionRate];
        }

        return $packages;
    }

    private function formatPackageItems(array $items, array $goodsMap): array
    {
        $packages = [];
        foreach ($items as $item) {
            $good = $goodsMap[(int) $item->good_id] ?? null;
            $packages[] = [
                'id' => (int) $item->id,
                'good_id' => (int) $item->good_id,
                'good_name' => $good ? $good->name : '',
                'quantity' => (int) ($item->quantity ?: 1),
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'commission_rate' => (float) ($item->commission_rate ?: 0),
                'sort_order' => (int) ($item->sort_order ?? 0),
                'status' => (int) ($item->status ?? 0),
            ];
        }

        return $packages;
    }

    private function getPackageDisplayCommissionRate(array $packages, float $fallbackRate): float
    {
        foreach ($packages as $package) {
            $rate = (float) ($package['commission_rate'] ?? 0);
            if ((int) ($package['status'] ?? 0) === 0 && $rate > 0) {
                return $rate;
            }
        }

        foreach ($packages as $package) {
            $rate = (float) ($package['commission_rate'] ?? 0);
            if ($rate > 0) {
                return $rate;
            }
        }

        return $fallbackRate;
    }

    private function buildPackageProductText(array $packages, float $amount, float $commission): string
    {
        $parts = [];
        foreach ($packages as $package) {
            $parts[] = '[' . ($package['good_id'] ?: '-') . ']Amount: ' . $this->formatAmount((float) $package['total_price']) . ' Rate: ' . $this->formatAmount((float) ($package['commission_rate'] ?? 0)) . '%';
        }

        if (!$parts) {
            $parts[] = '[-]Amount: ' . $this->formatAmount($amount) . ' Commission: ' . $this->formatAmount($commission);
        }

        return implode(' / ', $parts);
    }

    private function buildSinglePackageProductText(array $package, float $commission): string
    {
        return '[' . ($package['good_id'] ?: '-') . ']订单总额： ' . $this->formatAmount((float) $package['total_price']) . '佣金： ' . $this->formatAmount($commission);
    }

    private function getDailyTaskCount(): int
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'daily_task_count')->find();
            $value = $row['setting_value'] ?? 0;
        } catch (\Throwable $e) {
            $value = 0;
        }

        $count = (int) $value;
        if ($count <= 0) {
            $count = 40;
        }

        return min($count, 999);
    }

    private function getDefaultCommissionRate(): float
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'default_commission_rate')->find();
            return is_numeric($row['setting_value'] ?? null) ? max(0, (float) $row['setting_value']) : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function getTaskMinBalance(): float
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'task_min_balance')->find();
            return is_numeric($row['setting_value'] ?? null) ? max(0, (float) $row['setting_value']) : 50;
        } catch (\Throwable $e) {
            return 50;
        }
    }
}
