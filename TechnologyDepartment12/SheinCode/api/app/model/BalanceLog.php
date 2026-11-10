<?php
namespace app\model;

use think\Model;

class BalanceLog extends Model
{
    protected $table = 'tp_balance_logs';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
}
