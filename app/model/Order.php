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
    public function getDeliveryDate($businessId,$logistic_delivery_date='')
    {
        $date_arr = Db::name('producing_progress_summery')->where([
            ['business_userId', '=', $businessId],
            ['delivery_date','>',time()-3600*24*7],
            ['isdeleted','=',0]
        ])->field("delivery_date logistic_delivery_date,FROM_UNIXTIME(delivery_date,'%Y-%m-%d') date,2 as is_default")->group('delivery_date')->order('delivery_date asc')->select()->toArray();
        //获取默认显示日期,距离今天最近的日期，将日期分为3组，今天之前，今天，今天之后距离今天最近的日期的key值
        $today_time = strtotime(date('Y-m-d',time()));
        $default = [];//默认显示日期数据
        $default_k = 0;//默认显示日期索引值
        foreach($date_arr as $k=>$v) {
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'], $today_time);
            if($v['logistic_delivery_date'] == $logistic_delivery_date){
                $date_arr[$k]['is_default'] = 1;
                $default = $date_arr[$k];
                $default_k = $k;
            }
        }
        //如果存储的日期存在，则默认显示存储日期；否则按原先规格显示
        if($default){
            return ['list' => $date_arr,'default' => $default,'default_k' => $default_k];
        }else{
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
            //判断当前供应商最近7天内是否有订单数据，如果有，则前端需要实时刷新数据，如果没有，则无需更新
            $map = 'status=1 or accountPay=1';
            $order_count = Db::name('order')->where([
                    ['business_userId', '=', $businessId],
                    ['coupon_status', '=', 'c01'],
                    ['logistic_delivery_date','>',time()-3600*24*7],
            ])->count();
            $is_has_data = $order_count>0 ? 1 : 2;
            return ['list' => $date_arr,'default' => $default,'default_k' => $default_k,'is_has_data' => $is_has_data];
        }
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
                ->field('t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([
                    ['t.business_id','=',$businessId],
                    ['t.truck_no','in',$logistic_truck_No_arr]
                ])
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
     * @param int $type 1-获取生产相关的订单数量 2-获取配货端-根据产品筛选的订单数量
     * @return array
     */
    public function getOrderCount($businessId,$logistic_delivery_date='',$logistic_truck_No='',$type=1)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
