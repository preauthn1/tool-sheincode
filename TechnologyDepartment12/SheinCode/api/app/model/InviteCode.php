<?php
namespace app\model;

use think\Model;

class InviteCode extends Model
{
    protected $table = 'tp_invite_codes';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
}
