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
            ['rm.proucing_item', '=', 1],
            ['wcc.customer_buying_quantity', '>', 0]
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
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        $count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->where($where)
            ->count();
        return $count;
    }

    /**
     * 获取订单的产品信息
     * @param $id
     * @param $businessId
     */
    public function getWccList($businessId,$logistic_delivery_date='',$logistic_truck_No='',$category_id='')
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId','=',$businessId],
            ['wcc.customer_buying_quantity','>',0]
        ];
        if(!empty($logistic_delivery_date)){
            $where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_truck_No !== ''){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        if(!empty($category_id)){
            $where[] = ['rc.id','=',$category_id];
        }
        $data = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,rm.menu_en_name,rm.unit_en,rm.menu_id,rmo.menu_en_name guige_name,rc.id cate_id,rc.category_en_name')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
            ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
            ->where($where)
            ->where($map)
//            ->group('wcc.restaurant_menu_id,wcc.guige1_id')
            ->order('rc.category_sort_id asc,rm.menu_id asc')
            ->select()->toArray();
        $list = [];
        foreach($data as &$v){
            if(!isset($list[$v['cate_id']])){
                $list[$v['cate_id']] = [
                    'cate_id' => $v['cate_id'],
                    'category_en_name' => $v['category_en_name'],
                ];
            }
            if(!isset($list[$v['cate_id']]['product'][$v['product_id']])) {
                $list[$v['cate_id']]['product'][$v['product_id']] = [
                    'product_id' => $v['product_id'],
                    'menu_id' => $v['menu_id'],
                    'menu_en_name' => $v['menu_en_name'],
                    'unit_en' => $v['unit_en'],
                    'sum_quantities' => $v['customer_buying_quantity'],
                ];
            } else {
                $list[$v['cate_id']]['product'][$v['product_id']]['sum_quantities'] += $v['customer_buying_quantity'];
            }
            if($v['guige1_id'] > 0){
                $list[$v['cate_id']]['product'][$v['product_id']]['is_has_two_cate'] = 1;
                if(!isset($list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']])) {
                    $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']] = [
                        'guige1_id' => $v['guige1_id'],
                        'guige_name' => $v['guige_name'],
                        'customer_buying_quantity' => $v['customer_buying_quantity']
                    ];
                } else {
                    $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['customer_buying_quantity'] += $v['customer_buying_quantity'];
                }
            } else {
                $list[$v['cate_id']]['product'][$v['product_id']]['is_has_two_cate'] = 2;
                $list[$v['cate_id']]['product'][$v['product_id']]['customer_buying_quantity'] = $v['customer_buying_quantity'];
            }
        }
        $list = array_values($list);
        foreach($list as $k=>$v){
            $list[$k]['product'] = array_values($list[$k]['product']);
            foreach($list[$k]['product'] as $pk=>$pv){
                if($pv['is_has_two_cate'] == 1){
                    $list[$k]['product'][$pk]['two_cate'] = array_values($list[$k]['product'][$pk]['two_cate']);
                }
            }
        }
        return $list;
    }

    /**
     * 获取订单的产品信息
     * @param $id
     * @param $businessId
     */
    public function updateWccData($where,$data)
    {
        $res = WjCustomerCoupon::alias('wcc')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->where($where)
            ->update($data);
        return $res;
    }
}
