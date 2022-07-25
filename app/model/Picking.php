<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class Picking extends Model
{
    use modelTrait;

    /**
     * 获取订单信息
     * @param $orderId  订单id
     * @return array
     */
    public function getOrderInfo($orderId)
    {
        $where = [
            ['p.orderId', '=', $orderId],
        ];
        //获取加工明细单数据
        $order = Db::name('picking')
            ->alias('p')
            ->field('p.id,p.orderId,p.business_userId,p.coupon_status,p.displayName,p.first_name,p.last_name,p.address,p.receipt_picture,p.phone,p.userId,uf.user_id,uf.factory_id,uf.nickname,uf.pic')
            ->leftJoin('user_factory uf','uf.user_id = p.userId and factory_id=p.business_userId')
            ->leftJoin('user u','u.id = p.business_userId')
            ->where($where)
            ->find();
        if($order){
            $Order = new Order();
            $name = $Order->getCustomerName($order);
            $order['name'] = $name;
        }
        return $order;
    }

    /**
     * 获取司机端的订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_schedule_id 配送司机调度id
     * @param int $type 1。获取boxs的统计信息 2.获取订单的统计信息 3.获取订单送达的统计信息
     * @return array
     */
    public function getPickingOrderCount($businessId,$logistic_delivery_date,$logistic_schedule_id,$type=2)
    {
        $map = "(p.coupon_status='p01' or p.coupon_status='b01')";
        $where = [
            ['p.business_userId', '=', $businessId],
            ['p.logistic_delivery_date', '=', $logistic_delivery_date],
            ['p.logistic_schedule_id', '=', $logistic_schedule_id]
        ];
        $sql_model = Db::name('picking')
            ->alias('p')
            ->where($where)
            ->where($map);
        //获取需要配送的订单总数
        if($type == 2 || $type == 3){
            $order_count = $sql_model->count();
        } else {
            $order_count_arr = $sql_model->field('IF(`edit_boxesNumber`>0,`edit_boxesNumber`,`boxesNumber`) as boxesNumber')->select()->toArray();
            $order_count = array_sum(array_column($order_count_arr,'boxesNumber'));
        }
        //获取已加工的订单总数
        $sql_model1 = Db::name('picking')
            ->alias('p')
            ->where($where)
            ->where($map);
        if($type == 2){
            $order_done_count = $sql_model1->count();
        } elseif ($type == 3) {
            $order_done_count = $sql_model1->where("p.coupon_status='b01'")->count();
        } else {
            $order_done_count_arr = $sql_model1->field('IF(`edit_boxesNumber`>0,`edit_boxesNumber`,`boxesNumber`) as boxesNumber')->select()->toArray();
            $order_done_count = array_sum(array_column($order_done_count_arr,'boxesNumber'));
        }
        return [
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }
}
