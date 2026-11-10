<?php
namespace app\model;

use think\Model;

class User extends Model
{
    protected $table = 'tp_users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $hidden = ['password', 'withdrawal_password'];

    public static function generateInviteCode(): string
    {
        do {
            $code = (string) mt_rand(1000, 999999);
        } while (self::where('my_invite_code', $code)->find());

        return $code;
    }

    public static function validateInviteCode(string $code): bool
    {
        return (bool) preg_match('/^\d{4,6}$/', $code);
    }

    public static function validatePhone(string $phone): bool
    {
        return (bool) preg_match('/^\+1[2-9]\d{6,9}$/', $phone);
    }
}
