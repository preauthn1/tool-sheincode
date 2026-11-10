<?php
namespace app\controller\api;

use app\BaseController;
use app\model\Tasks;

class Bill extends BaseController
{
    private const ENTRIES_PER_TASK = 3;

    public function records()
    {
        $userId = (int) ($this->request->userId ?? 0);
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, min(60, (int) $this->request->get('pageSize', 15)));

        $totalTasks = Tasks::where('user_id', $userId)->where('status', 1)->count();
        $total = $totalTasks * self::ENTRIES_PER_TASK;
        $entryOffset = ($page - 1) * $pageSize;
        $taskOffset = intdiv($entryOffset, self::ENTRIES_PER_TASK);
        $skipEntries = $entryOffset % self::ENTRIES_PER_TASK;
        $taskLimit = (int) ceil(($skipEntries + $pageSize) / self::ENTRIES_PER_TASK);

        $tasks = Tasks::where('user_id', $userId)
            ->where('status', 1)
            ->order('completed_at', 'desc')
            ->order('id', 'desc')
            ->limit($taskOffset, $taskLimit)
            ->select();

        $entries = [];
        foreach ($tasks as $task) {
            foreach ($this->taskEntries($task) as $entry) {
                $entries[] = $entry;
            }
        }

        $list = array_slice($entries, $skipEntries, $pageSize);

        return json([
            'code' => 200,
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'has_more' => ($entryOffset + count($list)) < $total,
            ],
        ]);
    }

    private function taskEntries($task): array
    {
        $amount = round((float) $task->task_amount, 2);
        $commission = round((float) ($task->profit ?: ($amount * (float) $task->commission_rate / 100)), 2);
        $date = $this->entryDate((string) ($task->completed_at ?: $task->updated_at ?: $task->created_at));
        $taskId = (int) $task->id;

        return [
            [
                'id' => $taskId . '-reserved',
                'title' => 'Reserved order',
                'amount' => -$amount,
                'date' => $date,
                'task_id' => $taskId,
                'type' => 'reserved_order',
            ],
            [
                'id' => $taskId . '-commission',
                'title' => 'Task commission',
                'amount' => $commission,
                'date' => $date,
                'task_id' => $taskId,
                'type' => 'task_commission',
            ],
            [
                'id' => $taskId . '-refund',
                'title' => 'Principal Refund',
                'amount' => $amount,
                'date' => $date,
                'task_id' => $taskId,
                'type' => 'principal_refund',
            ],
        ];
    }

    private function entryDate(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-n-j H:i', $timestamp) : $value;
    }
}
