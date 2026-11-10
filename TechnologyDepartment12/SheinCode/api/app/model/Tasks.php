<?php
namespace app\model;

use think\Model;

class Tasks extends Model
{
    protected $table = 'tp_tasks';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
