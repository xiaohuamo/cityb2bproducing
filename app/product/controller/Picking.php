<?php
declare (strict_types = 1);

namespace app\product\controller;

use think\Request;
use think\facade\Db;
use think\facade\Queue;
use app\product\validate\IndexValidate;
use app\model\{
    User,
    Order,
    StaffRoles,
    RestaurantMenu,
    WjCustomerCoupon,
    RestaurantMenuTop,
    RestaurantCategory,
    ProducingBehaviorLog,
    DispatchingBehaviorLog,
    DispatchingProgressSummery
};

class Picking extends AuthBase
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

    //获取可配送的加工日期
    public function deliveryDate()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date']);
        $logistic_delivery_date = $param['logistic_delivery_date']??'';

        $DispatchingProgressSummery = new DispatchingProgressSummery();
        $businessId = $this->getBusinessId();
        $res = $DispatchingProgressSummery->getDeliveryDate($businessId,$logistic_delivery_date);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }

    //根据筛选日期获取初始化数据
    public function iniData()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','truck_sort','type','choose_logistic_truck_No']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['choose_logistic_truck_No'] = $param['choose_logistic_truck_No']??'';
        $param['truck_sort'] = $param['truck_sort']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $Order = new Order();
        $DispatchingProgressSummery = new DispatchingProgressSummery();
        //3.获取对应日期默认全部的司机的已加工订单数量和总的加工订单数量
        $driver_order_count = $DispatchingProgressSummery->getDispatchingOrderCount($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No']);
        //4.获取对应日期全部的已加工订单数量和总的加工订单数量
        $all_order_count = $DispatchingProgressSummery->getDispatchingOrderCount($businessId,$param['logistic_delivery_date']);
        //5.获取司机的信息
        $truck = $DispatchingProgressSummery->getDriversDeliveryData($businessId,$user_id,$param['logistic_delivery_date'],$param['choose_logistic_truck_No'],$param['truck_sort']);
        $data = [
            'truck' => $truck,
            'driver_order_count' => $driver_order_count,
            'all_order_count' => $all_order_count,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //切换司机获取对应的订单信息
    public function changeTruck()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','tw_sort','tw_sort_type']);
        $param['tw_sort'] = $param['tw_sort']??0;//排序字段
        $param['tw_sort_type'] = $param['tw_sort_type']??1;//1-正向排序 2-反向排序

        $businessId = $this->getBusinessId();

        $DispatchingProgressSummery = new DispatchingProgressSummery();

        //1.获取对应日期加司机的订单信息
        $user_id = $this->getMemberUserId();
        $order = $DispatchingProgressSummery->getOrderList($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['tw_sort'],$param['tw_sort_type']);
        //2.获取对应日期的司机的已加工订单数量和总的加工订单数量
        $driver_order_count = $DispatchingProgressSummery->getDispatchingOrderCount($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No']);
        $data = [
            'order' => $order,
            'driver_order_count' => $driver_order_count,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取订单明细信息
    public function productOrderDetailList()
    {
        //接收参数
        $param = $this->request->only(['orderId','wcc_sort','wcc_sort_type']);
        $param['wcc_sort'] = $param['wcc_sort']??0;//排序字段
        $param['wcc_sort_type'] = $param['wcc_sort_type']??1;//1-正向排序 2-反向排序

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $Order = new Order();

        //获取对应日期的加工订单
        $order = $Order->getProductOrderDetailList($businessId,$user_id,$param['orderId'],$param['wcc_sort'],$param['wcc_sort_type']);
        $data = [
            'order' => $order
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //锁定订单
    public function lockOrder()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','orderId']);
        $validate = new IndexValidate();
        if (!$validate->scene('lockOrder')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取订单信息
        $dps_info = DispatchingProgressSummery::getOne([
            'business_id' => $businessId,
            'delivery_date' => $param['logistic_delivery_date'],
            'orderId' => $param['orderId'],
            'isdeleted' => 0
        ]);
        if (!$dps_info) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }

        //添加队列
        $uniqid = uniqid(time().mt_rand(1,1000000), true);
        $jobData = [
            "uniqid" => $uniqid,
            "user_id" => $user_id,
            "dps_info" => $dps_info
        ];
        $isPushed = Queue::push('app\job\JobLockOrder', $jobData, 'lockOrder');
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
    public function lockProductResult()
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

    //获取锁定结果
    public function unlockOrder()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','orderId']);
        $validate = new IndexValidate();
        if (!$validate->scene('lockOrder')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取订单信息
        $dps_info = DispatchingProgressSummery::getOne([
            'business_id' => $businessId,
            'delivery_date' => $param['logistic_delivery_date'],
            'orderId' => $param['orderId'],
            'isdeleted' => 0
        ]);
        if (!$dps_info) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        //如果该产品已加工完，不可重复点击锁定解锁
        if ($dps_info['isDone'] == 1) {
            return show(config('status.code')['lock_processed_error']['code'], config('status.code')['lock_processed_error']['msg']);
        }
        //判断该产品是否是当前上锁人解锁的
        if ($dps_info['operator_user_id'] != $user_id) {
            return show(config('status.code')['unlock_user_error']['code'], config('status.code')['unlock_user_error']['msg']);
        }
        //解锁
        $res = DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],[
            'operator_user_id' => 0
        ]);
        if ($res) {
            $DispatchingBehaviorLog = new DispatchingBehaviorLog();
            $DispatchingBehaviorLog->addBehaviorLog($user_id,$businessId,2,$param['logistic_delivery_date'],$param);
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }

    //修改产品订单状态
    public function changeProductOrderStatus()
    {
        //接收参数
        $param = $this->request->only(['id','is_producing_done','logistic_truck_No']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
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
            $is_order_done = 0;//该订单对应的总量是否加工完毕
            $is_order_all_done = 0;//该司机是否所有订单都加工完毕

            //1.获取加工明细信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['dispatching_is_producing_done'] == $param['is_producing_done']){
                return show(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //1.查询该产品是否在汇总表中
            $dps_info = DispatchingProgressSummery::getOne([
                ['business_id','=',$businessId],
                ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                ['orderId','=',$wcc_info['order_id']],
                ['isdeleted','=',0],
            ],'id,finish_quantities,sum_quantities,operator_user_id,isDone');
            if(!$dps_info){
                return show(config('status.code')['summary_error']['code'], config('status.code')['summary_error']['msg']);
            }
            //一.已处理和正在处理流程
            if($param['is_producing_done'] == 1 || $param['is_producing_done'] == 2){
                //1-1.判断该产品是否有人加工，无人加工不可点击已处理
                if(!($dps_info['operator_user_id'] > 0)){
                    return show(config('status.code')['summary_process_error']['code'], config('status.code')['summary_process_error']['msg']);
                }
                //如果当前操作员工处理员工是否是同一个人
                if($dps_info['operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //1-2.该产品已处理完成，不可重复处理
                if($dps_info['isDone'] == $param['is_producing_done']){
                    return show(config('status.code')['repeat_done_error']['code'], config('status.code')['repeat_done_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['dispatching_operator_user_id'=>$user_id,'dispatching_is_producing_done'=>$param['is_producing_done']]);
                if($param['is_producing_done'] == 2){
                    Db::commit();
                    return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
                }
                $finish_quantities = $dps_info['finish_quantities']+1;
                $dps_data['finish_quantities'] = $finish_quantities;
                if($finish_quantities == $dps_info['sum_quantities']){
                    $dps_data['isDone'] = 1;
                    $is_order_done = 1;
                }
                DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
                //3.判断该订单是否全部加工完毕
                //如果该订单所有明细是否产全部拣货完毕，则更改订单状态
                $count = $WjCustomerCoupon->getWccDispatchingOrderDone($wcc_info['order_id']);
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
                //4.如果当前订单全部拣货完毕，判断当前司机的所有订单是否全部拣货完毕
                if($is_order_done == 1){
                    $isDone_arr = DispatchingProgressSummery::where([
                        ['business_id','=',$businessId],
                        ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                        ['truck_no','=',$wcc_info['logistic_truck_No']],
                        ['isdeleted','=',0],
                    ])->column('isDone');
                    if(!in_array(0,$isDone_arr)){
                        $is_order_all_done = 1;
                    }
                }
                Db::commit();
                $DispatchingBehaviorLog = new DispatchingBehaviorLog();
                $log_data = [
                    "orderId" => $wcc_info['order_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingBehaviorLog->addBehaviorLog($user_id,$businessId,3,$wcc_info['logistic_delivery_date'],$log_data);
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_order_done' => $is_order_done,//当前订单是否拣货完毕
                    'is_order_all_done' => $is_order_all_done //当前司机对应的订单是否全部拣货完毕
                ];
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
            }
            //二.返回继续处理流程
            if($param['is_producing_done'] == 0){
                //如果该产品被锁定时，判断当前操作员工处理员工是否是同一个人
                if($dps_info['isDone'] == 0 && $dps_info['operator_user_id'] > 0 && $dps_info['operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['dispatching_operator_user_id'=>$user_id,'dispatching_is_producing_done'=>0]);
                $finish_quantities = $dps_info['finish_quantities']-1;
                $dps_data['finish_quantities'] = $finish_quantities;
                $dps_data['operator_user_id'] = $user_id;
                //判断之前是否已加工完成，若加工完成，需要修改状态
                if($dps_info['finish_quantities'] == $dps_info['sum_quantities']){
                    $dps_data['isDone'] = 0;
                    $is_order_done = 0;
                }
                DispatchingProgressSummery::getUpdate(['id' => $dps_info['id']],$dps_data);
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
                //4.还原当前产品加工状态，未加工完
                $is_product_all_done = 0;
                Db::commit();
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_order_done' => $is_order_done,//当前订单是否拣货完毕
                    'is_order_all_done' => $is_order_all_done //当前司机对应的订单是否全部拣货完毕
                ];
                $DispatchingBehaviorLog = new DispatchingBehaviorLog();
                $log_data = [
                    "orderId" => $wcc_info['order_id'],
                    "wj_customer_coupon_id" => $param['id']
                ];
                $DispatchingBehaviorLog->addBehaviorLog($user_id,$businessId,4,$wcc_info['logistic_delivery_date'],$log_data);
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
                $ProducingBehaviorLog = new ProducingBehaviorLog();
                $log_data = [
                    "product_id" => $wcc_info['product_id'],
                    "guige1_id" => $wcc_info['guige1_id'],
                    "wj_customer_coupon_id" => $param['id'],
                    "new_customer_buying_quantity" => $param['new_customer_buying_quantity']
                ];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,5,$wcc_info['logistic_delivery_date'],$log_data);
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            } else {
                return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
            }
        } else {
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        }
    }

    //获取当前备货产品
    public function currentStockProduct()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $ProducingProgressSummery = new ProducingProgressSummery();
        $RestaurantCategory = new RestaurantCategory();

        //1.获取对应日期加工的商品信息
        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $goods = $ProducingProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],0,'',2);
        //按顺序获取产品大类
        $cate = $RestaurantCategory->getCategory($businessId);
        $cate_sort_arr = array_column($cate->toArray(),'id');
        $cate_id_arr = array_unique(array_column($goods,'cate_id'));
        $result = array_intersect($cate_sort_arr,$cate_id_arr);
        //将产品信息按照分类获取
        $data = [];
        foreach($goods as &$v){
            if(!isset($data[$v['cate_id']])){
                $data[$v['cate_id']] = [
                    'cate_id' => $v['cate_id'],
                    'category_en_name' => $v['category_en_name'],
                    'data' => []
                ];
            }
            $data[$v['cate_id']]['data'][] = $v;
        }
        $new_data = [];
        foreach($result as $cv){
            $new_data[$cv] = $data[$cv];
        }
        $data = array_values($new_data);
        $data = array_values($data);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取置顶产品信息
    public function topProduct()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        $RestaurantMenuTop = new RestaurantMenuTop();
        $data = $RestaurantMenuTop->getTopProduct($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No']);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //设置产品置顶
    public function setTopProduct()
    {
        $param = $this->request->only(['product_id','action_type']);
        //1.验证数据
        //validate验证机制
        $validate = new IndexValidate();
        if (!$validate->scene('setProductTop')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        if($param['action_type'] == 1){
            $data = [
                'userId' => $user_id,
                'business_userId' => $businessId,
                'product_id' => $param['product_id'],
                'created_at' => time()
            ];
            $res = RestaurantMenuTop::createData($data);
        } else {
            $where = [
                'userId' => $user_id,
                'business_userId' => $businessId,
                'product_id' => $param['product_id'],
            ];
            $info = RestaurantMenuTop::getOne($where);
            if($info){
                $res = RestaurantMenuTop::deleteAll(['id' => $info['id']]);
            }
        }
        if($res !== false){
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }

    }

    //获取加工产品类目
    public function category()
    {
        $businessId = $this->getBusinessId();
        $RestaurantCategory = new RestaurantCategory();
        $cate = $RestaurantCategory->getCategory($businessId);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$cate);
    }

    //获取对应大类的产品
    public function categoryProduct()
    {
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','goods_sort','category_id']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
        $param['goods_sort'] = $param['goods_sort']??0;
        $param['category_id'] = $param['category_id']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

//        $ProducingProgressSummery = new ProducingProgressSummery();
//        $data = $ProducingProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['goods_sort'],$param['category_id']);
        $RestaurantMenu = new RestaurantMenu();
        $data = $RestaurantMenu->getCateProduct($businessId,$user_id,$param['category_id']);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取日志数据
    public function logData()
    {
        $param = $this->request->only(['logistic_delivery_date','orderId','wcc_id']);
        $param['guige1_id'] = isset($param['guige1_id']) ? ($param['guige1_id']?:0) : 0;
        $param['wcc_id'] = isset($param['wcc_id']) ? ($param['wcc_id']?:0) : 0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();

        $DispatchingBehaviorLog = new DispatchingBehaviorLog();
        $res = $DispatchingBehaviorLog->getLogData($businessId,$user_id,$param);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }
}
