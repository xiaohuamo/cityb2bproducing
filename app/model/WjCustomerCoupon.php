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
    public function getWccList($businessId,$userId,$logistic_delivery_date='',$logistic_truck_No='',$category_id='')
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
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,rm.menu_en_name,rm.unit_en,rm.menu_id,rm.proucing_item,rmo.menu_en_name guige_name,rc.id cate_id,rc.category_en_name')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
            ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
            ->where($where)
            ->where($map)
//            ->group('wcc.restaurant_menu_id,wcc.guige1_id')
            ->order('rc.category_sort_id asc,rm.menu_id asc')
            ->select()->toArray();
        $ProducingProgressSummery = new ProducingProgressSummery();
        $product_status_arr = [];//存储产品的状态
        $product_id_arr = [];//存储加工产品
        $product_no_id_arr = [];//存储非加工产品
        foreach ($data as &$v){
            if($v['proucing_item'] == 1){
                $product_id_arr[] = $v['product_id'];
            }else{
                $product_no_id_arr[] = $v['product_id'];
            }
        }
        //如果存在加工产品，则获取该产品的加工状态
        if($product_id_arr){
            $product_status_arr = $ProducingProgressSummery->productTwoCateDoneInfo($businessId,$userId,$logistic_delivery_date,$logistic_truck_No,$product_id_arr);
        }

        if($product_no_id_arr){
            $product_status_arr = $this->getNoneProcessedData($businessId,$userId,$logistic_delivery_date,$logistic_truck_No,$product_no_id_arr);
        }
//        halt($product_status_arr);
        $list = [];
        foreach($data as &$v){
            //查询该产品的加工状态
            $v['status'] = isset($product_status_arr[$v['product_id']]) ? $product_status_arr[$v['product_id']]['status'] : 0;
            $v['operator_user'] = isset($product_status_arr[$v['product_id']]['operator_user']) ? $product_status_arr[$v['product_id']]['operator_user'] : [];
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
                    'proucing_item' => $v['proucing_item'],
                    'sum_quantities' => $v['customer_buying_quantity'],
                    'status' => $v['status'],
                    'operator_user' => $v['operator_user'],
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

    /**
     * 获取非加工明细产品数据
     */
    public function getNoneProcessedData($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$product_id_arr)
    {
        $where = [
            ['o.business_userId','=',$businessId],
            ['o.logistic_delivery_date','=',$logistic_delivery_date],
            ['wcc.restaurant_menu_id','in',$product_id_arr],
            ['wcc.customer_buying_quantity','>',0],
        ];
        if($logistic_truck_No != ''){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        $data = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.operator_user_id,wcc.is_producing_done,o.logistic_truck_No,u.name,u.nickname,u.displayName')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('user u','u.id = wcc.operator_user_id')
            ->where($where)
            ->select()->toArray();
        $list = [];//按产品id合并数组
        foreach($data as $k=>$v){
            $list[$v['product_id']]['product'][] = $v;
        }
        //通过产品的信息获取该产品当前的状态
        $done = [];
        foreach ($list as $k=>$v){
            //获取该产品的状态
            $is_producing_done_arr = array_column($v['product'],'is_producing_done');
            $operator_user_id = 0;//操作人员id
            if(in_array(0,$is_producing_done_arr)){
                foreach ($v['product'] as $vv){
                    if($vv['is_producing_done'] == 0){
                        $operator_user_id = $vv['operator_user_id'];
                        break;
                    }
                }
                $done[$k] = [
                  'operator_user_id' => $operator_user_id,
                  'isDone' => 0,
                ];
            }else{
                $done[$k] = [
                  'operator_user_id' => $v['product'][0]['operator_user_id'],
                  'isDone' => 1
                ];
            }
            if($done[$k]['isDone'] == 0){
                if($done[$k]['operator_user_id'] == 0){
                    $status = 0;
                }else{
                    $status = $done[$k]['operator_user_id']==$userId ? 1 : 2;
                }
            }else{
                $status = 3;
            }
            $done[$k]['status'] = $status;
            $operator_user = [];
            foreach ($v['product'] as $vv){
                if($vv['operator_user_id'] > 0){
                    if(!isset($operator_user[$vv['operator_user_id']])){
                        $operator_user[$vv['operator_user_id']] = [
                            'operator_user_id' => $vv['operator_user_id'],
                            'user_name' => $vv['nickname'] ?: $vv['name']
                        ];
                    }
                }
            }
            $done[$k]['operator_user'] = array_values($operator_user);
        }
        return $done;
    }

    /**
     * 更新非加工明细产品数据
     */
    /**
     * @param $businessId
     * @param $logistic_delivery_date
     * @param string $logistic_truck_No
     * @param $product_id
     * @param $update_data
     * @param $type 1-锁定时更新操作员 2-解锁 3-备货完成 4-返回重新操作
     * @return mixed
     */
    public function updateNoneProcessedData($businessId,$logistic_delivery_date,$logistic_truck_No='',$product_id,$operator_user_id,$type)
    {
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.logistic_delivery_date', '=', $logistic_delivery_date],
            ['wcc.restaurant_menu_id', '=', $product_id],
            ['wcc.customer_buying_quantity', '>', 0],
        ];
        switch($type){
            case 1://锁定时，需要将所有该商品未锁定的，锁定为当前操作员，和司机没有关系，为了防止切换司机时，部分完成，剩余未锁定的也需要改为当前操作员
                $where[] = ['wcc.is_producing_done', '=', 0];
                $update_data = ['wcc.operator_user_id'=>$operator_user_id];
                break;
            case 2:
                $where[] = ['wcc.is_producing_done', '=', 0];
                $update_data = ['wcc.operator_user_id'=>0];
                break;
            case 3:
                if ($logistic_truck_No != '') {
                    $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
                }
                $update_data = ['wcc.is_producing_done'=>1];
                break;
            case 4:
                if ($logistic_truck_No != '') {
                    $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
                }
                $update_data = [
                    'wcc.operator_user_id'=>$operator_user_id,
                    'wcc.is_producing_done'=>0
                ];
                break;
        }
        $res = WjCustomerCoupon::alias('wcc')
            ->leftJoin('order o', 'o.orderId = wcc.order_id')
            ->where($where)
            ->update($update_data);
        return $res;
    }
}
