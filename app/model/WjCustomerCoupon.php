<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use app\product\service\BoxNumber;
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
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,wcc.print_label_sorts,wcc.current_box_sort_id,wcc.voucher_deal_amount,o.id o_id,o.business_userId,o.logistic_truck_No,o.logistic_delivery_date,o.is_producing_done order_is_producing_done,o.boxesNumber,o.boxesNumberSortId,o.edit_boxesNumber,o.money_new,wcc.dispatching_is_producing_done,o.dispatching_is_producing_done order_dispatching_is_producing_done,wcc.dispatching_item_operator_user_id,rm.restaurant_category_id cate_id,rm.proucing_item,wcc.assign_stock,wcc.operator_user_id')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where([
                ['wcc.id','=',$id],
                ['o.business_userId','=',$businessId]
            ])->find();
        return $wcc_info;
    }

    /**
     * 获取产品或产品规格数据
     * @param $businessId
     * @param $userId
     * @param string $logistic_delivery_date
     * @param string $logistic_truck_No
     * @param int $product_id
     * @param int $guige1_id
     */
    public function getWccProductList($businessId,$userId,$logistic_delivery_date='',$logistic_truck_No='',$product_id=0,$guige1_id=0)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        if (!empty($logistic_delivery_date)) {
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if ($logistic_truck_No !== '') {
            $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
        }
        if ($product_id>0) {
            $where[] = ['wcc.restaurant_menu_id', '=', $product_id];
        }
        if ($guige1_id>0) {
            $where[] = ['wcc.guige1_id', '=', $guige1_id];
        }
        $data = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done,wcc.assign_stock,o.logistic_truck_No,rm.restaurant_category_id cate_id,rm.proucing_item')
            ->leftJoin('order o', 'o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->where($map)
            ->select()->toArray();
        return $data;
    }

    /**
     * 判断某个订单的加工产品是否全部加工完毕
     * @param $type 1-加工端获取订单产品全部加工完成 2-配货端判断产品对应的订单是否全部拣货完成
     */
    public function getWccOrderDone($order_id='',$business_userId='',$logistic_delivery_date='',$logistic_truck_No='',$product_id='',$cate_id=0,$type=1)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['wcc.is_producing_done', '=', 0],
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        if($type == 1){
            $where[] = ['wcc.is_producing_done', '=', 0];
            $where[] = ['rm.proucing_item', '=', 1];
        }
        if($type == 2){
            $where[] = ['wcc.dispatching_is_producing_done', '=', 0];
        }
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
        if($cate_id>0){
            $where[] = ['rm.restaurant_category_id', '=', $cate_id];
        }
        $count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->where($where)
            ->where($map)
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
    public function getWccList($businessId,$userId,$logistic_delivery_date='',$logistic_truck_No='',$category_id='',$proucing_item='')
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
        if(!empty($proucing_item)){
            $where[] = ['rm.proucing_item','=',$proucing_item];
        }
        $data = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.assign_stock,wcc.is_producing_done,rm.menu_en_name,rm.unit_en,rm.menu_id,rm.proucing_item,rmo.menu_en_name guige_name,rc.id cate_id,rc.category_en_name,pis.stock_qty')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
            ->leftJoin('restaurant_category rc','rm.restaurant_category_id = rc.id')
            ->leftJoin('producing_item_stock pis',"pis.item_id = wcc.restaurant_menu_id and pis.spec_id = wcc.guige1_id and pis.factory_id = $businessId")
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
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
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
            //加工产品的完成数量
            $v['finish_quantities'] = isset($product_status_arr[$v['product_id']]) ? $product_status_arr[$v['product_id']]['finish_quantities'] : 0;
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
                    'sum_quantities' => floatval($v['customer_buying_quantity']),
                    'finish_quantities' => floatval($v['finish_quantities']),
                    'status' => $v['status'],
                    'operator_user' => $v['operator_user'],
                    'assign' => -1,
                    'left_stock' => -1,
                    'producing_quantity' => -1,
                ];
                if(!($v['guige1_id'] > 0)){
                    //如果是生产产品，可以查看已分配库存，库存剩余，需要生产量
                    if($v['proucing_item'] == 1){
                        $assign_stock = floatval($v['assign_stock']==1?$v['new_customer_buying_quantity']:0);
                        $left_stock = floatval($v['stock_qty']?:0);
                        $producing_quantity = $v['is_producing_done'] == 1?0:floatval(bcsub((string)$v['new_customer_buying_quantity'],(string)$assign_stock,2));
                        $list[$v['cate_id']]['product'][$v['product_id']]['assign'] = $assign_stock;
                        $list[$v['cate_id']]['product'][$v['product_id']]['left_stock'] = $left_stock;
                        $list[$v['cate_id']]['product'][$v['product_id']]['producing_quantity'] = $producing_quantity;
                    }
                }
            } else {
                $list[$v['cate_id']]['product'][$v['product_id']]['sum_quantities'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['sum_quantities'],(string)$v['customer_buying_quantity'],2));
                if(!($v['guige1_id'] > 0)){
                    //如果是生产产品，可以查看已分配库存，库存剩余，需要生产量
                    if($v['proucing_item'] == 1){
                        $assign_stock = floatval($v['assign_stock']==1?$v['new_customer_buying_quantity']:0);
                        $producing_quantity = $v['is_producing_done'] == 1?0:floatval(bcsub((string)$v['new_customer_buying_quantity'],(string)$assign_stock,2));
                        $list[$v['cate_id']]['product'][$v['product_id']]['assign'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['assign'],(string)$assign_stock,2));
                        $list[$v['cate_id']]['product'][$v['product_id']]['producing_quantity'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['producing_quantity'],(string)$producing_quantity,2));
                    }
                }
            }
            if($v['guige1_id'] > 0){
                $list[$v['cate_id']]['product'][$v['product_id']]['is_has_two_cate'] = 1;
                $assign_stock = floatval($v['assign_stock']==1?$v['new_customer_buying_quantity']:0);//分配库存的数量
                $left_stock = floatval($v['stock_qty']?:0);//剩余库存的数量
                $producing_quantity =  $v['is_producing_done'] == 1?0:floatval(bcsub((string)$v['new_customer_buying_quantity'],(string)$assign_stock,2));//需要加工的数量
                if(!isset($list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']])) {
                    $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']] = [
                        'guige1_id' => $v['guige1_id'],
                        'guige_name' => $v['guige_name'],
                        'customer_buying_quantity' => floatval($v['customer_buying_quantity']),
                        'assign' => -1,
                        'left_stock' => -1,
                        'producing_quantity' => -1,
                    ];
                    //如果是生产产品，可以查看已分配库存，库存剩余，需要生产量
                    if($v['proucing_item'] == 1){
                        $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['assign'] = $assign_stock;
                        $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['left_stock'] = $left_stock;
                        $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['producing_quantity'] = $producing_quantity;
                    }
                } else {
                    $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['customer_buying_quantity'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['customer_buying_quantity'],(string)$v['customer_buying_quantity'],2));
                    //如果是生产产品，可以查看已分配库存，库存剩余，需要生产量
                    if($v['proucing_item'] == 1){
                        $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['assign'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['assign'],(string)$assign_stock,2));
                        $list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['producing_quantity'] = floatval(bcadd((string)$list[$v['cate_id']]['product'][$v['product_id']]['two_cate'][$v['guige1_id']]['producing_quantity'],(string)$producing_quantity,2));
                    }
                }
            } else {
                $list[$v['cate_id']]['product'][$v['product_id']]['is_has_two_cate'] = 2;
                $list[$v['cate_id']]['product'][$v['product_id']]['customer_buying_quantity'] = floatval($v['customer_buying_quantity']);
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
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.operator_user_id,wcc.is_producing_done,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,o.logistic_truck_No,u.name,u.nickname,u.displayName')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('user u','u.id = wcc.operator_user_id')
            ->where($where)
            ->select()->toArray();
        $list = [];//按产品id合并数组
        foreach($data as $k=>$v){
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            $list[$v['product_id']]['product'][] = $v;
            if(isset($list[$v['product_id']]['finish_quantities'])){
                $list[$v['product_id']]['finish_quantities']=floatval(($list[$v['product_id']]['finish_quantities']*100+$v['new_customer_buying_quantity']*100)/100);
            }else{
                $list[$v['product_id']]['finish_quantities']=floatval($v['customer_buying_quantity']);
            }
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
            $list[$k] = array_merge($list[$k],$done[$k]);
        }
        return $list;
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

    /**
     * @param $businessId
     * @param $user_id
     * @param string $logistic_delivery_date
     * @param string $logistic_truck_No
     */
    public function getPickingItemCategory($businessId,$user_id,$logistic_delivery_date='',$logistic_truck_No='',$cate_sort=0)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['wcc.customer_buying_quantity','>',0],
        ];
        if (!empty($logistic_delivery_date)) {
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if (!empty($logistic_truck_No)) {
            $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
        }
        switch ($cate_sort) {
            case 0:
                $order_by = 'wcc.dispatching_is_producing_done asc,rc.category_sort_id asc';
                break;
            case 1:
                $order_by = 'wcc.dispatching_is_producing_done desc,rc.category_sort_id asc';
                break;
            case 2:
                $order_by = 'rc.category_sort_id asc';
                break;
            default:
                $order_by = 'rc.category_sort_id asc';
        }
        $data = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('rc.id,rc.category_en_name,wcc.dispatching_operator_user_id,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done')
            ->leftJoin('order o', 'o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm', 'wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_category rc', 'rm.restaurant_category_id = rc.id')
            ->where($map)
            ->where($where)
            ->order($order_by)
            ->select()->toArray();
        $cate_id_arr = array_unique(array_column($data,'id'));
        if($cate_id_arr){
            $cate_id_str = implode(',',$cate_id_arr);
            $cate_where = "restaurant_id=$businessId and parent_category_id in ($cate_id_str) and length(category_en_name)>0 and isdeleted=0 and isHide=0";
            $two_cate_data = Db::name('restaurant_category')->field('id,parent_category_id,category_en_name')->where($cate_where)->select()->toArray();
            $two_cate = [];
            foreach ($two_cate_data as $k=>$v){
                $two_cate[$v['parent_category_id']][] = $v;
            }
        }
        $category = [];
        $ProducingProgressSummery = new ProducingProgressSummery();
        foreach ($data as $k=>$v){
            if(!isset($category[$v['id']])){
                $category[$v['id']] = [
                    'id' => $v['id'],
                    'category_en_name' => $v['category_en_name'],
                    'two_cate' => $two_cate[$v['id']] ?? [],
                    'is_has_two_cate' => 1,
                ];
            }
            $category[$v['id']]['two_cate_done_info'][] = [
                'operator_user_id' => $v['dispatching_item_operator_user_id'],
                'isDone' => $v['dispatching_is_producing_done']
            ];
        }
        foreach ($category as &$v){
            //获取状态
            $v['status'] = $ProducingProgressSummery->getProcessStatus($v,$user_id,3,$v['two_cate_done_info']);
            unset($v['two_cate_done_info']);
        }
        return array_values($category);
    }

    public function getOneCateProductList($businessId,$user_id,$logistic_delivery_date='',$logistic_truck_No='',$one_cate_id=0,$two_cate_id=0)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['wcc.customer_buying_quantity','>',0],
        ];
        if (!empty($logistic_delivery_date)) {
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if (!empty($logistic_truck_No)) {
            $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
        }
        if ($one_cate_id>0) {
            $where[] = ['rm.restaurant_category_id', '=', $one_cate_id];
        }
        if ($two_cate_id>0) {
            $where[] = ['rm.sub_category_id', '=', $two_cate_id];
        }
        $data = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.customer_buying_quantity,o.boxesNumber,rm.menu_en_name,rm.unit_en,rm.menu_id,rmo.menu_en_name guige_name,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done,wcc.assign_stock,o.dispatching_is_producing_done order_dispatching_is_producing_done,rm.restaurant_category_id,rm.sub_category_id,rm.unitQtyPerBox,rm.proucing_item')
            ->leftJoin('order o', 'o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm', 'wcc.restaurant_menu_id = rm.id')
            ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
            ->where($map)
            ->where($where)
            ->order('rm.menu_id asc,rmo.menu_id asc,wcc.id asc')
            ->select()->toArray();
        $product = [];
        $ProducingProgressSummery = new ProducingProgressSummery();
        foreach ($data as $k=>$v){
            $boxs = ceil($v['customer_buying_quantity']/$v['unitQtyPerBox']);
            if(!isset($product[$v['product_id']])){
                $product[$v['product_id']] = [
                    'product_id' => $v['product_id'],
                    'cate_id' => $v['restaurant_category_id'],
                    'sub_cate_id' => $v['sub_category_id'],
                    'menu_id' => $v['menu_id'],
                    'menu_en_name' => $v['menu_en_name'],
                    'unit_en' => $v['unit_en'],
                    'box_number' => $boxs,
//                    'finish_box_number' => 0,
                    'sum_quantities' => floatval($v['customer_buying_quantity']),
                    'finish_quantities' => $v['dispatching_is_producing_done']==1?floatval($v['customer_buying_quantity']):0,
                    'assign_stock' => $v['assign_stock'],
                    'proucing_item' => $v['proucing_item'],
                ];
            }else{
                $product[$v['product_id']]['box_number'] += $boxs;
                $product[$v['product_id']]['sum_quantities'] = floatval(bcadd((string)$product[$v['product_id']]['sum_quantities'],(string)$v['customer_buying_quantity'],2));
                if($v['dispatching_is_producing_done'] == 1){
                    $product[$v['product_id']]['finish_quantities'] = floatval(bcadd((string)$product[$v['product_id']]['finish_quantities'],(string)$v['customer_buying_quantity'],2));
                }
            }
            $product[$v['product_id']]['done_info'][] = [
                'operator_user_id' => $v['dispatching_item_operator_user_id'],
                'isDone' => $v['dispatching_is_producing_done']
            ];
            if($v['guige1_id'] > 0){
                $product[$v['product_id']]['is_has_two_cate'] = 1;
                if(!isset($product[$v['product_id']]['two_cate'][$v['guige1_id']])){
                    $product[$v['product_id']]['two_cate'][$v['guige1_id']] = [
                        'product_id' => $v['product_id'],
                        'guige1_id' => $v['guige1_id'],
                        'unit_en' => $v['unit_en'],
                        'guige_name' => $v['guige_name'],
                        'box_number' => $boxs,
                        'sum_quantities' => floatval($v['customer_buying_quantity']),
                        'finish_quantities' =>  $v['dispatching_is_producing_done']==1?floatval($v['customer_buying_quantity']):0,
                        'assign_stock' => $v['assign_stock'],
                        'proucing_item' => $v['proucing_item'],
                    ];
                }else{
                    $product[$v['product_id']]['two_cate'][$v['guige1_id']]['box_number'] += $boxs;
                    $product[$v['product_id']]['two_cate'][$v['guige1_id']]['sum_quantities'] = floatval(bcadd((string)$product[$v['product_id']]['two_cate'][$v['guige1_id']]['sum_quantities'],(string)$v['customer_buying_quantity'],2));
                    if($v['dispatching_is_producing_done'] == 1){
                        $product[$v['product_id']]['two_cate'][$v['guige1_id']]['finish_quantities'] = floatval(bcadd((string)$product[$v['product_id']]['two_cate'][$v['guige1_id']]['finish_quantities'],(string)$v['customer_buying_quantity'],2));
                    }
                }
                $product[$v['product_id']]['two_cate'][$v['guige1_id']]['done_info'][] = [
                    'operator_user_id' => $v['dispatching_item_operator_user_id'],
                    'isDone' => $v['dispatching_is_producing_done']
                ];
            }else{
                $product[$v['product_id']]['two_cate'] = [];
            }
        }
        foreach ($product as &$v){
            //获取状态
            $v['status'] = $ProducingProgressSummery->getProcessStatus($v,$user_id,3,$v['done_info']);
            unset($v['done_info']);
            if(isset($v['two_cate'])){
                foreach($v['two_cate'] as $twk=>$twv){
                    $v['two_cate'][$twk]['status'] = $ProducingProgressSummery->getProcessStatus($v,$user_id,3,$v['two_cate'][$twk]['done_info']);
                    unset($v['two_cate'][$twk]['done_info']);
                }
                $v['two_cate'] = array_values($v['two_cate']);
            }
        }
        return array_values($product);
    }

    /**
     * 获取产品明细信息
     */
    public function getPickProductData($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$product_id,$guige1_id=0)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId','=',$businessId],
            ['o.logistic_delivery_date','=',$logistic_delivery_date],
            ['wcc.restaurant_menu_id','=',$product_id],
            ['wcc.customer_buying_quantity','>',0],
        ];
        if($logistic_truck_No !== ''){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        if($guige1_id > 0){
            $where[] = ['wcc.guige1_id','=',$guige1_id];
        }
        $data = WjCustomerCoupon::alias('wcc')
            ->field('wcc.id,wcc.order_id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done,wcc.assign_stock,rm.proucing_item')
            ->leftJoin('order o','o.orderId = wcc.order_id')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->where($map)
            ->select()->toArray();
        $done = $this->getProductGuigeStatus($data,$userId);
        return $done;
    }

    /**
     * 获取产品/产品规格的状态
     * @param $data
     * @param $userId
     * @return array
     */
    public function getProductGuigeStatus($data,$userId)
    {
        //通过产品的信息获取该产品当前的状态
        $done = [];
        //获取该产品的状态
        //存储当前产品的所有状态
        $is_producing_done_arr = array_column($data,'dispatching_is_producing_done');
        $operator_user_id = 0;//操作人员id
        if(in_array(0,$is_producing_done_arr)){
            foreach ($data as $v){
                if($v['dispatching_is_producing_done'] == 0){
                    $operator_user_id = $v['dispatching_item_operator_user_id'];
                    break;
                }
            }
            $done = [
                'operator_user_id' => $operator_user_id,
                'isDone' => 0,
            ];
        }else{
            $done = [
                'operator_user_id' => $data[0]['dispatching_item_operator_user_id'],
                'isDone' => 1
            ];
        }
        if($done['isDone'] == 0){
            if($done['operator_user_id'] == 0){
                $status = 0;
            }else{
                $status = $done['operator_user_id']==$userId ? 1 : 2;
            }
        }else{
            $status = 3;
        }
        $done['status'] = $status;
        return $done;
    }

    /**
     * 更新配货端数据-根据产品拣货状态
     * @param $businessId
     * @param $logistic_delivery_date
     * @param $product_id
     * @param $update_data
     * @param $type 1-锁定时更新操作员 2-解锁 3-拣货完成 4-返回重新操作
     * @return mixed
     */
    public function updatePickProductItemProcessedData($businessId,$logistic_delivery_date,$logistic_truck_No='',$product_id,$guige1_id=0,$operator_user_id,$type)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.logistic_delivery_date', '=', $logistic_delivery_date],
            ['wcc.restaurant_menu_id', '=', $product_id],
            ['wcc.customer_buying_quantity', '>', 0],
        ];
        if($guige1_id > 0){
            $where[] = ['wcc.guige1_id','=',$guige1_id];
        }
        switch($type){
            case 1://锁定时，需要将所有该商品未锁定的，锁定为当前操作员，和司机没有关系，为了防止切换司机时，部分完成，剩余未锁定的也需要改为当前操作员
                $where[] = ['wcc.dispatching_is_producing_done', '<>', 1];
                $update_data = ['wcc.dispatching_item_operator_user_id'=>$operator_user_id];
                break;
            case 2:
                $where[] = ['wcc.dispatching_is_producing_done', '<>', 1];
                $update_data = ['wcc.dispatching_item_operator_user_id'=>0];
                break;
            case 3:
                if ($logistic_truck_No !== '') {
                    $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
                }
                $update_data = ['wcc.dispatching_is_producing_done'=>1];
                break;
            case 4:
                if ($logistic_truck_No != '') {
                    $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
                }
                $update_data = [
                    'wcc.dispatching_item_operator_user_id'=>$operator_user_id,
                    'wcc.dispatching_is_producing_done'=>0
                ];
                break;
        }
        $res = WjCustomerCoupon::alias('wcc')
            ->leftJoin('order o', 'o.orderId = wcc.order_id')
            ->where($where)
            ->update($update_data);
        return $res;
    }

    /**
     * 判断是否需要总箱数,一旦起始标签数<=1,则修改数量时会判断是否修改订单明细对应的箱数和总箱数
     * @param $wcc_info 订单明细
     */
    public function updateOrderItemBox($wcc_info)
    {
        //1.判断是否需要总箱数,一旦起始标签数<=1,则修改数量时会判断是否修改订单明细对应的箱数和总箱数
        if($wcc_info['boxesNumberSortId'] <= 1){
            $BoxNumber = new BoxNumber();
            $orderBoxNumber = $BoxNumber->getOrderBoxes($wcc_info['order_id'],$wcc_info['business_userId']);
            //1-1.判断总箱数是否变化，变化则更新总箱数
            if($orderBoxNumber['orderboxnumber'] != $wcc_info['boxesNumber']){
                Order::getUpdate(['orderId'=>$wcc_info['order_id']],['boxesNumber'=>$orderBoxNumber['orderboxnumber']]);
            }
            //1-2。更新所有订单明细的箱数
            foreach($orderBoxNumber['order'] as $k=>$v){
                WjCustomerCoupon::getUpdate(['id' => $v['id']],[
                    'boxnumber'=>$v['boxnumber'],
                    'splicingboxnumber' => $v['splicingboxnumber'],
                    'mix_box_group' => 0]);
            }
            //1-3。批量新订单明细的箱数以及分组情况
            $update_data = [];
            foreach ($orderBoxNumber['splicing_arr'] as $k=>$v){
                foreach ($v as $bk=>$bv) {
                    $update_data[] = [
                        'id' => $bv['id'],
                        'boxnumber' => $bv['boxnumber'],
                        'splicingboxnumber' => $bv['splicingboxnumber'],
                        'mix_box_group' => $k+1
                    ];
                }
            }
            if(!empty($update_data)){
                $this->saveAll($update_data);
            }
        }
    }
}