//            ['o.coupon_status', '=', 'c01'],
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        if($logistic_delivery_date){
            $where[] = ['o.logistic_delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_truck_No){
            $where[] = ['o.logistic_truck_No','=',$logistic_truck_No];
        }
        if($type == 1){
            $where[] = ['rm.proucing_item', '=', 1];
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
        if($type == 1){
            $where[] = ['o.is_producing_done','=',1];
        }else{
            $where[] = ['o.dispatching_is_producing_done','=',1];
        }
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
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
//            ['o.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1],
            ['wcc.customer_buying_quantity', '>', 0]
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
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.message,o.userId,o.orderId,o.first_name,o.last_name,o.address,o.phone,o.message_to_business,o.logistic_truck_No,o.logistic_sequence_No,o.logistic_stop_No,o.logistic_delivery_date,o.logistic_suppliers_info,o.logistic_suppliers_count,o.redeem_code,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,1 as num1,pps.operator_user_id,pps.isDone,rm.unit_en')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->leftJoin('producing_progress_summery pps',"pps.delivery_date = o.logistic_delivery_date and pps.business_userId=$businessId and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
            ->where($where)
            ->where($map)
            ->order($order_by)
            ->select()->toArray();
        //获取所有的司机信息
        $logistic_truck_No_arr = array_filter(array_unique(array_column($order,'logistic_truck_No')));
        $truck_data_arr = [];//存储司机的信息
        if($logistic_truck_No_arr){
            $truck_data_arr = Truck::alias('t')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([
                    ['t.business_id','=',$businessId],
                    ['t.truck_no','in',$logistic_truck_No_arr],
                ])
                ->column('t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname','t.truck_no');
            foreach ($truck_data_arr as &$v){
                $v['name'] = $v['contactPersonFirstname'].' '.$v['contactPersonLastname'];
            }
        }
        foreach($order as &$v){
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            $v['truck_info'] = $truck_data_arr[$v['logistic_truck_No']] ?? [];
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
     *  将订单商品加入生产流程表队列
     * @param $businessId
     * @param $logistic_delivery_date
     * @param $type 1自动更新 2新增商家数据更新
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addOrderGoodsToProgress($businessId,$logistic_delivery_date,$type)
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['rm.proucing_item', '=', 1],
            ['wcc.customer_buying_quantity','>',0],
            ['o.logistic_delivery_date','=',$logistic_delivery_date]
        ];
        //查找订单中的所有商品的汇总
        $order_goods = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('o.business_userId,o.logistic_delivery_date delivery_date,wcc.restaurant_menu_id product_id,wcc.guige1_id,sum(wcc.customer_buying_quantity) AS sum_quantities,pps.id pps_id,pps.sum_quantities pps_sum_quantities,pps.isDone')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('producing_progress_summery pps',"pps.delivery_date = o.logistic_delivery_date and pps.business_userId=$businessId and pps.product_id=wcc.restaurant_menu_id and pps.guige1_id=wcc.guige1_id and pps.isdeleted=0")
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
            if(empty($v['pps_id']) || $v['pps_sum_quantities'] != $v['sum_quantities']) {
                $isPushed = Queue::push('app\job\Job1', $v, 'producingProgressSummary');
                // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
                if ($type == 1) {
                    if ($isPushed !== false) {
                        echo date('Y-m-d H:i:s') . " a new Job is Pushed to the MQ" . "<br>";
                    } else {
                        echo 'Oops, something went wrong.';
                    }
                }
            }
        }
    }

    /**
     * 将司机调度信息加入调度流程表队列
     * @param $businessId
     * @param $logistic_delivery_date
     * @param $type 1自动更新 2新增商家数据更新
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addDispatchingToProgress($businessId,$logistic_delivery_date,$type)
    {
//        $map = '(o.status=1 or o.accountPay=1) and o.logistic_truck_No != 0 and o.logistic_truck_No is not null';
        $map = "o.status=1 or o.accountPay=1";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['wcc.customer_buying_quantity','>',0],
            ['o.logistic_delivery_date','=',$logistic_delivery_date],
        ];
        //查找订单中的所有商品的汇总
        $orders = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('o.business_userId business_id,o.logistic_delivery_date delivery_date,o.orderId,o.logistic_truck_No truck_no,count(wcc.order_id) AS sum_quantities,dps.id dps_id,dps.sum_quantities dps_sum_quantities,dps.isDone,dps.truck_no dps_truck_no')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('dispatching_progress_summery dps',"dps.delivery_date = o.logistic_delivery_date and dps.business_id=$businessId and dps.orderId=o.orderId and dps.isdeleted=0")
            ->where($where)
            ->where($map)
            ->group('wcc.order_id')
            ->order('o.logistic_truck_No')
            ->select()->toArray();
//        dump($orders);
        //查询当天已加入汇总表的调度信息
//        $dps_where = "business_id=$businessId and delivery_date=$logistic_delivery_date and truck_no != 0 and truck_no is not null and isdeleted=0";
        $dps_where = "business_id=$businessId and delivery_date=$logistic_delivery_date and isdeleted=0";
        $dps_list = DispatchingProgressSummery::getAll($dps_where);
        //判断是否汇总信息是否有变动
        $dps_has_list = [];//比对汇总表中仍然存在的信息
        foreach($dps_list as $v){
            foreach($orders as $vv) {
//                if($v['orderId'] == $vv['orderId'] && $v['truck_no'] == $vv['truck_no']){
                if($v['delivery_date'] == $vv['delivery_date'] && $v['orderId'] == $vv['orderId']){
                    $dps_has_list[] = $v;
                    break;
                }
            }
        }
        if(count($dps_list) > 0 && count($dps_has_list) != count($dps_list)){
            $dps_has_id_arr = array_column($dps_has_list,'id');
            $dps_id_arr = array_column($dps_list,'id');
            $result = array_diff($dps_id_arr,$dps_has_id_arr);
            DispatchingProgressSummery::getUpdate([['id','in',$result]],['isdeleted'=>1]);
        }
        //将调度信息加入队列依次插入数据库
//            $DispatchingProgressSummery = new DispatchingProgressSummery();
        foreach($orders as $k=>$v){
            if(empty($v['dps_id']) || $v['dps_sum_quantities'] != $v['sum_quantities'] || $v['dps_truck_no'] != $v['truck_no']) {
                $isPushed = Queue::push('app\job\JobDispatching', $v, 'dispatchingProgressSummery');
                // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
                if ($type == 1) {
                    if ($isPushed !== false) {
                        echo date('Y-m-d H:i:s') . " a new Job is Pushed to the MQ" . "<br>";
                    } else {
                        echo 'Oops, something went wrong.';
                    }
                }
            }
        }
    }

    /**
     * 获取订单明细信息
     * @param $orderId  订单id
     * @return array
     */
    public function getProductOrderDetailList($businessId,$user_id,$orderId,$wcc_sort=0,$wcc_sort_type=1)
    {
        $where = [
            ['wcc.order_id', '=', $orderId],
            ['wcc.customer_buying_quantity', '>', 0]
        ];
        switch ($wcc_sort){
            case 1://进行中排序
                if($wcc_sort_type == 1){
                    $order_by = 'dispatching_is_producing_done asc,id asc';
                } else {
                    $order_by = 'dispatching_is_producing_done desc,id asc';
                }
                break;
            case 2://已完成排序
                if($wcc_sort_type == 1) {
                    $order_by = 'dispatching_is_producing_done desc,id asc';
                } else {
                    $order_by = 'dispatching_is_producing_done asc,id asc';
                }
                break;
            case 3://拼箱排序
                if($wcc_sort_type == 1) {
                    $order_by = 'mix_box_group desc,id asc';
                } else {
                    $order_by = 'mix_box_group asc,id asc';
                }
                break;
            default://产品编号排序
                if($wcc_sort_type == 1) {
                    $order_by = 'rm.menu_id asc';
                } else {
                    $order_by = 'rm.menu_id desc';
                }
        }
        //获取加工明细单数据
        $order = Db::name('wj_customer_coupon')
            ->alias('wcc')
//            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,rm.menu_en_name,rm.menu_id,rm.unit_en,wcc.guige1_id,rmo.menu_en_name guige_name,o.userId,o.orderId,o.logistic_delivery_date,o.logistic_sequence_No,o.logistic_truck_No,o.boxesNumber,o.boxesNumberSortId,o.edit_boxesNumber,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.dispatching_is_producing_done,1 as num1,dps.operator_user_id,dps.isDone,rm.proucing_item,wcc.message,rm.unit_en,wcc.dispatching_item_operator_user_id,wcc.boxnumber,wcc.splicingboxnumber,wcc.print_label_sorts,wcc.mix_box_sort_id,wcc.current_box_sort_id')
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.message,wcc.boxnumber,wcc.splicingboxnumber,wcc.mix_box_group,wcc.print_label_sorts,wcc.current_box_sort_id,wcc.mix_box_sort_id,o.userId,o.orderId,o.first_name,o.last_name,o.displayName,o.address,o.phone,o.message_to_business,o.customer_delivery_option,o.logistic_stop_No,rm.menu_en_name,rm.menu_id,rm.unit_en,wcc.guige1_id,rmo.menu_en_name guige_name,o.userId,o.orderId,o.logistic_delivery_date,o.logistic_sequence_No,o.logistic_truck_No,o.boxesNumber,o.boxesNumberSortId,o.edit_boxesNumber,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.dispatching_is_producing_done,1 as num1,dps.operator_user_id,dps.isDone,rm.proucing_item,rm.unit_en,wcc.dispatching_item_operator_user_id,wcc.mix_box_group')
//            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.message,wcc.boxnumber,wcc.splicingboxnumber,wcc.mix_box_group,wcc.print_label_sorts,wcc.current_box_sort_id,wcc.mix_box_sort_id,o.userId,o.orderId,o.first_name,o.last_name,o.displayName,o.address,o.phone,o.message_to_business,o.logistic_truck_No,o.logistic_sequence_No,o.logistic_stop_No,o.logistic_delivery_date,o.logistic_suppliers_info,o.logistic_suppliers_count,o.customer_delivery_option,o.boxesNumber,o.boxesNumberSortId,o.edit_boxesNumber,o.redeem_code,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,1 as num1,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done,rm.proucing_item,rm.unit_en,rm.unitQtyPerBox,rm.overflowRate')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('restaurant_menu_option rmo','wcc.guige1_id = rmo.id')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->leftJoin('dispatching_progress_summery dps',"dps.delivery_date = o.logistic_delivery_date and dps.business_id=$businessId and dps.orderId=o.orderId and dps.isdeleted=0")
            ->where($where)
            ->order($order_by)
            ->select()->toArray();
//        halt($order);
        //获取所有的司机信息
        $logistic_truck_No_arr = array_filter(array_unique(array_column($order,'logistic_truck_No')));
        $truck_data_arr = [];//存储司机的信息
        if($logistic_truck_No_arr){
            $truck_data_arr = Truck::alias('t')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([
                    ['t.business_id','=',$businessId],
                    ['t.truck_no','in',$logistic_truck_No_arr],
                ])
                ->column('t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname','t.truck_no');
            foreach ($truck_data_arr as &$v){
                $v['name'] = $v['contactPersonFirstname'].' '.$v['contactPersonLastname'];
            }
        }
        foreach($order as &$v){
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            $v['truck_info'] = $truck_data_arr[$v['logistic_truck_No']] ?? [];
            //判断当前加工明细是否被锁定
            $v['is_lock'] = 0;
            $v['lock_type'] = 0;
            if($v['operator_user_id'] > 0){
                if($v['isDone'] == 0){
                    $v['is_lock'] = 1;//是否被锁定，1锁定 2未锁定
                    $v['lock_type'] = $user_id == $v['operator_user_id']?1:2;//1-被自己锁定 2-被他人锁定
                }
            }
            if($v['customer_delivery_option']=='1'){
                $customer_delivery_option='Delivery';
            }elseif($v['customer_delivery_option']=='2'){
                $customer_delivery_option='Pick up';
            }else{
                $customer_delivery_option='No Delivery';
            }
            $name = $this->getCustomerName($v);
            $v['subtitle'] = $customer_delivery_option."  CustId:".$v['userId']." <br>" .'CustName:<strong  style=\"width: 80%;font-size:16px;font-weight:bolder\" >'. $name."</strong>" ;
            $v = $this->getOrderItemBoxSortId($v);
        }
        return $order;
    }

    /**
     * 获取司机可以配送的日期
     */
    public function getDriverDeliveryDate($businessId,$logistic_truck_No,$logistic_delivery_date='')
    {
        $date_arr = Db::name('order')->where([
            ['business_userId', '=', $businessId],
            ['logistic_delivery_date', '>', time() - 3600 * 24 * 30],
            ['logistic_truck_No', '=', $logistic_truck_No]
        ])->field("logistic_delivery_date,FROM_UNIXTIME(logistic_delivery_date,'%Y-%m-%d') date,2 as is_default")->group('logistic_delivery_date')->order('logistic_delivery_date asc')->select()->toArray();
        //获取默认显示日期,距离今天最近的日期，将日期分为3组，今天之前，今天，今天之后距离今天最近的日期的key值
        $today_time = strtotime(date('Y-m-d', time()));
        $default = [];//默认显示日期数据
        $default_k = 0;//默认显示日期索引值
        foreach ($date_arr as $k => $v) {
            $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'], $today_time);
            if ($v['logistic_delivery_date'] == $logistic_delivery_date) {
                $date_arr[$k]['is_default'] = 1;
                $default = $date_arr[$k];
                $default_k = $k;
            }
        }
        //如果存储的日期存在，则默认显示存储日期；否则按原先规格显示
        if ($default) {
            return ['list' => $date_arr, 'default' => $default, 'default_k' => $default_k];
        } else {
            $today_before_k = $today_k = $today_after_k = '';
            foreach ($date_arr as $k => $v) {
                $date_arr[$k]['date_day'] = date_day($v['logistic_delivery_date'], $today_time);
                if ($v['logistic_delivery_date'] - $today_time <= 0) {
                    $today_before_k = $k;
                }
                if ($v['logistic_delivery_date'] - $today_time == 0) {
                    $today_k = $k;
                }
                if ($v['logistic_delivery_date'] - $today_time > 0) {
                    $today_after_k = $k;
                    break;
                }
            }
            if ($today_k !== '') {
                $date_arr[$today_k]['is_default'] = 1;
                $default = $date_arr[$today_k];
                $default_k = $today_k;
                return ['list' => $date_arr, 'default' => $default, 'default_k' => $default_k];
            }
            if ($today_after_k !== '') {
                $date_arr[$today_after_k]['is_default'] = 1;
                $default = $date_arr[$today_after_k];
                $default_k = $today_after_k;
                return ['list' => $date_arr, 'default' => $default, 'default_k' => $default_k];
            }
            if ($today_before_k !== '') {
                $date_arr[$today_before_k]['is_default'] = 1;
                $default = $date_arr[$today_before_k];
                $default_k = $today_before_k;
                return ['list' => $date_arr, 'default' => $default, 'default_k' => $default_k];
            }
            return ['list' => $date_arr, 'default' => $default, 'default_k' => $default_k];
        }
    }

    /**
     * 获取司机端的订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id
     * @param int $type 1。获取boxs的统计信息 2.获取订单的统计信息
     * @return array
     */
    public function getDriverOrderCount($businessId,$logistic_delivery_date,$logistic_truck_No,$type=2)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.logistic_delivery_date', '=', $logistic_delivery_date],
            ['o.logistic_truck_No', '=', $logistic_truck_No]
        ];
        $sql_model = Db::name('order')
            ->alias('o')
            ->where($where)
            ->where($map);
        //获取需要配送的订单总数
        if($type == 2){
            $order_count = $sql_model->count();
        } else {
            $order_count = $sql_model->sum('boxs');
        }
        //获取已加工的订单总数
        $where[] = ['o.driver_receipt_status', '=', 1];
        $sql_model1 = Db::name('order')
            ->alias('o')
            ->where($where)
            ->where($map);
        if($type == 2){
            $order_done_count = $sql_model1->count();
        } else {
            $order_done_count = $sql_model1->sum('boxs');
        }
        return [
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取司机端的订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getDriverBoxCount($businessId,$logistic_delivery_date,$logistic_truck_No)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.logistic_delivery_date', '=', $logistic_delivery_date],
            ['o.logistic_truck_No', '=', $logistic_truck_No]
        ];
        //获取需要配送的订单总数
        $order_count = Db::name('order')
            ->alias('o')
            ->where($where)
            ->where($map)
            ->sum('boxs');
        //获取已加工的订单总数
        $where[] = ['o.driver_receipt_status', '=', 1];
        $order_done_count = Db::name('order')
            ->alias('o')
            ->where($where)
            ->where($map)
            ->sum('boxs');
        return [
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取司机订单明细信息
     * @return array
     */
    public function getDriverOrderList($logistic_delivery_date,$businessId,$logistic_truck_No,$o_sort=0,$o_sort_type=1)
    {
        //获取当前用户对应的司机编号
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.logistic_delivery_date', '=', $logistic_delivery_date],
            ['o.business_userId', '=', $businessId],
            ['o.logistic_truck_No', '=', $logistic_truck_No],
        ];
        switch ($o_sort){
            case 1://Name排序
                if($o_sort_type == 1) {
                    $order_by = 'o.userId asc,o.id asc';
                } else {
                    $order_by = 'o.userId desc,o.id desc';
                }
                break;
            case 2://SeqNo排序
                if($o_sort_type == 1) {
                    $order_by = 'o.logistic_sequence_No asc,o.id asc';
                } else {
                    $order_by = 'o.logistic_sequence_No desc,o.id desc';
                }
                break;
            default://默认StopNo排序
                if($o_sort_type == 1){
                    $order_by = 'o.logistic_stop_No asc,o.id asc';
                } else {
                    $order_by = 'o.logistic_stop_No desc,o.id desc';
                }
        }
        //获取加工明细单数据
        $order = Db::name('order')
            ->alias('o')
            ->field('o.orderId,o.userId,o.business_userId,o.coupon_status,o.logistic_delivery_date,o.logistic_sequence_No,o.logistic_stop_No,o.address,o.driver_receipt_status,o.boxs,o.displayName,o.first_name,o.last_name,o.phone,uf.nickname user_name,u.nickname business_name,u.name')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->leftJoin('user u','u.id = o.business_userId')
            ->where($where)
            ->where($map)
            ->order($order_by)
            ->select()->toArray();
        foreach($order as &$v){
            $v['delivery_date'] = date('m/d/Y',$v['logistic_delivery_date']);
            $v['business_name'] = $v['business_name'] ?: $v['name'];
            $v['business_shortcode'] = $v['displayName'] ?: $v['first_name'].' '.$v['last_name'];
        }
        return $order;
    }

    /**
     * 配货端-获取产品订单明细
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id
     * @return array
     */
    public function getProductItemOrderList($businessId,$user_id,$logistic_delivery_date='',$logistic_truck_No='',$product_id='',$guige1_id='',$wcc_sort=0,$wcc_sort_type=1)
    {
        $map = "(o.status=1 or o.accountPay=1) and (o.coupon_status='c01' or o.coupon_status='b01')";
        $where = [
            ['o.business_userId', '=', $businessId],
//            ['o.coupon_status', '=', 'c01']
            ['wcc.customer_buying_quantity', '>', 0]
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
                    $order_by = 'wcc.dispatching_is_producing_done asc,id asc';
                }else{
                    $order_by = 'wcc.dispatching_is_producing_done desc,id asc';
                }
                break;
            case 2:
                if($wcc_sort_type == 1) {
                    $order_by = 'wcc.dispatching_is_producing_done desc,id asc';
                }else{
                    $order_by = 'wcc.dispatching_is_producing_done asc,id asc';
                }
                break;
            default:
                if($wcc_sort_type == 1) {
                    $order_by = 'id asc,wcc.dispatching_is_producing_done asc';
                }else{
                    $order_by = 'id desc,wcc.dispatching_is_producing_done asc';
                }
        }
        //获取加工明细单数据
        $order = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,wcc.message,wcc.boxnumber,wcc.splicingboxnumber,wcc.mix_box_group,wcc.print_label_sorts,wcc.current_box_sort_id,wcc.mix_box_sort_id,o.userId,o.orderId,o.first_name,o.last_name,o.displayName,o.address,o.phone,o.message_to_business,o.logistic_truck_No,o.logistic_sequence_No,o.logistic_stop_No,o.logistic_delivery_date,o.logistic_suppliers_info,o.logistic_suppliers_count,o.customer_delivery_option,o.boxesNumber,o.boxesNumberSortId,o.edit_boxesNumber,o.redeem_code,uf.nickname,wcc.customer_buying_quantity,wcc.new_customer_buying_quantity,wcc.is_producing_done,1 as num1,wcc.dispatching_item_operator_user_id,wcc.dispatching_is_producing_done,rm.proucing_item,rm.unit_en,rm.unitQtyPerBox,rm.overflowRate')
            ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->where($where)
            ->where($map)
            ->order($order_by)
            ->select()->toArray();
        //获取所有的司机信息
        $logistic_truck_No_arr = array_filter(array_unique(array_column($order,'logistic_truck_No')));
        $truck_data_arr = [];//存储司机的信息
        if($logistic_truck_No_arr){
            $truck_data_arr = Truck::alias('t')
                ->leftjoin('user u','u.id=t.current_driver')
                ->where([
                    ['t.business_id','=',$businessId],
                    ['t.truck_no','in',$logistic_truck_No_arr],
                ])
                ->column('t.truck_no logistic_truck_No,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname','t.truck_no');
            foreach ($truck_data_arr as &$v){
                $v['name'] = $v['contactPersonFirstname'].' '.$v['contactPersonLastname'];
            }
        }
        foreach($order as &$v){
            $v['new_customer_buying_quantity'] = $v['new_customer_buying_quantity']>=0?$v['new_customer_buying_quantity']:$v['customer_buying_quantity'];
            $v['truck_info'] = $truck_data_arr[$v['logistic_truck_No']] ?? [];
            //判断当前加工明细是否被锁定
            $v['is_lock'] = 0;
            $v['lock_type'] = 0;
            if($v['dispatching_item_operator_user_id'] > 0){
                if($v['dispatching_is_producing_done'] == 0||$v['dispatching_is_producing_done'] == 2){
                    $v['is_lock'] = 1;//是否被锁定，1锁定 2未锁定
                    $v['lock_type'] = $user_id == $v['dispatching_item_operator_user_id']?1:2;//1-被自己锁定 2-被他人锁定
                }
            }
            if($v['customer_delivery_option']=='1'){
                $customer_delivery_option='Delivery';
            }elseif($v['customer_delivery_option']=='2'){
                $customer_delivery_option='Pick up';
            }else{
                $customer_delivery_option='No Delivery';
            }
            $name = $this->getCustomerName($v);
            $v['subtitle'] = $customer_delivery_option."  CustId:".$v['userId']." <br>" .'CustName:<strong  style=\"width: 80%;font-size:16px;font-weight:bolder\" >'. $name."</strong>" ;
            //获取该产品的所有打印标签记录-（如果有记录则显示最后一个打印标签，如果没有记录，则显示当前订单的总序号）
            $v = $this->getOrderItemBoxSortId($v);
        }
        return $order;
    }

    /**
     * 获取订单明细当前的打印序号id
     */
    public function getOrderItemBoxSortId($data)
    {
        $data['old_boxesNumberSortId'] = $data['boxesNumberSortId'];
        $data['boxes'] = $data['splicingboxnumber']>0?$data['boxnumber']+1:$data['boxnumber'];//获取该产品需要打印的总箱数
        //获取该订单的总箱数
        if($data['edit_boxesNumber']<=0){
            $data['edit_boxesNumber'] = $data['boxesNumber'];
        }
        $data['old_boxesNumber'] = $data['boxesNumber'];
        $data['boxesNumber'] = $data['edit_boxesNumber'];
        //获取当前明细的序号id
        if(empty($data['print_label_sorts'])){
            $data['print_label_sorts_arr'] = [];
            if($data['current_box_sort_id'] <= 0){
                $data['current_box_sort_id'] = $data['boxesNumberSortId'] >= $data['boxesNumber']?$data['boxesNumber']:$data['boxesNumberSortId'];
            }
        }else{
            $data['print_label_sorts_arr'] = array_filter(explode(',',$data['print_label_sorts']));
            if($data['current_box_sort_id'] <= 0) {
                $data['current_box_sort_id'] = count($data['print_label_sorts_arr']) < $data['boxes'] ? $data['boxesNumberSortId'] : $data['print_label_sorts_arr'][count($data['print_label_sorts_arr']) - 1];
            }
        }
        $data['print_label_sorts_length'] = count($data['print_label_sorts_arr']);//获取该产品打印标签的个数
        return $data;
    }

    /**
     * 获取产品需要的箱数
     */
    public function getProductBoxes($new_customer_buying_quantity,$unitQtyPerBox,$boxesNumberSortId,$overflowRate)
    {
        $boxs_integer_nums = intval($new_customer_buying_quantity/$unitQtyPerBox);
        $remain_nums = (float)number_format($new_customer_buying_quantity-$boxs_integer_nums*$unitQtyPerBox,2);
        //如果没有余数，则正好是整箱数
        if($remain_nums == 0){
            $boxes = $boxs_integer_nums;
        }else{
            //如果有余数，
            //1.判断溢出率，如果按照溢出率，正好够装满最后一箱，则总箱数就是当前整箱数
            //2.判断溢出率，如果按照溢出率，$remain_nums>$allow_overflow_nums，判断当前序号是否为1，如果为1，则多打一箱子，如果不是，则不多打一箱
            $allow_overflow_nums = $overflowRate*$unitQtyPerBox/100;
            if ($remain_nums <= $allow_overflow_nums) {
                $boxes = $boxs_integer_nums;
            } else {
                if($boxesNumberSortId == 1){
                    $boxes = $boxs_integer_nums+1;
                }else{
                    $boxes = $boxs_integer_nums;
                }
            }
        }
        return [
            'boxs_integer_nums' => $boxs_integer_nums,//整箱数
            'remain_nums' => $remain_nums,//余数
            'boxes' => $boxes,//实际打印箱数
        ];
    }

    //根据订单信息获得客户名称
    public function getCustomerName($order){
        //第一优先级 客户简码;
        // 如果有客户填写的客户名，同时附上
        if (!empty($order['nickname'])&&trim($order['nickname'])) {
            if(trim($order['displayName'])){
//                return trim($order['nickname']).'('. trim(trim($order['displayName'])).')';
                return trim($order['nickname']);
            }else{
                return trim($order['nickname']);
            }
        }
        //如果没有客户简码，则客户提交订单时的 客户名 为第二优先级 ，如果客户同时填写了姓名，附上姓名；
        if(!empty($order['displayName'])&&trim($order['displayName'])){
            if(trim($order['first_name']) || trim($order['last_name']) ) {
//                return trim($order['displayName']).'('. trim($order['first_name']).' '.trim($order['last_name']).')';
                return trim($order['displayName']);
            }else{
                return trim($order['displayName']);
            }
        }
        //  如果客户无简码，并且提交订单时未填写客户户名，则，客户填写的 姓 ，名 做为第三优先级 ；
        if((!empty($order['first_name'])&&trim($order['first_name'])) || (!empty($order['last_name'])&&trim($order['last_name'])) ) {
            return  trim($order['first_name']).' '.trim($order['last_name']);
        }
        //如果以上均为捕获到客户信息，则获取用户注册时的用户信息做为标记；
        $user = User::getOne($order['userId']);
        if($user){
            if(!empty($order['displayName'])&&trim($user['displayName'])){
                return trim($user['displayName']);
            }
            if(!empty($order['businessName'])&&trim($user['businessName'])){
                return trim($user['businessName']);
            }
            if((!empty($order['person_first_name'])&&trim($user['person_first_name'])) || (!empty($order['person_last_name'])&&trim($user['person_last_name']))){
                return trim($user['person_first_name']).' '.trim($user['person_last_name']);
            }
            return trim($user['name']);
        }
    }
}
