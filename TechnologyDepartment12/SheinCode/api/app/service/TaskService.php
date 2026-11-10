<?php

namespace app\service;

use app\model\BalanceLog;
use app\model\BonusRecord;
use app\model\Commissions;
use app\model\Good;
use app\model\TaskConfig;
use app\model\TaskPackageItem;
use app\model\Tasks;
use app\model\User;
use think\facade\Db;

class TaskService
{
    private const START_BALANCE = 50;
    private const DAILY_TASKS = 40;
    private const TASK_RATE = 0.01;
    private const SHARE_RATE = 0.25;

    public function checkCanStart(int $userId): array
    {
        $user = User::find($userId);
        if (!$user || (int)$user->status !== 1) {
            return ['code' => 400, 'msg' => 'User not found or disabled'];
        }

        $startBalance = $this->getStartBalance();

        if ((float)$user->balance < 0) {
            return ['code' => 400, 'msg' => 'Insufficient balance'];
        }

        if ((float)$user->balance < $startBalance) {
            return ['code' => 400, 'msg' => 'Insufficient balance'];
        }

        if (!$this->hasCompletedTask($userId) && $this->accountAssetBalance($user) < $startBalance) {
            return ['code' => 400, 'msg' => 'Account balance must be at least ' . $startBalance . ' USD to start tasks'];
        }

        return ['code' => 200, 'msg' => 'Ready to start tasks'];
    }

