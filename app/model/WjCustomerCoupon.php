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
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,o.business_userId,o.logistic_truck_No,o.logistic_delivery_date,o.is_producing_done order_is_producing_done,wcc.dispatching_is_producing_done,o.dispatching_is_producing_done order_dispatching_is_producing_done')
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
    public function getWccOrderDone($order_id='',$business_userId='',$logistic_delivery_date='',$logistic_truck_No='',$product_id='')
    {
        $where = [
            ['wcc.is_producing_done', '=', 0],
            ['rm.proucing_item', '=', 1]
        ];
        if(!empty($order_id)){
            $where[] = ['wcc.order_id', '=', $order_id];
        }
        if(!empty($business_userId)){
            $where[] = ['o.business_userId', '=', $business_userId];
        }
        if(!empty($logistic_delivery_date)){
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if(!empty($logistic_truck_No)){
            $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
        }
        if(!empty($product_id)){
            $where[] = ['wcc.restaurant_menu_id', '=', $product_id];
        }
        $count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->where($where)
            ->count();
        return $count;
    }

    /**
     * 判断某个订单的所有明细是否都拣货完毕
     * @param $order_id
     */
    public function getWccDispatchingOrderDone($order_id)
    {
        $where = [
            ['wcc.order_id', '=', $order_id],
            ['wcc.dispatching_is_producing_done', '=', 0],
        ];
        $count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->where($where)
            ->count();
        return $count;
    }
}
