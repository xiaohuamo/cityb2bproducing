<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class OrderProductPlanningDetails extends Model
{
    use modelTrait;

    /**
     * 获取加工明细产品信息
     * @param $id
     * @param $businessId
     */
    public function getWccInfo($id,$businessId)
    {
        $wcc_info = self::alias('oppd')
            ->field('oppd.id,oppd.order_id,oppd.restaurant_menu_id product_id,oppd.guige1_id,oppd.customer_buying_quantity,oppd.new_customer_buying_quantity,oppd.is_producing_done,opp.logistic_delivery_date,opp.is_producing_done order_is_producing_done,pps.operator_user_id')
            ->leftJoin('order_product_planing opp','opp.orderId = oppd.order_id')
            ->leftJoin('producing_planing_progress_summery pps',"pps.delivery_date = opp.logistic_delivery_date and pps.business_userId=$businessId and pps.product_id=oppd.restaurant_menu_id and pps.guige1_id=oppd.guige1_id")
            ->where([
                ['oppd.id','=',$id],
                ['opp.business_userId','=',$businessId]
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
            ['oppd.order_id', '=', $order_id],
            ['oppd.is_producing_done', '=', 0],
            ['rm.proucing_item', '=', 1]
        ];
        $count = self::alias('oppd')
            ->leftJoin('restaurant_menu rm','rm.id = oppd.restaurant_menu_id')
            ->where($where)
            ->count();
        return $count;
    }
}
