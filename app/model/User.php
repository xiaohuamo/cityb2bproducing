<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class User extends Model
{
    use modelTrait;

    public function getUsers($businessId)
    {
        $map = "(u.role=3 and u.id=$businessId or u.role=20 and u.user_belong_to_user=$businessId) and (sr.roles like '%,0,%' or sr.roles like '%,1,%' or sr.roles like '%,9,%' or sr.roles like '%,11,%')";
        $data = Db::name('user')
            ->alias('u')
            ->field('u.id user_id,u.name,u.nickname,sr.roles')
            ->leftJoin('staff_roles sr','sr.staff_id=u.id')
            ->where($map)
            ->select()->toArray();
        foreach ($data as &$v) {
            $v['user_name'] = $v['nickname'] ?: $v['name'];
        }
        return $data;
    }
}
