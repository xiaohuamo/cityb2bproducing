<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;

/**
 * @mixin \think\Model
 */
class StaffRoles extends Model
{
    use modelTrait;

    //判断用户是否有生产页面权限
    public function getProductPermission($staff_id)
    {
        $map1 = [
            ['roles','like','%,1,%']
        ];
        $map2 = [
            ['roles','like','%,9,%']
        ];
        $map3 = [
            ['roles','like','%,11,%']
        ];
        $info = Db::name('staff_roles')->where(['staff_id'=>$staff_id])->whereOr([$map1,$map2,$map3])->find();
        return $info;
    }
}
