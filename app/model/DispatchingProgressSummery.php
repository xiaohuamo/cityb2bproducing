<?php
declare (strict_types = 1);

namespace app\model;

use app\common\traits\modelTrait;
use think\facade\Db;
use think\Model;

/**
 * @mixin \think\Model
 */
class DispatchingProgressSummery extends Model
{
    use modelTrait;

    /**
     * 将订单数据加入调度流程表
     * @param $data 调度汇总数据
     * @return array
     */
    public function addProgressSummary($data)
    {
        $dps_info = DispatchingProgressSummery::getOne([
            'business_id'=>$data['business_id'],
            'delivery_date'=>$data['delivery_date'],
            'orderId'=>$data['orderId'],
            'isdeleted'=>0
        ]);
        if (!empty($dps_info)) {
            //1.判断数据是否有变动
            if($dps_info['sum_quantities'] != $data['sum_quantities'] || $dps_info['truck_no'] != $data['truck_no']){
                $update_data['sum_quantities'] = $data['sum_quantities'];
                $update_data['truck_no'] = $data['truck_no'];
                if($data['sum_quantities']<$dps_info['finish_quantities']){
                    $update_data['finish_quantities'] = $data['sum_quantities'];
                }
                if($data['isDone']==1){
                    if($dps_info['sum_quantities'] < $data['sum_quantities']){
                        //查询该订单数量不一致时，是否还有待完成的订单
                        $is_wait_done = WjCustomerCoupon::getAll([
                            ['order_id','=',$dps_info['orderId']],
                            ['customer_buying_quantity','>',0],
                            ['dispatching_is_producing_done','=',0]
                        ]);
                        if($is_wait_done){
                            $update_data['isDone'] = 0;
                            //同时将该订单状态改为未完成状态
                            Order::getUpdate(['orderId'=>$dps_info['orderId']],['dispatching_is_producing_done'=>0]);
                        }else{
                            $update_data['finish_quantities'] = $data['sum_quantities'];
                        }

                    }
                }
                $res = self::getUpdate(['id' => $dps_info['id']], $update_data);
            }
        } else {
            $data['finish_quantities'] = 0;
            $data['operator_user_id'] = 0;
            $data['isDone'] = 0;
            $data['proucing_center_id'] = 0;
            $data['delivery_round'] = 1;
            $res = self::createData($data);
        }
        return $res;
    }

