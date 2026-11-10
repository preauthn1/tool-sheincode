<?php
namespace app\model;

use think\Model;

class Commissions extends Model
{
    protected $table = 'tp_commissions';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
}
