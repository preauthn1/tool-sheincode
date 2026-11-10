<?php

namespace app\controller\api;

use app\BaseController;
use app\service\TaskService;
use think\App;

class Task extends BaseController
{
    protected TaskService $taskService;

    public function __construct(App $app, TaskService $taskService)
    {
        parent::__construct($app);
        $this->taskService = $taskService;
    }

    public function checkCanStart()
    {
        return json($this->taskService->checkCanStart($this->getUserId()));
    }

    public function getTodayTasks()
    {
        return json(['code' => 200, 'data' => $this->taskService->getTodayTasks($this->getUserId())]);
    }

    public function start()
    {
        return json($this->taskService->start($this->getUserId()));
    }

    public function getOrders()
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = max(1, min(50, (int) $this->request->get('pageSize', 10)));
        $status = $this->request->get('status', '');

        return json(['code' => 200, 'data' => $this->taskService->getOrders($this->getUserId(), $page, $pageSize, $status)]);
    }

    public function submitOrder()
    {
        $taskId = (int) $this->request->post('task_id');
        if (!$taskId) {
            return json(['code' => 400, 'msg' => '任务ID不能为空']);
        }

        return json($this->taskService->submitOrder($this->getUserId(), $taskId));
    }

    private function getUserId(): int
    {
        return (int) ($this->request->userId ?? 0);
    }
}
