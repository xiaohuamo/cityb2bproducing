<?php
declare (strict_types = 1);

namespace app\product\controller;

use think\facade\Db;
use think\facade\Queue;
use think\Model;
use think\Request;
use app\product\validate\IndexValidate;
use app\model\{
    Order,
    WjCustomerCoupon,
    DispatchingProgressSummery,
    DispatchingItemBehaviorLog
};
use think\Validate;

class PickingItem extends AuthBase
{
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }

    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        // 模板输出
        return $this->fetch('index');
    }


    //根据筛选日期获取初始化数据
    public function iniData()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','one_cate_sort','type']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['one_cate_sort'] = $param['one_cate_sort']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        $Order = new Order();
        $WjCustomerCoupon = new WjCustomerCoupon();
        //3.获取对应日期默认全部的司机的已加工订单数量和总的加工订单数量
        $driver_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No'],2);
        //4.获取对应日期全部的已加工订单数量和总的加工订单数量
        $all_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date'],'',2);
        //5.获取分类的信息
        $cate = $WjCustomerCoupon->getPickingItemCategory($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['one_cate_sort']);
        $data = [
            'cate' => $cate,
            'driver_order_count' => $driver_order_count,
            'all_order_count' => $all_order_count,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //切换以及类目获取对应的信息
    public function changeCate()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','one_cate_id','two_cate_id']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['one_cate_id'] = $param['one_cate_id']??0;
        $param['two_cate_id'] = $param['two_cate_id']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        $WjCustomerCoupon = new WjCustomerCoupon();

        //1.获取对应日期加一级类目的产品信息
        $product = $WjCustomerCoupon->getOneCateProductList($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['one_cate_id'],$param['two_cate_id']);
        $data = [
            'product' => $product,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取产品的订单明细信息
    public function productItemOrderDetailList()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','product_id','guige1_id','wcc_sort','wcc_sort_type']);
        $param['guige1_id'] = $param['guige1_id']??'';
        $param['wcc_sort'] = $param['wcc_sort']??0;//排序字段
        $param['wcc_sort_type'] = $param['wcc_sort_type']??1;//1-正向排序 2-反向排序

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $Order = new Order();

        //获取对应日期的加工订单
        $order = $Order->getProductItemOrderList($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id'],$param['guige1_id'],$param['wcc_sort'],$param['wcc_sort_type']);
        $redis = redis_connect();
        $param['guige1_id'] = empty($param['guige1_id'])?0:$param['guige1_id'];
        $key = 'fit_print_all_'.$param['logistic_delivery_date'].'_'.$param['product_id'].'_'.$param['guige1_id'];
        $is_print_all = $redis->get($key);
        $data = [
            'order' => $order,
            'is_print_all' => !empty($is_print_all)?1:2,//是否全部打印 1是 2否
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //picking&sum页面锁定非生产产品
    public function lockProductItem()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','product_id','guige1_id']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['guige1_id'] = $param['guige1_id']??'';

        $validate = new IndexValidate();
        if (!$validate->scene('lockProductItem')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取产品信息
        $WjCustomerCoupon = new WjCustomerCoupon();
        $product_data = $WjCustomerCoupon->getPickProductData($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id'],$param['guige1_id']);
//        halt($product_data);
        if (!$product_data) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }

        //添加队列
        $uniqid = uniqid(time().mt_rand(1,1000000), true);
        $jobData = [
            "uniqid" => $uniqid,
            "user_id" => $user_id,
            "businessId" => $businessId,
            "data" => $param
        ];
        $isPushed = Queue::push('app\job\JobLockPickProductItem', $jobData, 'lockPickProductItem');
        // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
        if ($isPushed !== false) {
            $data = [
                "uniqid" => $uniqid,
            ];
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        } else {
            return show(config('status.code')['lock_error']['code'],config('status.code')['lock_error']['msg']);
        }
    }

    //获取锁定结果
    public function lockProductItemResult()
    {
        //接收参数
        $param = $this->request->only(['uniqid']);
        $uniqid = $param['uniqid'];
        if ($uniqid) {
            $redis = redis_connect();
            $res = $redis->get($uniqid);
            if ($res) {
                $temp = json_decode($res, true);
                $redis->del($uniqid);
                return show($temp['status'],$temp['message'],$temp['result']);
            } else {
                return show(config('status.code')['lock_result_error']['code'],config('status.code')['lock_result_error']['msg']);
            }
        }
    }

    //解锁
    public function unlockProductItem()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','product_id','guige1_id']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['guige1_id'] = $param['guige1_id']??'';

        $validate = new IndexValidate();
        if (!$validate->scene('lockProductItem')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户
        try{
            Db::startTrans();
            //1.获取产品信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $product_data = $WjCustomerCoupon->getPickProductData($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id'],$param['guige1_id']);
//        halt($product_data);
            if (!$product_data) {
                return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
            }
            //如果该产品已加工完，不可重复点击锁定解锁
            if ($product_data['isDone'] == 1) {
                return show(config('status.code')['lock_processed_error']['code'], config('status.code')['lock_processed_error']['msg']);
            }
            //判断该产品是否是当前上锁人解锁的
            if ($product_data['operator_user_id'] != $user_id) {
                return show(config('status.code')['unlock_user_error']['code'], config('status.code')['unlock_user_error']['msg']);
            }
            //解锁
            $WjCustomerCoupon->updatePickProductItemProcessedData($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id'],$param['guige1_id'],$user_id,2);
            Db::commit();
            //添加用户行为日志
            $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
            $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,2,$param['logistic_delivery_date'],$param);
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }

    //修改订单明细状态
    public function pickChangeProductItemStatus()
    {
        //接收参数
        $param = $this->request->only(['id','is_producing_done','logistic_truck_No','product_id','guige1_id']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['product_id'] = $param['product_id']??'';
        $param['guige1_id'] = $param['guige1_id']??'';
        $validate = new IndexValidate();
        if (!$validate->scene('changeProductOrderStatus')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        try{
            Db::startTrans();

            $businessId = $this->getBusinessId();
            $user_id = $this->getMemberUserId();//当前操作用户
            $order_inc_num = 0;//加工完成订单数据新增
            $driver_order_inc_num = 0;//对应的司机加工订单数据新增
            $is_product_guige1_done = 0;//该产品/规格对应的总量是否完毕
            $is_product_guige1_all_done = 0;//该产品是否所有规格都完毕
            $is_cate_all_done = 0;//该分类下的产品是否全部完成

            //1.获取订单明细信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['dispatching_is_producing_done'] == $param['is_producing_done']){
                return show(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //获取该产品的数据
            $product_data = $WjCustomerCoupon->getWccProductList($businessId,$user_id,$wcc_info['logistic_delivery_date'],$param['logistic_truck_No'],$wcc_info['product_id']);
            //计算完成的总数
            $sum_quantities = 0;//该产品的总数量
            $guige_sum_quantities = 0;//该规格的总数量
            $finish_quantities = 0;//该产品完成的总数量
            $guige_finish_quantities = 0;//该规格完成的总数量
            foreach ($product_data as $v){
                if($v['dispatching_is_producing_done'] == 1){
                    $finish_quantities += $v['customer_buying_quantity'];
                    if($wcc_info['guige1_id'] > 0 && $v['guige1_id'] == $wcc_info['guige1_id']){
                        $guige_finish_quantities += $v['customer_buying_quantity'];
                    }
                }
                $sum_quantities += $v['customer_buying_quantity'];
                if($wcc_info['guige1_id'] > 0 && $v['guige1_id'] == $wcc_info['guige1_id']){
                    $guige_sum_quantities += $v['customer_buying_quantity'];
                }
            }
            //一.已处理和正在处理流程
            if($param['is_producing_done'] == 1 || $param['is_producing_done'] == 2){
                //1-1.判断该产品是否有人加工，无人加工不可点击已处理
                if(!($wcc_info['dispatching_item_operator_user_id'] > 0)){
                    return show(config('status.code')['summary_process_error']['code'], config('status.code')['summary_process_error']['msg']);
                }
                //如果当前操作员工处理员工是否是同一个人
                if($wcc_info['dispatching_item_operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['dispatching_item_operator_user_id'=>$user_id,'dispatching_is_producing_done'=>$param['is_producing_done']]);
                if($param['is_producing_done'] == 2){
                    Db::commit();
                    return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
                }
                //产品完成总数量增加,判断是否完成
                $finish_quantities += $wcc_info['customer_buying_quantity'];
                if ($wcc_info['guige1_id'] > 0) {
                    $guige_finish_quantities += $wcc_info['customer_buying_quantity'];
                    if($guige_finish_quantities == $guige_sum_quantities){
                        $is_product_guige1_done = 1;
                        if($finish_quantities == $sum_quantities){
                            $is_product_guige1_all_done = 1;
                        }
                    }
                } else {
                    if($finish_quantities == $sum_quantities){
                        $is_product_guige1_all_done = 1;
                    }
                }
                //如果产品全部完成，判断该产品对应的分类是否完成
                if($is_product_guige1_all_done == 1){
                    $count = $WjCustomerCoupon->getWccOrderDone('',$businessId,$wcc_info['logistic_delivery_date'],$wcc_info['logistic_truck_No'],'',$wcc_info['cate_id'],2);
                    if($count == 0){
                        $is_cate_all_done = 1;
                    }
                }
                //2-2同时更新按订单拣货的汇总表,该订单是否全部拣货完成
                $dps_info = DispatchingProgressSummery::getOne(['orderId'=>$wcc_info['order_id'],'isDone'=>0,'isdeleted'=>0]);
                if(!empty($dps_info)){
                    $finish_quantities = $dps_info['finish_quantities']+1;
                    $dps_data['finish_quantities'] = $finish_quantities;
                    if($finish_quantities == $dps_info['sum_quantities']){
                        $dps_data['isDone'] = 1;
                    }
                    DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
                }
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则更改订单加工状态
                $count = $WjCustomerCoupon->getWccOrderDone($wcc_info['order_id'],'','','','','',2);
                if($count == 0){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'dispatching_is_producing_done'=>1
                    ]);
                    $order_inc_num = 1;//待加工产品全部完工，订单数+1
                    //判断对应司机的订单数，如果司机信息一致，则司机订单+1
                    if($param['logistic_truck_No']>0){
                        if($wcc_info['logistic_truck_No'] == $param['logistic_truck_No']){
                            $driver_order_inc_num = 1;
                        }else{
                            $driver_order_inc_num = 0;
                        }
                    }else{
                        $driver_order_inc_num = 1;
                    }
                }
                Db::commit();
                $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,3,$wcc_info['logistic_delivery_date'],$log_data);
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_guige1_all_done' => $is_product_guige1_all_done, //当前产品（包括所有规格）是否全部加工完毕
                    'is_cate_all_done' => $is_cate_all_done
                ];
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
            }
            //二.返回继续处理流程
            if($param['is_producing_done'] == 0){
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['dispatching_item_operator_user_id'=>$user_id,'dispatching_is_producing_done'=>0]);
                $finish_quantities -= $wcc_info['customer_buying_quantity'];
                $is_cate_all_done = 0;
                $is_product_guige1_all_done = 0;
                if ($wcc_info['guige1_id'] > 0) {
                    $guige_finish_quantities -= $wcc_info['customer_buying_quantity'];
                    $is_product_guige1_done = 0;
                }
                //2-2同时更新按订单拣货的汇总表,该订单是否全部拣货完成
                $dps_info = DispatchingProgressSummery::getOne(['orderId'=>$wcc_info['order_id'],'isdeleted'=>0]);
                if(!empty($dps_info)){
                    $finish_quantities = $dps_info['finish_quantities']-1;
                    $dps_data['finish_quantities'] = $finish_quantities;
                    if($dps_info['isDone'] == 1){
                        $dps_data['isDone'] = 0;
                    }
                    DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
                }
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则需还原更改订单加工状态
                if($wcc_info['order_dispatching_is_producing_done'] == 1){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'dispatching_is_producing_done'=>0
                    ]);
                    $order_inc_num = -1;
                    //判断对应司机的订单数，如果司机信息一致，则司机订单-1
                    if($param['logistic_truck_No']>0){
                        if($wcc_info['logistic_truck_No'] == $param['logistic_truck_No']){
                            $driver_order_inc_num = -1;
                        }else{
                            $driver_order_inc_num = 0;
                        }
                    }else{
                        $driver_order_inc_num = -1;
                    }
                }
                Db::commit();
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_guige1_all_done' => $is_product_guige1_all_done, //当前产品（包括所有规格）是否全部加工完毕
                    'is_cate_all_done' => $is_cate_all_done
                ];
                $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,4,$wcc_info['logistic_delivery_date'],$log_data);
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
            }

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }

    //修改全部订单明细状态
    public function pickAllChangeProductItemStatus()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','product_id','guige1_id','is_producing_done']);
        $validate = new IndexValidate();
        if (!$validate->scene('changePickAllProductOrderStatus')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        try{
            Db::startTrans();

            $businessId = $this->getBusinessId();
            $user_id = $this->getMemberUserId();//当前操作用户
            $order_inc_num = 0;//加工完成订单数据新增
            $driver_order_inc_num = 0;//对应的司机加工订单数据新增
            $is_product_guige1_done = 0;//该产品/规格对应的总量是否完毕
            $is_product_guige1_all_done = 0;//该产品是否所有规格都完毕
            $is_cate_all_done = 0;//该分类下的产品是否全部完成

            $WjCustomerCoupon = new WjCustomerCoupon();
            //1.获取该产品的所有订单数据
            $product_data = $WjCustomerCoupon->getWccProductList($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id']);
            if (!$product_data) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            //获取该产品的状态
            $product_guige_data = [];
            if($param['guige1_id'] > 0){
                foreach ($product_data as $v){
                    if($v['guige1_id'] == $param['guige1_id']){
                        $product_guige_data[] = $v;
                    }
                }
            }else{
                $product_guige_data = $product_data;
            }
            $done = $WjCustomerCoupon->getProductGuigeStatus($product_guige_data,$user_id);
            if($done['status'] != 1){
                return show(config('status.code')['product_plan_approved_error']['code'], config('status.code')['product_plan_approved_error']['msg']);
            }
            $id_arr = [];//存储所有相关的订单明细id
            $add_quantity = 0;//需要增加的总数量
            //计算完成的总数
            $sum_quantities = 0;//该产品的总数量
            $guige_sum_quantities = 0;//该规格的总数量
            $finish_quantities = 0;//该产品完成的总数量
            $guige_finish_quantities = 0;//该规格完成的总数量
            foreach ($product_data as $v){
                if($v['dispatching_is_producing_done'] == 1){
                    $finish_quantities += $v['customer_buying_quantity'];
                    if($param['guige1_id'] > 0 && $v['guige1_id'] == $param['guige1_id']){
                        $guige_finish_quantities += $v['customer_buying_quantity'];
                    }
                }
                $sum_quantities += $v['customer_buying_quantity'];
                if($param['guige1_id'] > 0){
                    if($v['guige1_id'] == $param['guige1_id']){
                        $guige_sum_quantities += $v['customer_buying_quantity'];
                        if($v['dispatching_is_producing_done'] == 0 || $v['dispatching_is_producing_done'] == 2){
                            $id_arr[] = $v['id'];
                            $add_quantity += $v['customer_buying_quantity'];
                        }
                    }
                } else {
                    if($v['dispatching_is_producing_done'] == 0 || $v['dispatching_is_producing_done'] == 2) {
                        $id_arr[] = $v['id'];
                        $add_quantity += $v['customer_buying_quantity'];
                    }
                }
            }
            //一.已处理和正在处理流程
            if($param['is_producing_done'] == 1){
                //1-1.判断该产品是否有人加工，无人加工不可点击已处理
                if(!($done['operator_user_id'] > 0)){
                    return show(config('status.code')['summary_process_error']['code'], config('status.code')['summary_process_error']['msg']);
                }
                //如果当前操作员工处理员工是否是同一个人
                if($done['operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $id_arr],['dispatching_item_operator_user_id'=>$user_id,'dispatching_is_producing_done'=>$param['is_producing_done']]);
                //产品完成总数量增加,判断是否完成
                $finish_quantities += $add_quantity;
                if ($param['guige1_id'] > 0) {
                    $guige_finish_quantities += $add_quantity;
                    if($guige_finish_quantities == $guige_sum_quantities){
                        $is_product_guige1_done = 1;
                        if($finish_quantities == $sum_quantities){
                            $is_product_guige1_all_done = 1;
                        }
                    }
                } else {
                    if($finish_quantities == $sum_quantities){
                        $is_product_guige1_all_done = 1;
                    }
                }
                //如果产品全部完成，判断该产品对应的分类是否完成
                if($is_product_guige1_all_done == 1){
                    $cate_id = $product_data[0]['cate_id'];
                    $count = $WjCustomerCoupon->getWccOrderDone('',$businessId,$param['logistic_delivery_date'],$param['logistic_truck_No'],'',$cate_id,2);
                    if($count == 0){
                        $is_cate_all_done = 1;
                    }
                }
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则更改订单加工状态
                foreach ($product_data as $v){
                    //3-1同时更新按订单拣货的汇总表,该订单是否全部拣货完成
                    $dps_info = DispatchingProgressSummery::getOne(['orderId'=>$v['order_id'],'isDone'=>0,'isdeleted'=>0]);
                    if(!empty($dps_info)){
                        $dps_data = [];
                        $finish_quantities = $dps_info['finish_quantities']+1;
                        $dps_data['finish_quantities'] = $finish_quantities;
                        if($finish_quantities == $dps_info['sum_quantities']){
                            $dps_data['isDone'] = 1;
                        }
                        DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
                    }
                    $count = $WjCustomerCoupon->getWccOrderDone($v['order_id'],'','','','','',2);
                    if($count == 0){
                        Order::getUpdate(['orderId' => $v['order_id']],[
                            'dispatching_is_producing_done'=>1
                        ]);
                        $order_inc_num += 1;
                        //判断对应司机的订单数，如果司机信息一致，则司机订单+1
                        if($param['logistic_truck_No']>0){
                            if($v['logistic_truck_No'] == $param['logistic_truck_No']){
                                $driver_order_inc_num += 1;
                            }
                        }else{
                            $driver_order_inc_num += 1;
                        }
                    }
                }
                Db::commit();
                $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
                foreach ($id_arr as $v){
                    $log_data = [
                        "product_id" => $param['product_id'],
                        "guige1_id" => $param['guige1_id'],
                        "wj_customer_coupon_id" => $v
                    ];
                    $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,8,$param['logistic_delivery_date'],$log_data);
                }
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_guige1_all_done' => $is_product_guige1_all_done, //当前产品（包括所有规格）是否全部加工完毕
                    'is_cate_all_done' => $is_cate_all_done,
                    'add_quantity' => $add_quantity
                ];
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }

    //修改加工数据
    public function editBuyingQuantity()
    {
        //接收参数
        $param = $this->request->only(['id','new_customer_buying_quantity']);
        $validate = new IndexValidate();
        if (!$validate->scene('editBuyingQuantity')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取加工明细信息
        $WjCustomerCoupon = new WjCustomerCoupon();
        $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
        if (!$wcc_info) {
            return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
        }

        //2.更新该产品加工数量和状态
        if($wcc_info['new_customer_buying_quantity'] != $param['new_customer_buying_quantity']){
            $res = WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['new_customer_buying_quantity'=>$param['new_customer_buying_quantity']]);
            if ($res) {
                $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id'],
                    "new_customer_buying_quantity" => $param['new_customer_buying_quantity']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,5,$wcc_info['logistic_delivery_date'],$log_data);
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            } else {
                return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
            }
        } else {
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        }
    }

    //获取非加工产品日志数据
    public function pickProductItemLogData()
    {
        $param = $this->request->only(['logistic_delivery_date','product_id','guige1_id','wcc_id']);
        $param['guige1_id'] = isset($param['guige1_id']) ? ($param['guige1_id']?:0) : 0;
        $param['wcc_id'] = isset($param['wcc_id']) ? ($param['wcc_id']?:0) : 0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
        $res = $DispatchingItemBehaviorLog->getLogData($businessId,$user_id,$param);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }

    /**
     * 修改box数量
     */
    public function editBoxNumber()
    {
        $param = $this->request->only(['id','num','type']);
        $validate = new IndexValidate();
        if (!$validate->scene('editBoxNumber')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        try {
            if($param['type'] == 1){
                $field = 'boxesNumberSortId';
            }else{
                $field = 'boxesNumber';
            }
            $data[$field] = $param['num'];
            //1.获取加工明细信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($param['type'] == 1 && $param['num'] > $wcc_info['boxesNumber']){
                $data['boxesNumber'] = $param['num'];
            }
            Db::startTrans();
            if($wcc_info[$field]!=$param['num']){
                //更改箱子数量
                Order::getUpdate(['orderId'=>$wcc_info['order_id']],$data);
                //同时将修改数量加入日志
                $DispatchingItemBehaviorLog = new DispatchingItemBehaviorLog();
                $log_data = [
                    "wj_customer_coupon_id" => $param['id'],
                    "$field" => $param['num']
                ];
                $DispatchingItemBehaviorLog->addProducingBehaviorLog($user_id,$businessId,5,$wcc_info['logistic_delivery_date'],$log_data);
            }
            Db::commit();
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }

    /**
     *打印时获取最新的箱数以及打印后箱数序号更新
     */
    public function orderBoxsNumber()
    {
        $param = $this->request->only(['id_arr','print_type']);

        $validate = new IndexValidate();
        if (!$validate->scene('orderBoxsNumber')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        try {
            //1.获取订单最新的箱数信息
            $box_list = WjCustomerCoupon::alias('wcc')
                ->field('wcc.id,wcc.restaurant_menu_id product_id,wcc.guige1_id,o.orderid,o.logistic_delivery_date,ceil(wcc.customer_buying_quantity/rm.unitQtyPerBox) AS boxes,o.boxesNumber,o.boxesNumberSortId')
                ->leftJoin('order o','o.orderId = wcc.order_id')
                ->leftJoin('restaurant_menu rm','rm.id = wcc.restaurant_menu_id')
                ->where(['wcc.id'=>$param['id_arr']])
                ->select()->toArray();
            if (!$box_list) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            Db::startTrans();
            //根据打印类型更新数据
            switch ($param['print_type']){
                case 1://fit print all 全部打印，需要修改该产品所有的订单明细所需要的箱数排序的全部序号
                case 2://fit print 按照单个订单明细打印所有的箱数
                    foreach($box_list as &$v){
                        $where['orderId'] = $v['orderid'];
                        if($v['boxesNumberSortId'] <= $v['boxesNumber']){
                            $update['boxesNumberSortId'] = $v['boxesNumberSortId'] + $v['boxes'];
                            Order::getUpdate($where,$update);
                            $v['boxesNumberSortId'] = $update['boxesNumberSortId'];
                            $v['newboxesNumberSortId'] = $update['boxesNumberSortId']>$v['boxesNumber']?$v['boxesNumber']:$update['boxesNumberSortId'];
                        }else{
                            $v['newboxesNumberSortId'] = $v['boxesNumber'];//返回新的表标签序号
                        }
                    }
                    break;
                case 3://print order 打印该订单的全部标签
                    foreach($box_list as &$v){
                        $where['orderId'] = $v['orderid'];
                        if($v['boxesNumberSortId'] <= $v['boxesNumber']) {
                            $update['boxesNumberSortId'] = $v['boxesNumber'] + 1;
                            Order::getUpdate($where, $update);
                            $v['boxesNumberSortId'] = $update['boxesNumberSortId'];
                            $v['newboxesNumberSortId'] = $update['boxesNumberSortId']>$v['boxesNumber']?$v['boxesNumber']:$update['boxesNumberSortId'];
                        }else{
                            $v['newboxesNumberSortId'] = $v['boxesNumber'];//返回新的表标签序号
                        }
                    }
                    break;
                case 0://表示未选择，默认打印一张有序号的标签
                case 4://blank label 每次输出一张空白标签
                    foreach($box_list as &$v){
                        $where['orderId'] = $v['orderid'];
                        if($v['boxesNumberSortId'] <= $v['boxesNumber']) {
                            $update['boxesNumberSortId'] = $v['boxesNumberSortId'] + 1;
                            Order::getUpdate($where, $update);
                            $v['boxesNumberSortId'] = $update['boxesNumberSortId'];
                            $v['newboxesNumberSortId'] = $update['boxesNumberSortId']>$v['boxesNumber']?$v['boxesNumber']:$update['boxesNumberSortId'];
                        }else{
                            $v['newboxesNumberSortId'] = $v['boxesNumber'];//返回新的表标签序号
                        }
                    }
                    break;
            }
            Db::commit();
            //如果是全部打印，只可以打印一次。之后不可已再选择全部打印
            if($param['print_type'] == 1){
                $redis = redis_connect();
                $logistic_delivery_date = $box_list[0]['logistic_delivery_date'];
                $product_id = $box_list[0]['product_id'];
                $guige_id = $box_list[0]['guige1_id'];
                $key = 'fit_print_all_'.$logistic_delivery_date.'_'.$product_id.'_'.$guige_id;
                $expire_time = 7*86400;
                $redis->setex($key,$expire_time,1);
            }
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$box_list);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }
}
