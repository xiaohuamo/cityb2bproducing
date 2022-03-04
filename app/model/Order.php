<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\Model;
use think\facade\Db;
use think\facade\Queue;

/**
 * @mixin \think\Model
 */
class Order extends Model
{
    use modelTrait;

    /**
     * 获取cc_order可以配送的日期
     */
    public function getDeliveryDate($businessId)
    {
        $date_arr = Db::name('producing_progress_summery')->where([
            ['business_userId', '=', $businessId],
            ['delivery_date','>',time()-3600*24*7],
            ['isdeleted','=',0]
        ])->field("delivery_date logistic_delivery_date,FROM_UNIXTIME(delivery_date,'%Y-%m-%d') date,2 as is_default")->group('delivery_date')->order('delivery_date asc')->select()->toArray();
        //获取默认显示日期,距离今天最近的日期，将日期分为3组，今天之前，今天，今天之后距离今天最近的日期的key值
        $today_time = strtotime(date('Y-m-d',time()));
        foreach($date_arr as $k=>$v) {
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'], $today_time);
        }
        $today_before_k = $today_k = $today_after_k = '';
        foreach($date_arr as $k=>$v){
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'],$today_time);
            if($v['logistic_delivery_date']-$today_time <= 0){
                $today_before_k = $k;
            }
            if($v['logistic_delivery_date']-$today_time == 0){
                $today_k = $k;
            }
            if($v['logistic_delivery_date']-$today_time > 0){
                $today_after_k = $k;
                break;
            }
        }
        if($today_k!==''){
            $date_arr[$today_k]['is_default'] = 1;
            $default = $date_arr[$today_k];
            $default_k = $today_k;
            return ['list' => $date_arr,'default' => $default,'default_k' => $default_k];
        }
        if($today_after_k!=='') {
            $date_arr[$today_after_k]['is_default'] = 1;
            $default = $date_arr[$today_after_k];
            $default_k = $today_after_k;
            return ['list' => $date_arr,'default' => $default,'default_k' => $default_k];
        }
        if($today_before_k!=='') {
            $date_arr[$today_before_k]['is_default'] = 1;
            $default = $date_arr[$today_before_k];
            $default_k = $today_before_k;
            return ['list' => $date_arr,'default' => $default,'default_k' => $default_k];
        }
        $default = [];
        $default_k = 0;
        return ['list' => $date_arr,'default' => $default,'default_k' => $default_k];
    }

