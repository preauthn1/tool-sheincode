<?php
namespace app\model;

use think\Model;

class Withdrawal extends Model
{
    protected $table = 'tp_withdrawals';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
