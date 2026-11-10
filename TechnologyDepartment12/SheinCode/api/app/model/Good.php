<?php
namespace app\model;

use think\Model;

class Good extends Model
{
    protected $table = 'tp_goods';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
