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

    public function getUsers($businessId,$userId)
    {
        //如果是管理者，则需要获取全部的包括待分配的产品
        $StaffRoles = new StaffRoles();
        $is_permission = $StaffRoles->getProductPlaningPermission($userId);
        if($is_permission == 1||empty($userId)){
            $map = "u.role=3 and u.id=$businessId or (u.role=20 and u.user_belong_to_user=$businessId and (sr.roles like '%,0,%' or sr.roles like '%,1,%' or sr.roles like '%,9,%' or sr.roles like '%,11,%'))";
        } else {
            $map = "staff_id=$userId";
        }
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

    /**
     * 获取用户操作加工数量记录
     * @param $businessId
     * @param $oppd_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserQuantityLog($businessId,$oppd_id){
        $map = "u.role=3 and u.id=$businessId or (u.role=20 and u.user_belong_to_user=$businessId and (sr.roles like '%,0,%' or sr.roles like '%,1,%' or sr.roles like '%,9,%' or sr.roles like '%,11,%'))";
        $data = Db::name('user')
            ->alias('u')
            ->field('u.id user_id,u.name,u.nickname,IFNULL(oppql.quantity,0) quantity')
            ->leftJoin('staff_roles sr','sr.staff_id=u.id')
            ->leftJoin('order_product_planning_quantity_log oppql',"oppql.userId=u.id and oppql.order_product_planning_details_id=$oppd_id")
            ->where($map)
            ->select()->toArray();
        foreach ($data as &$v) {
            $v['user_name'] = $v['nickname'] ?: $v['name'];
        }
        return $data;
    }
}