    /**
     * 获取司机信息
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     */
    public function getDrivers($businessId,$logistic_delivery_date='')
    {
        $map = 'status=1 or accountPay=1';
        $where = [
            ['business_userId', '=', $businessId],
            ['coupon_status', '=', 'c01'],
            ['logistic_truck_No', '>', 0]
        ];
        if($logistic_delivery_date){
            $where[] = ['logistic_delivery_date','=',$logistic_delivery_date];
        }
        $logistic_truck_No_arr = Db::name('order')->where($where)->where($map)->group('logistic_truck_No')->column('logistic_truck_No');
        //获取对应的司机信息
        $drivers = [];
        if($logistic_truck_No_arr){
            $logistic_truck_No_arr = array_filter($logistic_truck_No_arr);
            $drivers = Truck::alias('t')
                ->field('t.id logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([['t.id','in',$logistic_truck_No_arr]])
                ->select();
            foreach ($drivers as &$v){
                $v['name'] = $v['contactPersonFirstname'].' '.$v['contactPersonLastname'];
            }
        }
        return $drivers;
    }

    /**
     * 获取订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getOrderCount($businessId,$logistic_delivery_date='',$logistic_truck_No='')
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1]
        ];
        if($logistic_delivery_date){
            $where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_truck_No){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        //获取需要加工的订单总数
        $order_count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->where($map)
            ->group('wcc.order_id')
            ->count();
        //获取已加工的订单总数
        $where[] = ['o.is_producing_done','=',1];
        $order_done_count = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->where($map)
            ->group('wcc.order_id')
            ->count();
        return [
//            'order' => $order,
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取产品加工订单
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getProductOrderList($businessId,$user_id,$logistic_delivery_date='',$logistic_truck_No='',$product_id='',$guige1_id='',$wcc_sort=0,$wcc_sort_type=1)
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1]
        ];
        if ($logistic_delivery_date) {
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if ($logistic_truck_No) {
            $where[] = ['o.logistic_truck_No', '=', $logistic_truck_No];
        }
        if ($product_id > 0) {
            $where[] = ['wcc.restaurant_menu_id','=',$product_id];
        }
        if ($guige1_id > 0) {
            $where[] = ['wcc.guige1_id','=',$guige1_id];
        }
        switch ($wcc_sort){
            case 1:
                if($wcc_sort_type == 1){
                    $order_by = 'is_producing_done asc,id asc';
                }else{
                    $order_by = 'is_producing_done desc,id asc';
                }
                break;
            case 2:
                if($wcc_sort_type == 1) {
                    $order_by = 'is_producing_done desc,id asc';
                }else{
                    $order_by = 'is_producing_done asc,id asc';
                }
                break;
            default:
                if($wcc_sort_type == 1) {
                    $order_by = 'id asc,is_producing_done asc';
                }else{
                    $order_by = 'id desc,is_producing_done asc';
                }
        }
        //获取加工明细单数据
        $order = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,o.logistic_sequence_No,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,1 as num1,pps.operator_user_id,pps.isDone')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->leftJoin('producing_progress_summery pps',"pps.delivery_date = o.logistic_delivery_date and pps.business_userId=$businessId and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id")
            ->where($where)
            ->where($map)
            ->order($order_by)
            ->select()->toArray();
        foreach($order as &$v){
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            //判断当前加工明细是否被锁定
            $v['is_lock'] = 0;
            $v['lock_type'] = 0;
            if($v['operator_user_id'] > 0){
                if($v['isDone'] == 0){
                    $v['is_lock'] = 1;//是否被锁定，1锁定 2未锁定
                    $v['lock_type'] = $user_id == $v['operator_user_id']?1:2;//1-被自己锁定 2-被他人锁定
                }
            }
        }
        return $order;
    }

    /**
     * 将订单商品加入生产流程表队列
     * @param $businessId 供应商id
     * @return array
     */
    public function addOrderGoodsToProgress($businessId,$logistic_delivery_date)
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1]
        ];
        if($logistic_delivery_date){
            $where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
        }
        //查找订单中的所有商品的汇总
        $order_goods = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('o.business_userId,o.logistic_delivery_date delivery_date,wcc.restaurant_menu_id product_id,wcc.guige1_id,sum(wcc.customer_buying_quantity) AS sum_quantities')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->where($where)
            ->where($map)
            ->group('wcc.restaurant_menu_id,wcc.guige1_id')
            ->select();
        //查询当天已加入汇总表的商品
        $pps_list = ProducingProgressSummery::getAll(['business_userId'=>$businessId,'delivery_date'=>$logistic_delivery_date,'isdeleted'=>0]);
        //判断是否汇总信息是否有变动
        $pps_has_list = [];//比对汇总表中仍然存在的信息
        foreach($pps_list as $v){
            foreach($order_goods as $vv) {
                if($v['product_id'] == $vv['product_id'] && $v['guige1_id'] == $vv['guige1_id']){
                    $pps_has_list[] = $v;
                    break;
                }
            }
        }
        if(count($pps_list) > 0 && count($pps_has_list) != count($pps_list)){
            $pps_has_id_arr = array_column($pps_has_list,'id');
            $pps_id_arr = array_column($pps_list,'id');
            $result = array_diff($pps_id_arr,$pps_has_id_arr);
            ProducingProgressSummery::getUpdate([['id','in',$result]],['isdeleted'=>1]);
        }
        //将商品信息加入队列依次插入数据库
        foreach($order_goods as $k=>$v){
            $v['proucing_center_id'] = 0;
            $isPushed = Queue::push('app\job\Job1', $v, 'producingProgressSummary');
            // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
            if( $isPushed !== false ){
                echo date('Y-m-d H:i:s') . " a new Job is Pushed to the MQ"."<br>";
            }else{
                echo 'Oops, something went wrong.';
            }
        }
    }
}
