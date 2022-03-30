<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;
use app\model\User;

/**
 * @mixin \think\Model
 */
class StaffRoles extends Model
{
    use modelTrait;

    //判断用户是否有生产页面权限
    public function getProductPermission($staff_id)
    {
        $map = "staff_id=$staff_id and (roles like '%,1,%' or roles like '%,9,%' or roles like '%,11,%' or roles like '%,12,%')";
        $info = Db::name('staff_roles')->where($map)->find();
        return $info;
    }

    //判断用户是否有预加工管理权限
    public function getProductPlaningPermission($staff_id)
    {
        $is_permission = 2;
        //判断用户角色
        $role = User::getVal(['id'=>$staff_id],'role');
        if ($role == 3) {
            $is_permission = 1;
        } else {
            $map = "staff_id=$staff_id and (roles like '%,0,%' or roles like '%,1,%' or roles like '%,9,%')";
            $info = Db::name('staff_roles')->where($map)->find();
            //如果用户角色表中不存在该用户
            if($info){
                $is_permission = 1;
            }
        }
        return $is_permission;
    }
}
