<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class WjCustomerCoupon extends Model
{
    use modelTrait;

    /**
     * 获取加工明细产品信息
     * @param $id
     * @param $businessId
     */
    public function getWccInfo($id,$businessId)
    {
        $wcc_info = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,o.logistic_truck_No,o.logistic_delivery_date')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->where([
                ['wcc.id','=',$id],
                ['o.business_userId','=',$businessId]
            ])->find();
        return $wcc_info;
    }

    /**
     * 判断某个订单的加工产品是否全部加工完毕
     * @param $order_id
     */
    public function getWccOrderDone($order_id)
    {
        $where = [
            ['wcc.order_id', '=', $order_id],
            ['wcc.is_producing_done', '=', 0],
            ['rm.proucing_item', '=', 1]
        ];
        $count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->count();
        return $count;
    }
}