    /**
     * 获取cc_order可以配送的日期
     */
    public function getDeliveryDate($businessId,$logistic_delivery_date='')
    {
        $map = 'o.status=1 or o.accountPay=1';
        $where = [
            ['o.business_userId', '=', $businessId],
            ['o.coupon_status', '=', 'c01'],
            ['wcc.customer_buying_quantity', '>', 0],
            ['o.logistic_delivery_date','>',time()-3600*24*7],
        ];
        //获取需要加工的订单总数
        $date_arr = Db::name('wj_customer_coupon')
            ->alias('wcc')
            ->field("o.logistic_delivery_date,FROM_UNIXTIME(o.logistic_delivery_date,'%Y-%m-%d') date,2 as is_default")
            ->leftJoin('order o','wcc.order_id = o.orderId')
            ->where($where)
            ->where($map)
            ->group('o.logistic_delivery_date')
            ->order('o.logistic_delivery_date asc')
            ->select()->toArray();
//        $date_arr = Db::name('dispatching_progress_summery')->where([
//            ['business_id', '=', $businessId],
//            ['delivery_date','>',time()-3600*24*30],
//            ['isdeleted','=',0]
//        ])->field("delivery_date logistic_delivery_date,FROM_UNIXTIME(delivery_date,'%Y-%m-%d') date,2 as is_default")->group('delivery_date')->order('delivery_date asc')->select()->toArray();
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
     * 获取订单数（已加工订单数/总订单数）
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 配送司机id 0表示未分配司机
     * @return array
     */
    public function getDispatchingOrderCount($businessId,$logistic_delivery_date='',$logistic_truck_No='')
    {
        $where = [
            ['business_id', '=', $businessId],
            ['isdeleted', '=', 0],
        ];
        if($logistic_delivery_date){
            $where[] = ['delivery_date','=',$logistic_delivery_date];
        }
        if($logistic_truck_No!==''){
            $where[] = ['truck_No','=',$logistic_truck_No];
        }
        //获取需要加工的订单总数
        $sql_model = Db::name('dispatching_progress_summery')
            ->alias('dps');
        $order_count = $sql_model->where($where)->count();
        //获取已加工的订单总数
        $where[] = ['isDone','=',1];
        $order_done_count = $sql_model->where($where)->count();
        return [
//            'order' => $order,
            'order_done_count' => $order_done_count,
            'order_count' => $order_count
        ];
    }

    /**
     * 获取司机配送信息
     * @param $businessId  供应商id
     * @param string $logistic_delivery_date 配送日期
     * @param string $logistic_truck_No 司机truck_no
     * @param int $truck_sort 排序类型
     * @param int $sorce 调用接口来源 1-inidata 2-Dirver&orders
     */
    public function getDriversDeliveryData($businessId,$userId,$logistic_delivery_date,$choose_logistic_truck_No='',$truck_sort=0,$source=1)
    {
        $where = [
            ['dps.business_id', '=', $businessId],
            ['dps.delivery_date','=',$logistic_delivery_date],
            ['dps.isdeleted','=',0]
        ];
        if($choose_logistic_truck_No){
            $where[] = ['dps.truck_No','=',$choose_logistic_truck_No];
        }
        switch($truck_sort){
            case 1:
                $order_by = 'dps.isDone desc,dps.truck_No asc';
                break;
            case 2:
                $order_by = 'o.logisitic_schedule_time asc,dps.truck_No asc';
                break;
            default:$order_by = 'dps.isDone asc,dps.truck_No asc';
        }
        $data = Db::name('dispatching_progress_summery')
            ->alias('dps')
//            ->field('dps.truck_no logistic_truck_No,dps.operator_user_id,dps.isDone,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname,o.logisitic_schedule_time')
            ->leftJoin('order o','o.orderId = dps.orderId')
            ->leftJoin('truck t',"t.truck_no = dps.truck_no and t.business_id=$businessId")
            ->leftjoin('user u','u.id=t.current_driver')
            ->where($where)
            ->group('dps.truck_No')
            ->order($order_by)
//            ->select()->toArray();
            ->column('dps.truck_no logistic_truck_No,dps.operator_user_id,dps.isDone,t.truck_name,t.plate_number,u.contactPersonFirstname,u.contactPersonLastname,o.logisitic_schedule_time','dps.truck_no');
        if(isset($data[0])){
            $data_0 = $data[0];
            unset($data[0]);
            $data = array_values($data);
            array_unshift($data,$data_0);
        } else {
            $data = array_values($data);
        }
        $time = time();
        foreach ($data as &$v){
            $v['schedule_time'] = $v['logisitic_schedule_time'] > 0 ? date('h:ia',$v['logisitic_schedule_time']) : '';//发车时间
            $v['remain_time'] = '';//$v['logisitic_schedule_time'] > 0 ? 0 : 0;//距离发车剩余时间
            if($v['logistic_truck_No'] == 0){
                $v['name'] = 'waiting assigned';//无-待分配司机
            }else{
                $v['name'] = $v['contactPersonFirstname'].' '.$v['contactPersonLastname'];//司机姓名
            }
            //获取司机对应的所有订单
            $map = ['dps.truck_no'=>$v['logistic_truck_No']];
            $two_cate_done_info = Db::name('dispatching_progress_summery')->alias('dps')->field('operator_user_id,isDone')->where($where)->where($map)->select()->toArray();
            $v['is_has_two_cate'] = count($two_cate_done_info)>0 ? 1 : 2;//1-有二级分类 2-没有二级分类
            //判断处理状态 0-未处理 1-自己正在处理 2-其他人正在处理 3-处理完成
            $v['status'] = $this->getProcessStatus($v,$userId,1,$two_cate_done_info);
            //如果查询的是Dirver&orders的结果，获取该产品当前的所有操作员
            $v['operator_user'] = [];
            if($source == 2){
                $v['operator_user'] = Db::name('dispatching_progress_summery')
                    ->alias('dps')
                    ->field('operator_user_id,u.name,u.nickname,u.displayName,isDone')
                    ->leftJoin('user u','u.id = dps.operator_user_id')
                    ->where($where)
                    ->where([['dps.operator_user_id', '>', 0]])
                    ->group('operator_user_id')
                    ->select()->toArray();
                foreach ($v['operator_user'] as &$vv){
                    $vv['user_name'] = $vv['nickname'] ?: $vv['name'];
                }
            }
        }
        return $data;
    }

    /**
     * 获取对应司机的订单信息
     * @param $where
     * @return array|\think\Collection|Db[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOrderList($businessId,$userId,$logistic_delivery_date,$logistic_truck_No='',$choose_logistic_truck_No='',$tw_sort=0,$tw_sort_type=1,$type='')
    {
        $where = [
            ['dps.business_id', '=', $businessId],
            ['dps.delivery_date','=',$logistic_delivery_date],
            ['dps.isdeleted','=',0]
        ];
        if($type!='allDriverOrder'&&$logistic_truck_No!==''){
            $where[] = ['dps.truck_No','=',$logistic_truck_No];
        }
        if($type=='allDriverOrder'&&$choose_logistic_truck_No!==''){
            $where[] = ['dps.truck_No','=',$choose_logistic_truck_No];
        }
        switch ($tw_sort){
            case 0://SEQ No排序
                if($tw_sort_type == 1){
                    $order_by = 'o.logistic_sequence_No asc,o.id asc';
                } else {
                    $order_by = 'o.logistic_sequence_No desc,o.id asc';
                }
                break;
            case 1://Stop No排序
                if($tw_sort_type == 1) {
                    $order_by = 'o.logistic_stop_No desc,o.id asc';
                } else {
                    $order_by = 'o.logistic_stop_No asc,o.id asc';
                }
                break;
            case 2://Cust Code排序
                if($tw_sort_type == 1) {
                    $order_by = 'o.userId asc,o.id asc';
                } else {
                    $order_by = 'o.userId desc,o.id asc';
                }
                break;
        }
        $order_list = Db::name('dispatching_progress_summery')
            ->alias('dps')
            ->field('dps.orderId,dps.truck_no logistic_truck_No,o.logistic_sequence_No,dps.sum_quantities,dps.finish_quantities,dps.operator_user_id,dps.isDone,o.userId,uf.nickname,t.truck_name,t.plate_number,o.logisitic_schedule_time,u.contactPersonFirstname,u.contactPersonLastname')
            ->leftJoin('order o','o.orderId = dps.orderId')
            ->leftJoin('user_factory uf','uf.user_id = o.userId')
            ->leftJoin('truck t',"t.truck_no = dps.truck_no and t.business_id=$businessId")
            ->leftJoin('user u','u.id=t.current_driver')
            ->where($where)
            ->order($order_by)
            ->select()->toArray();
        foreach($order_list as &$v){
            //判断加工状态 0-未加工 1-自己正在加工 2-其他人正在加工 3-加工完成
            $v['status'] = $this->getProcessStatus($v,$userId,2);
            $v['name_length'] = $v['nickname'] ? strlen($v['nickname']) : 0;
            if($v['logistic_truck_No'] == 0){
                $v['name'] = 'waiting assigned';//无-待分配司机
            }else {
                $v['name'] = $v['contactPersonFirstname'] . ' ' . $v['contactPersonLastname'];//司机姓名
            }
            $v['schedule_time'] = $v['logisitic_schedule_time'] > 0 ? date('h:ia',$v['logisitic_schedule_time']) : '';//发车时间
            $v['remain_time'] = '';//$v['logisitic_schedule_time'] > 0 ? 0 : 0;//距离发车剩余时间
        }
        if ($type == 'allDriverOrder') {
            $list = [];
            $logistic_sequence_No_arr = array_column($order_list,'logistic_sequence_No');
            $truck_no_arr = array_column($order_list,'logistic_truck_No');
            array_multisort($logistic_sequence_No_arr,$truck_no_arr,$order_list);
            foreach($order_list as &$v){
                if(!isset($list[$v['logistic_truck_No']])) {
                    $list[$v['logistic_truck_No']] = [
                        'logistic_truck_No' => $v['logistic_truck_No'],
                        'name' => $v['name'],
                        'truck_name' => $v['truck_name'],
                        'plate_number' => $v['plate_number'],
                        'schedule_time' => $v['schedule_time'],
                        'remain_time' => $v['remain_time']
                    ];
                }
                $list[$v['logistic_truck_No']]['order'][] = $v;
            }
            if(isset($list[0])){
                $data_0 = $list[0];
                unset($list[0]);
                $list = array_values($list);
                array_unshift($list,$data_0);
            }
            $order_list = array_values($list);
        }
        return $order_list;
    }

    /**
     * 获取当前处理状态
     * @param $data 司机数据
     * @param $userId 当前用户id
     * @param int $type 1-一级类目司机信息 2-二级类目订单信息
     * @param array $two_cate_done_info 二级类目完成情况
     * @return int
     */
    public function getProcessStatus($data,$userId,$type=1,$two_cate_done_info=[])
    {
        //判断处理状态 0-未处理 1-自己正在处理 2-其他人正在处理 3-处理完成
        if($type==1 && $data['is_has_two_cate'] == 2 || $type==2){
            if($data['isDone'] == 0){
                if($data['operator_user_id'] > 0){
                    $status = $data['operator_user_id']==$userId ? 1 : 2;
                } else {
                    $status = 0;
                }
            }else{
                $status = 3;
            }
        } else {
            //如果一级分类中，有二级分类，需要根据二级分类中的所有来判断一级的状态
            if($type==1 && $data['is_has_two_cate'] == 1){
                //查询该产品所有的二级状态
                $two_cate_done_unique = array_unique(array_column($two_cate_done_info,'isDone'));
                $operator_user_id_arr = array_column($two_cate_done_info,'operator_user_id');
//                dump($two_cate_done_unique);
                if(count($two_cate_done_unique) == 1){
                    if($two_cate_done_unique[0] == 0){
                        $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr);
                    }else{
                        $status = 3;
                    }
                }else{
                    //判断未完成的规格中加工状态
                    $operator_user_id_arr = [];
                    foreach($two_cate_done_info as $v){
                        if($v['isDone'] == 0){
                            $operator_user_id_arr[] = $v['operator_user_id'];
                        }
                    }
                    $status = $this->productStatusAccordGuige($userId,$operator_user_id_arr);
                }
            }
        }
        return $status;
    }

    /**
     * 根据规格加工状态，判断当前产品显示的状态
     * @param $user_id
     * @param $operator_user_id_arr
     */
    public function productStatusAccordGuige($userId,$operator_user_id_arr)
    {
        if(count($operator_user_id_arr) > 0){//有人正在操作
            //如果当前用户正在加工该产品，状态为：正在加工中2
            //如果当前用户没加工该产品，判断所有规格是否都有人在加工，所有规格都被其他人加工，状态为：其他人加工中2。否则，状态为：待加工0
            if(in_array($userId,$operator_user_id_arr)){
                $status = 1;//表示正在加工中
            }else{
                if(in_array(0,$operator_user_id_arr)){
                    $status = 0;//表示待加工
                }else{
                    $status = 2;//其他人加工中
                }
            }
        }else{//所有规格都无人加工
            $status = 0;
        }
        return $status;
    }
}
