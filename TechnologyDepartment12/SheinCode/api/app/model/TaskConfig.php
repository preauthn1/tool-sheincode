<?php
namespace app\model;

use think\Model;

class TaskConfig extends Model
{
    protected $table = 'tp_task_configs';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
}
