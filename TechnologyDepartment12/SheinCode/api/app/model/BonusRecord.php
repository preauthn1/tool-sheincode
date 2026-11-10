<?php
namespace app\model;

use think\Model;

class BonusRecord extends Model
{
    protected $table = 'tp_bonus_records';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