    public function configDailyTasks(int $agentId, int $userId, float $taskAmount, int $totalTasks = self::DAILY_TASKS, int $completedTasks = 0, ?string $taskDate = null): array
    {
        $totalTasks = max(0, min($totalTasks, 999));
        $completedTasks = $totalTasks > 0 ? max(0, min($completedTasks, $totalTasks)) : 0;

        Db::startTrans();
        try {
            $this->ensureRoundColumns();
            $config = $this->ensureUserTaskConfig($agentId, $userId, $taskAmount, $totalTasks);
            $roundNo = (int)($config->current_round ?: 1);
            $config->agent_id = $agentId;
            $config->task_amount = $taskAmount;
            $config->total_tasks = $totalTasks;
            $config->current_round = $roundNo;
            $config->is_active = 1;
            $config->manual_completed_count = $completedTasks;
            $config->save();
//            $createResult = $this->createCompletedTaskRows($agentId, $userId, $roundNo, $completedTasks);
//            if (($createResult['code'] ?? 500) !== 200) {
//                Db::rollback();
//                return $createResult;
//            }
            //如果不是联单 重置index
            Tasks::where('user_id', $userId)->where('is_package', 0)->update([
                'task_index' => $completedTasks + 1
            ]);
            $ids = Tasks::where('user_id', $userId)->where('is_package', 1)->where('status', 1)->column('id');
            if (!empty($ids)) {
                TaskPackageItem::whereIn('task_id', $ids)->where('status', 1)->delete();
            }
            //如果是联单 已经完成的也重置掉
            Tasks::where('user_id', $userId)->where('is_package', 1)->where('status', 1)->update([
                'task_index' => $completedTasks + 1,
                'is_package' => 0
            ]);

            Db::commit();
            return ['code' => 200, 'msg' => 'Tasks configured'];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => 500, 'msg' => 'Failed to configure tasks'];
        }
    }

    public function getTodayTasks(int $userId): array
    {
        $this->ensureRoundColumns();
        $config = $this->getCurrentTaskConfig($userId);
        $roundNo = $this->currentRound($userId, $config);
        $this->cleanupUnmatchedNormalTasks($userId, $roundNo);
        $this->reconcileTodayNormalTasks($userId, $roundNo);
        $allTasks = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', '<>', -1)
            ->order('task_index', 'asc')
            ->select();
        $tasks = $this->visibleTaskRecords($allTasks);
        $this->attachGoods($tasks);
        $completed = $this->completedCountForRound($userId, $roundNo);
        $total = $this->getUserDailyTaskCount($userId, $config);
        $user = User::find($userId);
        $startBalance = $this->getStartBalance();
        $taskRate = $this->getNormalCommissionRate();
        $shareRate = $this->getShareRate();

        return [
            'tasks' => $tasks,
            'completed_count' => $completed,
            'total_count' => $total,
            'today_earn' => $user ? (float)$user->daily_commission : 0,
            'start_balance' => $startBalance,
            'task_rate' => $taskRate,
            'share_rate' => $shareRate,
            'current_round' => $roundNo,
        ];
    }

    public function start(int $userId): array
    {
        $check = $this->checkCanStart($userId);
        if (($check['code'] ?? 500) !== 200) {
            return $check;
        }

        $ensure = $this->ensureTodayTasks($userId);
        if (($ensure['code'] ?? 500) !== 200) {
            return $ensure;
        }

        $config = $this->getCurrentTaskConfig($userId);
        $roundNo = $this->currentRound($userId, $config);
        $currentTask = null;
        $nextTask = null;

        Db::startTrans();
        try {
            $currentTask = $this->getCurrentMatchedTask($userId, $roundNo, true);

            if (!$currentTask) {
                $totalTasks = $config['total_tasks'];
                $nextIndex = $config['manual_completed_count'] + 1;

                if ($nextIndex <= $totalTasks) {
                    $nextTask = $this->getOldestUnmatchedTaskByIndex($userId, $roundNo, $nextIndex);
                    if (!$nextTask) {
                        $user = User::find($userId);
                        if (!$user) {
                            Db::rollback();
                            return ['code' => 400, 'msg' => 'User not found'];
                        }

                        $createResult = $this->createNormalTask($user, $roundNo, $nextIndex, true);
                        if (($createResult['code'] ?? 500) !== 200) {
                            Db::rollback();
                            return $createResult;
                        }

                        $nextTask = $createResult['task'];
                    } else {
                        $nextTask->matched_at = date('Y-m-d H:i:s');
                        $nextTask->task_index = $nextIndex;
                        $nextTask->save();
                    }
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => 500, 'msg' => 'Failed to create tasks'];
        }

        $todayData = $this->getTodayTasks($userId);
        $currentTask = $currentTask ?: $nextTask;
        if ($currentTask) {
            $currentTask = Tasks::where('id', (int)$currentTask->id)
                ->where('status', 0)
                ->whereNotNull('matched_at')
                ->find();
        }

        if ($currentTask) {
            $this->attachGoods([$currentTask]);
            return [
                'code' => 200,
                'msg' => 'Order matched',
                'data' => array_merge($todayData, ['task' => $currentTask]),
            ];
        }

        return [
            'code' => 200,
            'msg' => 'All tasks completed',
            'data' => array_merge($todayData, ['task' => null]),
        ];
    }

    public function getOrders(int $userId, int $page = 1, int $pageSize = 10, $status = ''): array
    {
        $this->ensureRoundColumns();
        $config = $this->getCurrentTaskConfig($userId);
        $roundNo = $this->currentRound($userId, $config);
        $this->cleanupUnmatchedNormalTasks($userId, $roundNo);
        $this->reconcileTodayNormalTasks($userId, $roundNo);
        $completedToday = $this->completedCountForRound($userId, $roundNo);
        $totalToday = $this->getUserDailyTaskCount($userId, $config);
        $currentTask = $this->getCurrentMatchedTask($userId, $roundNo);

        $query = Tasks::where('user_id', $userId)
            ->where('status', '<>', -1);
        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }
        if ($status === '' || $status === null) {
            $query->where(function ($q) use ($currentTask) {
                $q->where('status', 1);
                if ($currentTask) {
                    $q->whereOr('id', (int)$currentTask->id);
                }
            });
        } elseif ((int)$status === 0) {
            $query->where('id', $currentTask ? (int)$currentTask->id : 0);
        }

        $tasks = $query
            ->order('task_date', 'desc')
            ->order('task_index', 'desc')
            ->order('id', 'desc')
            ->select();

        $this->attachGoods($tasks);
        $records = $this->expandOrderRecords($tasks, $currentTask ? (int)$currentTask->id : 0);
        if ($status !== '' && $status !== null) {
            $statusValue = (int)$status;
            $records = array_values(array_filter($records, static function ($record) use ($statusValue) {
                return (int)($record->status ?? 0) === $statusValue;
            }));
        }
        $records = $this->sortOrderRecordsForDisplay($records);

        $total = count($records);
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $pageRecords = array_slice($records, ($page - 1) * $pageSize, $pageSize);

        return [
            'list' => $pageRecords,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'has_more' => ($page * $pageSize) < $total,
            'completed_count' => $completedToday,
            'total_count' => $totalToday,
            'current_round' => $roundNo,
            'current_task_id' => $currentTask ? (int)$currentTask->id : 0,
        ];
    }

    public function submitOrder(int $userId, int $taskId): array
    {
        Db::startTrans();
        try {
            $task = Tasks::where('id', $taskId)
                ->where('user_id', $userId)
                ->where('status', 0)
                ->whereNotNull('matched_at')
                ->lock(true)
                ->find();
            if (!$task) {
                Db::rollback();
                return ['code' => 400, 'msg' => 'Task not found, not started, or already completed'];
            }

            $roundNo = $this->currentRound($userId);
            if ((int)($task->round_no ?: 1) !== $roundNo) {
                Db::rollback();
                return ['code' => 400, 'msg' => 'Task is not in the current round'];
            }

            $currentTask = $this->getCurrentMatchedTask($userId, $roundNo, true);
            if (!$currentTask || (int)$currentTask->id !== (int)$task->id) {
                Db::rollback();
                $totalTasks = $this->getUserDailyTaskCount($userId);
                $completed = $this->completedCountForRound($userId, $roundNo);
                if ($totalTasks > 0 && $completed >= $totalTasks) {
                    return ['code' => 400, 'msg' => "Today's task has been completed"];
                }
                return ['code' => 400, 'msg' => 'Please complete the current order first'];
            }

            $user = User::find($userId);
            if (!$user) {
                Db::rollback();
                return ['code' => 400, 'msg' => 'User not found'];
            }

            $hasPackageItems = $this->hasPackageItems((int)$task->id);
            $packageItem = $hasPackageItems ? $this->getCurrentPackageItem($task) : null;
            $taskAmount = round((float)($packageItem ? $packageItem->total_price : $task->task_amount), 2);
            $userBalance = round((float)$user->balance, 2);
            $isPresetPackage = (int)$task->is_package === 1 && (float)$task->commission_rate > 0;
            $reservedAmount = $isPresetPackage ? min($taskAmount, $packageItem ? round((float)$packageItem->reserved_amount, 2) : round((float)$user->frozen_balance, 2)) : 0.0;
            $hasReservedPackage = $isPresetPackage && $reservedAmount > 0;

            if ($hasReservedPackage && $taskAmount > 0 && $userBalance < 0) {
                Db::rollback();
                return ['code' => 400, 'msg' => 'Submit failed'];
            }

            if (!$hasReservedPackage && $taskAmount > 0 && $userBalance < $taskAmount) {
                if ($isPresetPackage && $packageItem && $this->hasReservationHistory((int)$packageItem->id)) {
                    Db::rollback();
                    return ['code' => 400, 'msg' => 'Submit failed'];
                }

                if (!$isPresetPackage) {
                    Db::rollback();
                    return ['code' => 400, 'msg' => 'Submit failed'];
                }

                if ($userBalance >= 0) {
                    $oldBalance = $userBalance;
                    $user->balance = round($userBalance - $taskAmount, 2);
                    $user->frozen_balance = round((float)$user->frozen_balance + $taskAmount, 2);
                    $user->save();

                    if ($packageItem) {
                        $packageItem->reserved_amount = round((float)$packageItem->reserved_amount + $taskAmount, 2);
                        $packageItem->save();
                    }

                    BalanceLog::create([
                        'user_id' => $user->id,
                        'change_amount' => -$taskAmount,
                        'before_balance' => $oldBalance,
                        'after_balance' => $user->balance,
                        'type' => 'reserved_order',
                        'remark' => $packageItem ? ('Reserved order package item #' . $packageItem->id) : 'Reserved order',
                    ]);
                }

                Db::commit();
                return ['code' => 400, 'msg' => 'Submit failed'];
            }

            $startBalance = $this->getStartBalance();
            if (!$hasReservedPackage && !$this->hasCompletedTask($userId) && $this->accountAssetBalance($user) < $startBalance) {
                Db::rollback();
                return ['code' => 400, 'msg' => 'Submit failed'];
            }

            $isPackage = $isPresetPackage;
            $currentCommissionRate = $packageItem && (float)$packageItem->commission_rate > 0
                ? ((float)$packageItem->commission_rate / 100)
                : ((float)$task->commission_rate / 100);
            $commissionRate = $isPresetPackage ? $currentCommissionRate : $this->getNormalCommissionRate();
            $commission = round($taskAmount * $commissionRate, 2);
            $task->is_package = $isPackage ? 1 : 0;
            $task->commission_rate = $commissionRate * 100;
            $completedAt = date('Y-m-d H:i:s');

            if ($packageItem) {
                $packageItem->status = 1;
                $packageItem->completed_at = $completedAt;
                $packageItem->save();
            }

            $task->profit = $hasPackageItems
                ? $this->completedPackageCommissionTotal((int)$task->id, (float)$task->commission_rate)
                : round((float)$task->profit + $commission, 2);
            $hasPendingPackage = $hasPackageItems ? $this->hasPendingPackageItem((int)$task->id) : false;
            if (!$hasPendingPackage) {
                $task->status = 1;
                $task->completed_at = $completedAt;
            }
            $task->save();

            if ($hasPackageItems && $hasPendingPackage) {
                $nextPackageItem = $this->getCurrentPackageItem($task);
                if ($nextPackageItem) {
                    $this->reservePackageItem($user, $nextPackageItem);
                }
            } elseif ($hasPackageItems) {
                $principalRefund = $this->getPackageReservedTotal((int)$task->id);
                if ($principalRefund > 0) {
                    $oldBalance = (float)$user->balance;
                    $user->frozen_balance = round(max(0, (float)$user->frozen_balance - $principalRefund), 2);
                    $user->balance = round($oldBalance + $principalRefund, 2);
                    $user->save();

                    BalanceLog::create([
                        'user_id' => $user->id,
                        'change_amount' => $principalRefund,
                        'before_balance' => $oldBalance,
                        'after_balance' => $user->balance,
                        'type' => 'principal_refund',
                        'remark' => 'Principal refund',
                    ]);
                }
            } elseif ($hasReservedPackage) {
                $oldBalance = (float)$user->balance;
                $user->frozen_balance = round(max(0, (float)$user->frozen_balance - $reservedAmount), 2);
                $user->balance = round($oldBalance + $reservedAmount, 2);
                $user->save();

                BalanceLog::create([
                    'user_id' => $user->id,
                    'change_amount' => $reservedAmount,
                    'before_balance' => $oldBalance,
                    'after_balance' => $user->balance,
                    'type' => 'principal_refund',
                    'remark' => 'Principal refund',
                ]);
            }

            $paidCommission = $commission;
            if ($paidCommission > 0) {
                $this->addBalance($user, $paidCommission, 'task_commission', 'Task commission');
                $this->grantLinkedShareForTask(
                    $user,
                    $task,
                    $paidCommission,
                    $packageItem ? (int)$packageItem->id : null,
                    !$hasPackageItems
                );
            }
            if (!$hasPendingPackage) {
                $this->incrementManualCompletedCount($userId, $roundNo);
                if ($hasPackageItems) {
                    Tasks::where('id', $task->id)->where('profit_shared', 0)->update(['profit_shared' => 1]);
                }
                $this->checkDailyCompletionBonus($user);
            }

            Db::commit();

            return [
                'code' => 200,
                'msg' => 'Order submitted',
                'data' => [
                    'commission' => $paidCommission,
                    'is_package' => $isPackage,
                    'has_pending_package' => $hasPendingPackage ? 1 : 0,
                    'rate' => $commissionRate * 100,
                ],
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => 500, 'msg' => 'Submit failed'];
        }
    }

    public function resetTasks(int $agentId, int $userId, float $taskAmount, string $remark, ?string $taskDate = null): array
    {
        Db::startTrans();
        try {
            $user = User::find($userId);
            if (!$user) {
                return ['code' => 400, 'msg' => 'User not found'];
            }

            $this->ensureRoundColumns();
            $config = $this->ensureUserTaskConfig(
                $agentId,
                $userId,
                $this->taskAmountForNewResetConfig($userId),
                $this->getDailyTaskCount()
            );
            $currentRound = (int)($config->current_round ?: 1);
            $this->invalidateRoundTasks($userId, $currentRound, $agentId, $remark, false);
            $config->agent_id = $agentId;
            $config->current_round = $currentRound + 1;
            $config->is_active = 1;
            $config->save();
            Db::table('tp_task_configs')->where('id', (int)$config->id)->update(['manual_completed_count' => 0]);

            $freshUser = User::find($userId) ?: $user;
            BalanceLog::create([
                'user_id' => $userId,
                'change_amount' => 0,
                'before_balance' => (float)$freshUser->balance,
                'after_balance' => (float)$freshUser->balance,
                'type' => 'reset_tasks',
                'remark' => $remark,
                'operator_id' => $agentId,
            ]);

            Db::commit();
            return ['code' => 200, 'msg' => 'Task count reset'];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['code' => 500, 'msg' => 'Reset failed'];
        }
    }

    public function currentCompletedCount(int $userId): int
    {
        return $this->completedCountForRound($userId, $this->currentRound($userId));
    }

    public function completedCountForRound(int $userId, int $roundNo): int
    {
        $config = $this->getCurrentTaskConfig($userId);
        if ($config && max(1, (int)($config->current_round ?? 1)) === $roundNo && $config->manual_completed_count !== null) {
            $manualCount = max(0, (int)$config->manual_completed_count);
            $totalTasks = max(0, (int)($config->total_tasks ?? 0));
            return $totalTasks > 0 ? min($manualCount, $totalTasks) : $manualCount;
        }

        return (int)Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', 1)
            ->count();
    }

    private function incrementManualCompletedCount(int $userId, int $roundNo): void
    {
        $config = $this->getCurrentTaskConfig($userId);
        if (!$config || max(1, (int)($config->current_round ?? 1)) !== $roundNo || $config->manual_completed_count === null) {
            return;
        }

        $nextCount = (int)$config->manual_completed_count + 1;
        $totalTasks = max(0, (int)($config->total_tasks ?? 0));
        if ($totalTasks > 0) {
            $nextCount = min($nextCount, $totalTasks);
        }

        Db::table('tp_task_configs')->where('id', (int)$config->id)->update(['manual_completed_count' => $nextCount]);
    }

    public function currentRound(int $userId, ?TaskConfig $config = null): int
    {
        $this->ensureRoundColumns();
        $config = $config ?: $this->getCurrentTaskConfig($userId);
        return max(1, (int)($config->current_round ?? 1));
    }

    public function getCurrentTaskConfig(int $userId): ?TaskConfig
    {
        $this->ensureRoundColumns();
        return TaskConfig::where('user_id', $userId)->where('is_active', 1)->order('id', 'desc')->find();
    }

    private function taskAmountForNewResetConfig(int $userId): float
    {
        $amount = (float)Tasks::where('user_id', $userId)
            ->where('status', '<>', -1)
            ->order('id', 'desc')
            ->value('task_amount');
        return round(max(0, $amount), 2);
    }

    private function ensureRoundColumns(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        try {
            $columns = Db::query("SHOW COLUMNS FROM `tp_task_configs` LIKE 'current_round'");
            if (!$columns) {
                Db::execute("ALTER TABLE `tp_task_configs` ADD COLUMN `current_round` int(11) NOT NULL DEFAULT 1");
            }
            $columns = Db::query("SHOW COLUMNS FROM `tp_task_configs` LIKE 'manual_completed_count'");
            if (!$columns) {
                Db::execute("ALTER TABLE `tp_task_configs` ADD COLUMN `manual_completed_count` int(11) DEFAULT NULL");
            }
            $columns = Db::query("SHOW COLUMNS FROM `tp_tasks` LIKE 'round_no'");
            if (!$columns) {
                Db::execute("ALTER TABLE `tp_tasks` ADD COLUMN `round_no` int(11) NOT NULL DEFAULT 1");
            }
            $ensured = true;
            return;
        } catch (\Throwable $e) {
        }

        try {
            $columns = Db::query('PRAGMA table_info(tp_task_configs)');
            $exists = false;
            foreach ($columns as $column) {
                if (($column['name'] ?? '') === 'current_round') {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                Db::execute('ALTER TABLE tp_task_configs ADD COLUMN current_round INTEGER NOT NULL DEFAULT 1');
            }

            $columns = Db::query('PRAGMA table_info(tp_tasks)');
            $exists = false;
            foreach ($columns as $column) {
                if (($column['name'] ?? '') === 'round_no') {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                Db::execute('ALTER TABLE tp_tasks ADD COLUMN round_no INTEGER NOT NULL DEFAULT 1');
            }
        } catch (\Throwable $e) {
        }

        $ensured = true;
    }

    private function createCompletedTaskRows(int $agentId, int $userId, int $roundNo, int $completedTasks): array
    {
        if ($completedTasks <= 0) {
            return ['code' => 200];
        }

        $user = User::find($userId);
        $maxGoodPrice = $user ? max(0, (float)$user->balance) : 0;
        $commissionRate = $this->getNormalCommissionRate() * 100;
        $existingIndexes = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', '<>', -1)
            ->whereIn('task_index', range(1, $completedTasks))
            ->column('task_index');
        $existingIndexes = array_fill_keys(array_map('intval', $existingIndexes), true);

        for ($i = 1; $i <= $completedTasks; $i++) {
            if (isset($existingIndexes[$i])) {
                continue;
            }

            $good = $this->pickHighestPricedGood($maxGoodPrice);
            if (!$good) {
                $good = $this->pickHighestPricedGood(0, false);
            }
            if (!$good) {
                return ['code' => 400, 'msg' => 'No active goods available'];
            }

            Tasks::create([
                'user_id' => $userId,
                'agent_id' => $agentId,
                'task_date' => date('Y-m-d'),
                'round_no' => $roundNo,
                'task_index' => $i,
                'good_id' => $good->id,
                'task_amount' => (float)$good->price,
                'profit' => 0,
                'commission_rate' => $commissionRate,
                'profit_shared' => 0,
                'status' => 1,
                'matched_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['code' => 200];
    }

    private function invalidateRoundTasks(int $userId, int $roundNo, int $agentId, string $remark, bool $includeCompleted = true): void
    {
        $query = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', '<>', -1);
        if (!$includeCompleted) {
            $query->where('status', '<>', 1);
        }
        $taskIds = $query->column('id');
        if (!$taskIds) {
            return;
        }

        $taskIds = array_map('intval', $taskIds);
        $this->releaseTaskReservations($userId, $taskIds, $agentId, $remark);
        TaskPackageItem::whereIn('task_id', $taskIds)->update(['status' => -1]);
        Tasks::whereIn('id', $taskIds)->update(['status' => -1]);
    }

    private function releaseTaskReservations(int $userId, array $taskIds, int $agentId, string $remark): void
    {
        $reservedAmount = round((float)TaskPackageItem::whereIn('task_id', $taskIds)->sum('reserved_amount'), 2);
        if ($reservedAmount <= 0) {
            return;
        }

        $user = User::where('id', $userId)->lock(true)->find();
        if (!$user) {
            return;
        }

        $releaseAmount = min($reservedAmount, max(0, (float)$user->frozen_balance));
        if ($releaseAmount <= 0) {
            return;
        }

        $oldBalance = (float)$user->balance;
        $user->balance = round($oldBalance + $releaseAmount, 2);
        $user->frozen_balance = round(max(0, (float)$user->frozen_balance - $releaseAmount), 2);
        $user->save();

        BalanceLog::create([
            'user_id' => $userId,
            'change_amount' => $releaseAmount,
            'before_balance' => $oldBalance,
            'after_balance' => $user->balance,
            'type' => 'reset_tasks',
            'remark' => 'Release reserved task funds: ' . $remark,
            'operator_id' => $agentId,
        ]);
    }

    private function grantLinkedShareForTask(User $sourceUser, Tasks $task, ?float $profitAmount = null, ?int $packageItemId = null, bool $markTaskShared = true): void
    {
        if ($profitAmount === null) {
            if ((int)$task->status !== 1 || (int)$task->profit_shared === 1) {
                return;
            }

            $profit = round((float)$task->profit, 2);
        } else {
            $profit = round($profitAmount, 2);
        }

        if ($profit <= 0) {
            return;
        }

        $target = $this->resolveShareTarget($sourceUser);
        if (!$target || (int)$target->id === (int)$sourceUser->id) {
            return;
        }

        $shareRate = $this->getShareRate();
        $shareAmount = $this->truncateMoney($profit * $shareRate);
        if ($shareAmount <= 0) {
            return;
        }

        $shareRatePercent = round($shareRate * 100, 4);
        $remark = $shareRatePercent . '% linked account profit share from task #' . $task->id;
        if ($packageItemId) {
            $remark .= ' package item #' . $packageItemId;
        }
        $remark .= ' by user #' . $sourceUser->id;

        $this->addBalance($target, $shareAmount, 'linked_profit_share', $remark);
        Commissions::create([
            'source_user_id' => $sourceUser->id,
            'target_user_id' => $target->id,
            'task_id' => $task->id,
            'profit_amount' => $profit,
            'commission_rate' => $shareRatePercent,
            'commission_amount' => $shareAmount,
            'type' => 'linked_share',
        ]);

        if ($markTaskShared) {
            Tasks::where('id', $task->id)->where('profit_shared', 0)->update(['profit_shared' => 1]);
        }
    }

    private function resolveShareTarget(User $sourceUser): ?User
    {
        $pairedUserId = (int)($sourceUser->paired_user_id ?: 0);
        if ($pairedUserId > 0 && $pairedUserId !== (int)$sourceUser->id) {
            $pairedUser = User::find($pairedUserId);
            if ($pairedUser) {
                return $pairedUser;
            }
        }

        if ($sourceUser->account_role === 'managed' && $sourceUser->real_user_id) {
            return User::find((int)$sourceUser->real_user_id);
        }

        if ($sourceUser->account_role === 'customer' && $sourceUser->agent_id) {
            return User::find((int)$sourceUser->agent_id);
        }

        if ($sourceUser->account_role === 'agent') {
            $customer = User::where('agent_id', $sourceUser->id)->where('account_role', 'customer')->find();
            return $customer ?: null;
        }

        return null;
    }

    private function addBalance(User $user, float $amount, string $type, string $remark): void
    {
        $oldBalance = (float)$user->balance;
        $user->balance = $oldBalance + $amount;

        if ($type === 'task_commission') {
            $user->daily_commission = ((float)$user->daily_commission) + $amount;
            $user->total_commission = ((float)$user->total_commission) + $amount;
        }

        if ($type === 'linked_profit_share') {
            $user->total_commission = ((float)$user->total_commission) + $amount;
        }

        $user->save();

        BalanceLog::create([
            'user_id' => $user->id,
            'change_amount' => $amount,
            'before_balance' => $oldBalance,
            'after_balance' => $user->balance,
            'type' => $type,
            'remark' => $remark,
        ]);
    }

    private function truncateMoney(float $amount): float
    {
        return floor(max(0, $amount) * 100 + 0.000001) / 100;
    }

    private function checkDailyCompletionBonus(User $user): void
    {
        $today = date('Y-m-d');
        $completedToday = $this->currentCompletedCount((int)$user->id);
        $totalToday = $this->getUserDailyTaskCount((int)$user->id);
        if ($completedToday < $totalToday) {
            return;
        }

        $freshUser = User::find($user->id);
        if ($freshUser && $freshUser->last_work_date === $today) {
            return;
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $user->consecutive_days = $freshUser && $freshUser->last_work_date === $yesterday
            ? ((int)$freshUser->consecutive_days) + 1
            : 1;
        $user->last_work_date = $today;
        $user->save();

        if ($user->consecutive_days === 3) {
            $this->grantBonus($user, 300, '3-day completion bonus');
        }

        if ($user->consecutive_days === 7) {
            $this->grantBonus($user, 700, '7-day completion bonus');
        }
    }

    private function grantBonus(User $user, float $amount, string $remark): void
    {
        $this->addBalance($user, $amount, 'bonus', $remark);

        BonusRecord::create([
            'user_id' => $user->id,
            'bonus_type' => 'consecutive_days',
            'bonus_amount' => $amount,
            'conditions_met' => $remark,
            'status' => 1,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function pickHighestPricedGood(float $maxPrice = 0, bool $enforceMaxPrice = true): ?Good
    {
        $maxPrice = max(0, $maxPrice);
        if ($enforceMaxPrice) {
            $preferredIds = $this->preferredNormalGoodIds($maxPrice);
            if ($preferredIds) {
                $row = Good::whereIn('id', $preferredIds)
                    ->where('status', 1)
                    ->order('price', 'desc')
                    ->find();
                if ($row) {
                    return $row;
                }
            }
        }
        if (!$enforceMaxPrice || $maxPrice <= 0) {
            return Good::where('status', 1)->order('price', 'desc')->find();
        }
        return Good::where('status', 1)
            ->where('price', '<=', $maxPrice)
            ->order('price', 'desc')
            ->find();
    }

    private function pickNormalGood(float $maxPrice = 0, bool $enforceMaxPrice = true): ?Good
    {
        $ids = [];
        $maxPrice = max(0, $maxPrice);

        if ($enforceMaxPrice) {
            $ids = $this->preferredNormalGoodIds($maxPrice);
        }

        if (!$ids) {
            $query = Good::where('status', 1);
            if ($enforceMaxPrice) {
                $query->where('price', '<=', $maxPrice);
            }
            $ids = $query->column('id');
        }

        if (!$ids) {
            return null;
        }

        return Good::find((int)$ids[array_rand($ids)]);
    }

    private function pickLowestPricedGood(float $maxPrice): ?Good
    {
        $maxPrice = max(0, $maxPrice);
        if ($maxPrice <= 0) {
            return null;
        }

        return Good::where('status', 1)
            ->where('price', '<=', $maxPrice)
            ->order('price', 'asc')
            ->find();
    }

    private function reconcileTodayNormalTasks(int $userId, int $roundNo): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $maxGoodPrice = max(0, (float)$user->balance);

        $preferredBounds = $this->preferredNormalGoodBounds($maxGoodPrice);
        $hasPreferredGoods = $preferredBounds !== null && (bool)$this->preferredNormalGoodIds($maxGoodPrice, $preferredBounds);

        $tasks = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', 0)
            ->where('is_package', 0)
            ->select();

        foreach ($tasks as $task) {
            $taskAmount = round((float)$task->task_amount, 2);
            $isMatchedAmount = $hasPreferredGoods
                ? ($taskAmount >= $preferredBounds[0] && $taskAmount <= $preferredBounds[1])
                : ($taskAmount <= $maxGoodPrice);
            if ($isMatchedAmount) {
                continue;
            }

            $good = $this->pickHighestPricedGood($maxGoodPrice);
            if (!$good) {
                return;
            }

            $task->good_id = $good->id;
            $task->task_amount = (float)$good->price;
            $task->profit = 0;
            $task->commission_rate = $this->getNormalCommissionRate() * 100;
            $task->save();
        }
    }

    private function ensureTodayTasks(int $userId): array
    {
        $this->ensureRoundColumns();
        $config = $this->getCurrentTaskConfig($userId);
        $roundNo = $this->currentRound($userId, $config);
        $this->cleanupUnmatchedNormalTasks($userId, $roundNo);
        $this->reconcileTodayNormalTasks($userId, $roundNo);

        $user = User::find($userId);
        if (!$user) {
            return ['code' => 400, 'msg' => 'User not found'];
        }

        $totalTasks = $this->getUserDailyTaskCount($userId, $config);
        if ($totalTasks <= 0) {
            return ['code' => 400, 'msg' => 'Daily task count is not configured'];
        }

        $taskAmount = max((float)$user->balance, $this->getStartBalance());
        $agentId = (int)($user->agent_id ?: 1);
        if (!Good::where('status', 1)->where('price', '<=', max(0, (float)$user->balance))->count()) {
            return ['code' => 400, 'msg' => 'Insufficient balance'];
        }

        try {
            $config = $config ?: new TaskConfig();
            $config->user_id = $userId;
            $config->config_date = $config->config_date ?: date('Y-m-d');
            $config->agent_id = $agentId;
            $config->task_amount = $taskAmount;
            $config->total_tasks = $totalTasks;
            $config->current_round = $roundNo;
            $config->is_active = 1;
            $config->save();

            return ['code' => 200];
        } catch (\Throwable $e) {
            return ['code' => 500, 'msg' => 'Failed to configure tasks'];
        }
    }

    private function createNormalTask(User $user, int $roundNo, int $taskIndex, bool $matched): array
    {
        $balance = max(0, (float)$user->balance);
        $maxGoodPrice = round($balance * (random_int(0, 100) / 100), 2);
        $good = $this->pickHighestPricedGood($maxGoodPrice);
        if (!$good && $maxGoodPrice < $balance) {
            $good = $this->pickLowestPricedGood($balance);
        }
        if (!$good) {
            return ['code' => 400, 'msg' => 'Insufficient balance'];
        }

        $task = Tasks::create([
            'user_id' => (int)$user->id,
            'agent_id' => (int)($user->agent_id ?: 1),
            'task_date' => date('Y-m-d'),
            'round_no' => $roundNo,
            'task_index' => $taskIndex,
            'good_id' => (int)$good->id,
            'task_amount' => (float)$good->price,
            'profit' => 0,
            'commission_rate' => $this->getNormalCommissionRate() * 100,
            'profit_shared' => 0,
            'status' => 0,
            'matched_at' => $matched ? date('Y-m-d H:i:s') : null,
        ]);

        return ['code' => 200, 'task' => $task];
    }

    private function getCurrentMatchedTask(int $userId, int $roundNo, bool $lock = false): ?Tasks
    {
        $config = $this->getCurrentTaskConfig($userId);
        $task_index = $config['manual_completed_count'] + 1;
        $query = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('task_index', $task_index)
            ->where('status', 0)
            ->whereNotNull('matched_at')
            ->order('created_at', 'asc')
            ->order('id', 'asc');

        if ($lock) {
            $query->lock(true);
        }

        return $query->find();
    }

    private function getOldestUnmatchedTaskByIndex(int $userId, int $roundNo, int $taskIndex): ?Tasks
    {
        return Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('task_index', $taskIndex)
            ->where('status', 0)
            ->whereNull('matched_at')
            ->order('created_at', 'asc')
            ->order('id', 'asc')
            ->find();
    }

    private function nextTaskIndex(int $userId, int $roundNo, int $totalTasks): int
    {
        $config = $this->getCurrentTaskConfig($userId);
        if ($config && max(1, (int)($config->current_round ?? 1)) === $roundNo && $config->manual_completed_count !== null) {
            return min(max(1, (int)$config->manual_completed_count + 1), $totalTasks + 1);
        }

        $tasks = Tasks::where('user_id', $userId)
            ->where('round_no', $roundNo)
            ->where('status', '<>', -1)
            ->order('task_index', 'asc')
            ->select();
        $completedMap = [];
        foreach ($tasks as $task) {
            $index = (int)$task->task_index;
            if ($index > 0 && (int)$task->status === 1) {
                $completedMap[$index] = true;
            }
        }

        for ($i = 1; $i <= $totalTasks; $i++) {
            if (!isset($completedMap[$i])) {
                return $i;
            }
        }

        return $totalTasks + 1;
    }

    private function visibleTaskRecords($tasks): array
    {
        $visible = [];
        foreach ($tasks as $task) {
            if ((int)$task->status === 1 || (int)$task->status === 2 || !empty($task->matched_at)) {
                $visible[] = $task;
            }
        }

        return $visible;
    }

    private function cleanupUnmatchedNormalTasks(int $userId, int $roundNo): void
    {
        // Keep generated pending tasks so matching can honor FIFO by created_at.
    }

    private function ensureUserTaskConfig(int $agentId, int $userId, float $taskAmount, int $totalTasks): TaskConfig
    {
        $this->ensureRoundColumns();
        $config = $this->getCurrentTaskConfig($userId);
        if (!$config) {
            $config = new TaskConfig();
            $config->user_id = $userId;
            $config->config_date = date('Y-m-d');
            $config->manual_completed_count = 0;
            $config->current_round = 1;
        }

        $config->agent_id = $agentId;
        $config->task_amount = $taskAmount;
        $config->total_tasks = $totalTasks;
        $config->current_round = max(1, (int)($config->current_round ?: 1));
        $config->is_active = 1;
        $config->save();

        return $config;
    }

    public function getUserDailyTaskCount(int $userId, ?TaskConfig $config = null): int
    {
        try {
            $config = $config ?: $this->getCurrentTaskConfig($userId);
            $configured = $config ? (int)($config->total_tasks ?? 0) : 0;
            if ($configured > 0) {
                return min($configured, 999);
            }
        } catch (\Throwable $e) {
        }

        return $this->getDailyTaskCount();
    }

    private function hasCompletedTask(int $userId): bool
    {
        return (int)Tasks::where('user_id', $userId)->where('status', 1)->count() > 0;
    }

    private function accountAssetBalance(User $user): float
    {
        return round((float)$user->balance + (float)$user->frozen_balance, 2);
    }

    private function getDailyTaskCount(): int
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', 'daily_task_count')->find();
            $value = $row['setting_value'] ?? self::DAILY_TASKS;
        } catch (\Throwable $e) {
            $value = self::DAILY_TASKS;
        }

        $count = (int)$value;
        if ($count <= 0) {
            $count = self::DAILY_TASKS;
        }

        return min($count, 999);
    }

    private function getStartBalance(): float
    {
        return $this->getNumericSetting('task_min_balance', self::START_BALANCE);
    }

    private function getDefaultCommissionRate(): float
    {
        return $this->getNumericSetting('default_commission_rate', self::TASK_RATE * 100) / 100;
    }

    private function getNormalCommissionRate(): float
    {
        return self::TASK_RATE;
    }

    private function getNormalOrderPriceDownDiff(): float
    {
        return max(0, $this->getNumericSetting('normal_order_price_down_diff', 0));
    }

    private function getShareRate(): float
    {
        return $this->getNumericSetting('invite_reward_rate', self::SHARE_RATE * 100) / 100;
    }

    private function getNumericSetting(string $key, float $default): float
    {
        try {
            $row = Db::name('system_settings')->where('setting_key', $key)->find();
            return is_numeric($row['setting_value'] ?? null) ? (float)$row['setting_value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function preferredNormalGoodBounds(float $maxPrice): ?array
    {
        $maxPrice = round(max(0, $maxPrice), 2);
        $downDiff = $this->getNormalOrderPriceDownDiff();
        if ($maxPrice <= 0 || $downDiff <= 0) {
            return null;
        }

        return [round(max(0, $maxPrice - $downDiff), 2), $maxPrice];
    }

    private function preferredNormalGoodIds(float $maxPrice, ?array $bounds = null): array
    {
        $bounds = $bounds ?: $this->preferredNormalGoodBounds($maxPrice);
        if ($bounds === null) {
            return [];
        }

        return Good::where('status', 1)
            ->where('price', '>=', $bounds[0])
            ->where('price', '<=', $bounds[1])
            ->column('id');
    }

    private function attachGoods($tasks): void
    {
        $goodIds = [];
        $taskIds = [];
        foreach ($tasks as $task) {
            $taskIds[] = (int)$task->id;
            if ($task->good_id) {
                $goodIds[] = (int)$task->good_id;
            }
        }

        $itemsByTask = [];
        if ($taskIds) {
            try {
                $items = TaskPackageItem::whereIn('task_id', $taskIds)->order('sort_order', 'asc')->select();
                foreach ($items as $item) {
                    $itemsByTask[(int)$item->task_id][] = $item;
                    if ($item->good_id) {
                        $goodIds[] = (int)$item->good_id;
                    }
                }
            } catch (\Throwable $e) {
                $itemsByTask = [];
            }
        }

        $map = [];
        if ($goodIds) {
            $goods = Good::whereIn('id', array_values(array_unique($goodIds)))->select();
            foreach ($goods as $good) {
                $map[(int)$good->id] = $good;
            }
        }

        foreach ($tasks as $task) {
            $good = $map[(int)$task->good_id] ?? null;
            if ($good) {
                $task->good_name = $good->name;
                $task->good_image = $good->image;
            }

            $packages = [];
            foreach ($itemsByTask[(int)$task->id] ?? [] as $item) {
                $itemGood = $map[(int)$item->good_id] ?? null;
                $packages[] = [
                    'id' => (int)$item->id,
                    'good_id' => (int)$item->good_id,
                    'good_name' => $itemGood ? $itemGood->name : '',
                    'good_image' => $itemGood ? $itemGood->image : '',
                    'quantity' => (int)($item->quantity ?: 1),
                    'unit_price' => (float)$item->unit_price,
                    'total_price' => (float)$item->total_price,
                    'commission_rate' => (float)($item->commission_rate ?: 0),
                    'status' => (int)($item->status ?? 0),
                    'sort_order' => (int)($item->sort_order ?? 0),
                    'reserved_amount' => (float)($item->reserved_amount ?? 0),
                    'completed_at' => $item->completed_at ?? null,
                ];
            }
            if (!$packages && $task->good_id) {
                $packages[] = [
                    'id' => 0,
                    'good_id' => (int)$task->good_id,
                    'good_name' => $good ? $good->name : '',
                    'good_image' => $good ? $good->image : '',
                    'quantity' => 1,
                    'unit_price' => (float)$task->task_amount,
                    'total_price' => (float)$task->task_amount,
                    'commission_rate' => (float)$task->commission_rate,
                    'status' => (int)($task->status ?? 0),
                    'sort_order' => 1,
                    'reserved_amount' => 0.0,
                    'completed_at' => $task->completed_at ?? null,
                ];
            }

            $task->packages = $packages;
            $currentPackage = null;
            foreach ($packages as $package) {
                if ((int)($package['status'] ?? 0) === 0) {
                    $currentPackage = $package;
                    break;
                }
            }
            if ($currentPackage) {
                $task->current_package = $currentPackage;
                $task->good_id = $currentPackage['good_id'];
                $task->good_name = $currentPackage['good_name'];
                $task->good_image = $currentPackage['good_image'];
                $task->task_amount = (float)$currentPackage['total_price'];
                if ((float)($currentPackage['commission_rate'] ?? 0) > 0) {
                    $task->commission_rate = (float)$currentPackage['commission_rate'];
                }
            }
            $task->display_id = 'UB' . date('ymd', strtotime((string)$task->task_date)) . str_pad((string)$task->id, 8, '0', STR_PAD_LEFT);
            $task->quantity = count($packages) ?: 1;
            $task->unit_price = (float)$task->task_amount;
            $task->commission = (float)$task->profit;
            $task->ordered_at = $task->created_at;
            $task->completed_time = $task->completed_at;
        }
    }

    private function expandOrderRecords($tasks, int $currentTaskId): array
    {
        $records = [];
        foreach ($tasks as $task) {
            $packages = is_array($task->packages ?? null) ? $task->packages : [];
            if ((int)($task->is_package ?? 0) !== 1 || !$packages) {
                $task->record_id = 'task-' . (int)$task->id;
                $task->task_id = (int)$task->id;
                $task->package_index = 0;
                $task->package_total = 0;
                $task->is_current_package = ((int)$task->id === $currentTaskId && (int)($task->status ?? 0) === 0) ? 1 : 0;
                $records[] = $task;
                continue;
            }

            $packageTotal = count($packages);
            $currentPackageId = 0;
            foreach ($packages as $package) {
                if ((int)($package['status'] ?? 0) === 0) {
                    $currentPackageId = (int)($package['id'] ?? 0);
                    break;
                }
            }

            foreach ($packages as $index => $package) {
                $packageId = (int)($package['id'] ?? 0);
                $fallbackId = $index + 1;
                $taskStatus = (int)($task->status ?? 0);
                $packageStatus = array_key_exists('status', $package) ? (int)$package['status'] : (int)($task->status ?? 0);
                if ($taskStatus !== 0) {
                    $packageStatus = $taskStatus;
                }
                $isCurrentPackage = (
                    (int)$task->id === $currentTaskId
                    && $packageStatus === 0
                    && ($currentPackageId === 0 || $packageId === $currentPackageId)
                );
                if ($taskStatus === 0 && $packageStatus === 0 && !$isCurrentPackage) {
                    continue;
                }

                $record = clone $task;
                $record->record_id = 'task-' . (int)$task->id . '-package-' . ($packageId ?: $fallbackId);
                $record->task_id = (int)$task->id;
                $record->package_item_id = $packageId;
                $record->package_index = $index + 1;
                $record->package_total = $packageTotal;
                $record->is_current_package = $isCurrentPackage ? 1 : 0;
                $record->good_id = (int)($package['good_id'] ?? 0);
                $record->good_name = (string)($package['good_name'] ?? '');
                $record->good_image = (string)($package['good_image'] ?? '');
                $record->quantity = (int)($package['quantity'] ?? 1);
                $record->unit_price = (float)($package['unit_price'] ?? $package['total_price'] ?? 0);
                $record->task_amount = (float)($package['total_price'] ?? 0);
                if ((float)($package['commission_rate'] ?? 0) > 0) {
                    $record->commission_rate = (float)$package['commission_rate'];
                }
                $record->commission = round((float)$record->task_amount * (float)$record->commission_rate / 100, 2);
                $record->status = $packageStatus;
                $record->completed_time = $package['completed_at'] ?: $task->completed_at;
                $records[] = $record;
            }
        }

        return $records;
    }

    private function sortOrderRecordsForDisplay(array $records): array
    {
        usort($records, static function ($a, $b) {
            $statusPriority = static function ($record): int {
                $status = (int)($record->status ?? 0);
                if ($status === 0) {
                    return 0;
                }
                if ($status === 2) {
                    return 1;
                }
                return 2;
            };

            $priorityDiff = $statusPriority($a) <=> $statusPriority($b);
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }

            $dateCompare = strcmp((string)($b->task_date ?? ''), (string)($a->task_date ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $indexDiff = (int)($b->task_index ?? 0) <=> (int)($a->task_index ?? 0);
            if ($indexDiff !== 0) {
                return $indexDiff;
            }

            $idDiff = (int)($b->id ?? 0) <=> (int)($a->id ?? 0);
            if ($idDiff !== 0) {
                return $idDiff;
            }

            return (int)($a->package_index ?? 0) <=> (int)($b->package_index ?? 0);
        });

        return $records;
    }

    private function getCurrentPackageItem(Tasks $task): ?TaskPackageItem
    {
        if ((int)$task->is_package !== 1) {
            return null;
        }

        try {
            return TaskPackageItem::where('task_id', (int)$task->id)
                ->where('status', 0)
                ->order('sort_order', 'asc')
                ->find();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hasPackageItems(int $taskId): bool
    {
        try {
            return (int)TaskPackageItem::where('task_id', $taskId)->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasPendingPackageItem(int $taskId): bool
    {
        try {
            return (int)TaskPackageItem::where('task_id', $taskId)->where('status', 0)->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function completedPackageCommissionTotal(int $taskId, float $fallbackCommissionRate): float
    {
        try {
            $items = TaskPackageItem::where('task_id', $taskId)->where('status', 1)->select();
        } catch (\Throwable $e) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $amount = round((float)$item->total_price, 2);
            $rate = (float)($item->commission_rate ?: $fallbackCommissionRate);
            if ($amount <= 0 || $rate <= 0) {
                continue;
            }

            $total += round($amount * $rate / 100, 2);
        }

        return round($total, 2);
    }

    private function hasReservationHistory(int $packageItemId): bool
    {
        try {
            return (int)BalanceLog::where('type', 'reserved_order')
                    ->where('remark', 'Reserved order package item #' . $packageItemId)
                    ->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function reservePackageItem(User $user, TaskPackageItem $packageItem): void
    {
        $amount = round((float)$packageItem->total_price, 2);
        if ($amount <= 0 || (float)$packageItem->reserved_amount > 0) {
            return;
        }

        $oldBalance = (float)$user->balance;
        $user->balance = round($oldBalance - $amount, 2);
        $user->frozen_balance = round((float)$user->frozen_balance + $amount, 2);
        $user->save();

        $packageItem->reserved_amount = $amount;
        $packageItem->save();

        BalanceLog::create([
            'user_id' => $user->id,
            'change_amount' => -$amount,
            'before_balance' => $oldBalance,
            'after_balance' => $user->balance,
            'type' => 'reserved_order',
            'remark' => 'Reserved order package item #' . $packageItem->id,
        ]);
    }

    private function getPackageReservedTotal(int $taskId): float
    {
        try {
            return round((float)TaskPackageItem::where('task_id', $taskId)->sum('reserved_amount'), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

}
