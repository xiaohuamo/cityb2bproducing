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
        $map = 'status=1 or accountPay=1';
        $date_arr = Db::name('order')->where([
            ['business_userId', '=', $businessId],
            ['coupon_status', '=', 'c01'],
            ['logistic_delivery_date','>',time()-3600*24*7]
        ])->where($map)->field("logistic_delivery_date,FROM_UNIXTIME(logistic_delivery_date,'%Y-%m-%d') date,2 as is_default")->group('logistic_delivery_date')->order('logistic_delivery_date asc')->select()->toArray();
        //获取默认显示日期,距离今天最近的日期，将日期分为3组，今天之前，今天，今天之后距离今天最近的日期的key值
        $today_time = strtotime(date('Y-m-d',time()));
        $today_before_k = $today_k = $today_after_k = [];
        foreach($date_arr as $k=>$v){
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'],$today_time);
        }
        foreach($date_arr as $k=>$v){
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
        if($today_k){
            $date_arr[$today_k]['is_default'] = 1;
            $default = $date_arr[$today_k];
            $default_k = $today_k;
        }elseif($today_after_k){
            $date_arr[$today_after_k]['is_default'] = 1;
            $default = $date_arr[$today_after_k];
            $default_k = $today_after_k;
        }else{
            $date_arr[$today_before_k]['is_default'] = 1;
            $default = $date_arr[$today_before_k];
            $default_k = $today_before_k;
        }
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
            ['coupon_status', '=', 'c01']
        ];
        if($logistic_delivery_date){
            $where[] = ['logistic_delivery_date','=',$logistic_delivery_date];
        }
        $logistic_driver_no_arr = Db::name('order')->where($where)->where($map)->group('logistic_truck_No')->column('logistic_truck_No');
        //获取对应的司机信息
        $drivers = [];
        if($logistic_driver_no_arr){
            $logistic_driver_no_arr = array_filter($logistic_driver_no_arr);
            $drivers = Truck::alias('t')
                ->field('t.id logistic_driver_no,t.truck_name,t.plate_number,t.current_driver,u.name,u.nickname')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([['t.id','in',$logistic_driver_no_arr]])
                ->select();
        }
        return $drivers;
    }

    /**
     * 获取订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_driver_no 配送司机id
     * @return array
     */
    public function getOrderCount($businessId,$logistic_delivery_date='',$logistic_driver_no='')
    {
        $map = 'status=1 or accountPay=1';
        $where = [
            ['business_userId', '=', $businessId],
            ['coupon_status', '=', 'c01']
        ];
        if($logistic_delivery_date){
            $where[] = ['logistic_delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_driver_no){
            $where[] = ['logistic_driver_no','=',$logistic_driver_no];
        }
        //获取订单总数
        $order_count = Db::name('order')->where($where)->count();
        //获取已加工的订单总数
        $order_done_count = Db::name('order')->where($where)->where(['is_producing_done' => 1])->count();
        return [
//            'order' => $order,
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取加工订单
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_driver_no 配送司机id
     * @return array
     */
    public function getOrderList($businessId,$logistic_delivery_date='',$logistic_driver_no='',$product_id='',$guige1_id='')
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01']
        ];
        if ($logistic_delivery_date) {
            $where[] = ['o.logistic_delivery_date', '=', $logistic_delivery_date];
        }
        if ($logistic_driver_no) {
            $where[] = ['o.logistic_driver_no', '=', $logistic_driver_no];
        }
        //获取订单数据
        $order = Db::name('order')->field('id,orderId,logistic_truck_No,is_producing_done')->where($where)->where($map)->order('is_producing_done asc')->select()->toArray();
        return $order;
    }

    /**
     * 将订单商品加入生产流程表队列
     * @param $businessId 供应商id
     * @return array
     */
    public function addOrderGoodsToProgress($businessId,$logistic_delivery_date='')
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
