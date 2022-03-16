<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\product\validate\IndexValidate;
use think\facade\View;
use think\facade\Db;
use think\facade\Queue;
use app\model\{
    User,
    StaffRoles,
    OrderProductPlaning,
    ProducingPlaningSelect,
    OrderProductPlanningDetails,
    ProducingPlaningBehaviorLog,
    ProducingPlaningProgressSummery,
    RestaurantMenu
};

class preProduct extends AuthBase
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        // 模板输出
        return View::fetch('index');
    }

    //获取加工日期(前7天+后30天)
    public function deliveryDate()
    {
        $today_time = strtotime(date('Y-m-d',time()));
        $date_arr = [];
        $default = [];//默认选中今天的数据
        $default_k = 0;
        for ($i=7; $i>=0; $i--) {
            $date_time = strtotime( '-' . $i .' days', $today_time);
            $date = date('Y-m-d' ,$date_time);
            $data = [
                'date' => $date,
                'date_day' => date_day($date_time, $today_time),
                'is_default' => $i==0?1:2,
                'logistic_delivery_date' => $date_time,
            ];
            $date_arr[] = $data;
            if($i == 0){
                $default = $data;
                $default_k = count($date_arr)-1;
            }
        }
        for ($i=1; $i<=30; $i++) {
            $date_time = strtotime( '+' . $i .' days', $today_time);
            $date = date('Y-m-d' ,$date_time);
            $date_arr[] = [
                'date' => $date,
                'date_day' => date_day($date_time, $today_time),
                'is_default' => 2,
                'logistic_delivery_date' => $date_time,
            ];
        }
        $res = [
            'list' => $date_arr,
            'default' => $default,
            'default_k' => $default_k
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }

    //根据筛选日期获取初始化数据
    public function iniData()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','operator_user_id','goods_sort','product_id','type']);
        $param['operator_user_id'] = $param['operator_user_id']??'';
        $param['goods_sort'] = $param['goods_sort']??0;
        $param['product_id'] = $param['product_id']??0;

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        //如果是定时刷新信息，需要判断当前条件下信息是否有变动，如果有变动，则更新
//        if($param['type'] == 'refresh'){
//            $resdis = redis_connect();
//            $key = 'logistic_delivery_date_'.$param['logistic_delivery_date'].'logistic_truck_No_'.$param['logistic_truck_No'];
//            //获取最后一个操作该界面的用户，如果是同一用户，不需要更新。不同用户则更新
//            $change_user_id = $resdis->get($key);
//            if($user_id == $change_user_id){
//                return show(config('status.code')['no_need_refresh']['code'],config('status.code')['no_need_refresh']['msg']);
//            }
//        }
        $Order = new OrderProductPlaning();
        $User = new User();
        $ProducingProgressSummery = new ProducingPlaningProgressSummery();
        //1.获取对应日期的客户（目前默认先获取当前的商家）
        $nickname = User::getVal(['id' => $businessId],'nickname');
        //2.获取对应日期的加工员工
        $all_operator = $User->getUsers($businessId);
        //3.获取对应日期默认全部的司机的已加工订单数量和总的加工订单数量
        $operator_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date'],$param['operator_user_id']);
        //4.获取对应日期全部的已加工订单数量和总的加工订单数量
        $all_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date']);
        //5.获取对应日期加工的商品信息
        $goods = $ProducingProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['operator_user_id'],$param['goods_sort']);
        $data = [
            'all_customers' => [$nickname],
            'all_operator' => $all_operator,
            'goods' => $goods,
            'operator_order_count' => $operator_order_count,
            'all_order_count' => $all_order_count,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //切换商品获取对应的二级类目
    public function changeGoods()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','operator_user_id','product_id']);
        $param['operator_user_id'] = $param['operator_user_id']??'';
        $param['guige1_id'] = $param['guige1_id']??'';

        $businessId = $this->getBusinessId();

        $ProducingProgressSummery = new ProducingPlaningProgressSummery();

        //5.获取对应日期加工的商品信息
        $user_id = $this->getMemberUserId();
        $goods = $ProducingProgressSummery->getGoodsTwoCate($businessId,$user_id,$param['logistic_delivery_date'],$param['operator_user_id'],$param['product_id']);

        $data = [
            'goods_two_cate' => $goods,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取加工明细单数据
    public function productOrderList()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','operator_user_id','product_id','guige1_id','wcc_sort','wcc_sort_type']);
        $param['operator_user_id'] = $param['operator_user_id']??'';
        $param['guige1_id'] = $param['guige1_id']??'';
        $param['wcc_sort'] = $param['wcc_sort']??0;//排序字段
        $param['wcc_sort_type'] = $param['wcc_sort_type']??1;//1-正向排序 2-反向排序

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        $Order = new OrderProductPlaning();

        //获取对应日期的加工订单
        $order = $Order->getProductOrderList($businessId,$user_id,$param['logistic_delivery_date'],$param['operator_user_id'],$param['product_id'],$param['guige1_id'],$param['wcc_sort'],$param['wcc_sort_type']);
        $data = [
            'order' => $order
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //锁定产品
    public function lockProduct()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','product_id','guige1_id']);
        $param['guige1_id'] = $param['guige1_id']??0;
        $validate = new IndexValidate();
        if (!$validate->scene('lockProduct')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取加工产品信息
        $pps_info = ProducingPlaningProgressSummery::getOne([
            'business_userId' => $businessId,
            'delivery_date' => $param['logistic_delivery_date'],
            'product_id' => $param['product_id'],
            'guige1_id' => $param['guige1_id'],
            'isdeleted' => 0
        ]);
        if (!$pps_info) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }

        //添加队列
        $uniqid = uniqid(time().mt_rand(1,1000000), true);
        $jobData = [
            "uniqid" => $uniqid,
            "user_id" => $user_id,
            "pps_info" => $pps_info
        ];
        $isPushed = Queue::push('app\job\JobLockProductPlaning', $jobData, 'lockProductPlaning');
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
    public function unlockProduct()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','product_id','guige1_id']);
        $param['guige1_id'] = $param['guige1_id']??0;
        $validate = new IndexValidate();
        if (!$validate->scene('lockProduct')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();//当前操作用户

        //1.获取加工产品信息
        $pps_info = ProducingPlaningProgressSummery::getOne([
            'business_userId' => $businessId,
            'delivery_date' => $param['logistic_delivery_date'],
            'product_id' => $param['product_id'],
            'guige1_id' => $param['guige1_id'],
            'isdeleted' => 0
        ]);
        if (!$pps_info) {
            return show(config('status.code')['param_error']['code'], config('status.code')['param_error']['msg']);
        }
        //如果该产品已加工完，不可重复点击锁定解锁
        if ($pps_info['isDone'] == 1) {
            return show(config('status.code')['lock_processed_error']['code'], config('status.code')['lock_processed_error']['msg']);
        }
        //判断该产品是否是当前上锁人解锁的
        if ($pps_info['operator_user_id'] != $user_id) {
            return show(config('status.code')['unlock_user_error']['code'], config('status.code')['unlock_user_error']['msg']);
        }
        //解锁
        $res = ProducingPlaningProgressSummery::getUpdate(['id' => $pps_info['id']],[
            'operator_user_id' => 0
        ]);
        if ($res) {
            $ProducingBehaviorLog = new ProducingPlaningBehaviorLog();
            $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,2,$param['logistic_delivery_date'],$param);
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }


    //修改产品订单状态
    public function changeProductOrderStatus()
    {
        //接收参数
        $param = $this->request->only(['id','is_producing_done','operator_user_id']);
        $param['operator_user_id'] = $param['operator_user_id']??'';
        $validate = new IndexValidate();
        if (!$validate->scene('changeProductOrderStatus')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        try{
            Db::startTrans();

            $businessId = $this->getBusinessId();
            $user_id = $this->getMemberUserId();//当前操作用户
            $order_inc_num = 0;//加工完成订单数据新增
            $operator_order_inc_num = 0;//对应的操作员加工订单数据新增
            $is_product_guige1_done = 0;//该产品/规格对应的总量是否加工完毕
            $is_product_all_done = 0;//该产品是否所有规则都加工完毕

            //1.获取加工明细信息
            $OrderProductPlaningDetails = new OrderProductPlanningDetails();
            $wcc_info = $OrderProductPlaningDetails->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['is_producing_done'] == $param['is_producing_done']){
                return show(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //1.查询该产品是否在汇总表中
            $pps_info = ProducingPlaningProgressSummery::getOne([
                ['business_userId','=',$businessId],
                ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                ['product_id','=',$wcc_info['product_id']],
                ['guige1_id','=',$wcc_info['guige1_id']],
                ['isdeleted','=',0],
            ],'id,finish_quantities,sum_quantities,operator_user_id,isDone');
            if(!$pps_info){
                return show(config('status.code')['summary_error']['code'], config('status.code')['summary_error']['msg']);
            }
            //一.已处理和正在处理流程
            if($param['is_producing_done'] == 1 || $param['is_producing_done'] == 2){
                //1-1.判断该产品是否有人加工，无人加工不可点击已处理
                if(!($pps_info['operator_user_id'] > 0)){
                    return show(config('status.code')['summary_process_error']['code'], config('status.code')['summary_process_error']['msg']);
                }
                //如果当前操作员工处理员工是否是同一个人
                if($pps_info['operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //1-2.该产品已处理完成，不可重复处理
                if($pps_info['isDone'] == $param['is_producing_done']){
                    return show(config('status.code')['repeat_done_error']['code'], config('status.code')['repeat_done_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                OrderProductPlanningDetails::getUpdate(['id' => $wcc_info['id']],['operator_user_id'=>$user_id,'is_producing_done'=>$param['is_producing_done']]);
                if($param['is_producing_done'] == 2){
                    Db::commit();
                    return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
                }
                $finish_quantities = $pps_info['finish_quantities']+$wcc_info['customer_buying_quantity'];
                $pps_data['finish_quantities'] = $finish_quantities;
                if($finish_quantities == $pps_info['sum_quantities']){
                    $pps_data['isDone'] = 1;
                    $is_product_guige1_done = 1;
                }
                ProducingPlaningProgressSummery::getUpdate(['id' => $pps_info['id']],$pps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则更改订单加工状态
                $count = $OrderProductPlaningDetails->getWccOrderDone($wcc_info['order_id']);
                if($count == 0){
                    OrderProductPlaning::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'is_producing_done'=>1
                    ]);
                    $order_inc_num = 1;//待加工产品全部完工，订单数+1
                    //判断对应操作员的订单数，如果操作员信息一致，则操作员订单+1
                    if($param['operator_user_id']>0){
                        if($wcc_info['operator_user_id'] == $param['operator_user_id']){
                            $operator_order_inc_num = 1;
                        }else{
                            $operator_order_inc_num = 0;
                        }
                    }else{
                        $operator_order_inc_num = 1;
                    }
                }
                //4.如果当前规格加工完毕，判断当前产品是否全部加工完毕
                if($is_product_guige1_done == 1){
                    $isDone_arr = ProducingPlaningProgressSummery::where([
                        ['business_userId','=',$businessId],
                        ['delivery_date','=',$wcc_info['logistic_delivery_date']],
                        ['product_id','=',$wcc_info['product_id']],
                        ['isdeleted','=',0],
                    ])->column('isDone');
                    if(!in_array(0,$isDone_arr)){
                        $is_product_all_done = 1;
                    }
                }
                Db::commit();
                $ProducingBehaviorLog = new ProducingPlaningBehaviorLog();
                $log_data = [
                    "order_product_planning_details_id" => $param['id']
                ];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,3,$wcc_info['logistic_delivery_date'],$log_data);
                $data = [
                    'operator_order_inc_num' => $operator_order_inc_num,//对应司机的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_all_done' => $is_product_all_done //当前产品（包括所有规格）是否全部加工完毕
                ];
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
            }
            //二.返回继续处理流程
            if($param['is_producing_done'] == 0){
                //如果该产品被锁定时，判断当前操作员工处理员工是否是同一个人
                if($pps_info['isDone'] == 0 && $pps_info['operator_user_id'] > 0 && $pps_info['operator_user_id'] != $user_id){
                    return show(config('status.code')['lock_user_deal_error']['code'], config('status.code')['lock_user_deal_error']['msg']);
                }
                //2.更新该产品加工数量和状态
                OrderProductPlanningDetails::getUpdate(['id' => $wcc_info['id']],['operator_user_id'=>$user_id,'is_producing_done'=>0]);
                $finish_quantities = $pps_info['finish_quantities']-$wcc_info['customer_buying_quantity'];
                $pps_data['finish_quantities'] = $finish_quantities;
                $pps_data['operator_user_id'] = $user_id;
                //判断之前是否已加工完成，若加工完成，需要修改状态
                if($pps_info['finish_quantities'] == $pps_info['sum_quantities']){
                    $pps_data['isDone'] = 0;
                    $is_product_guige1_done = 0;
                }
                ProducingPlaningProgressSummery::getUpdate(['id' => $pps_info['id']],$pps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则需还原更改订单加工状态
                if($wcc_info['order_is_producing_done'] == 1){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'is_producing_done'=>0
                    ]);
                    $order_inc_num = -1;
                    //判断对应操作员的订单数，如果操作员信息一致，则操作员订单-1
                    if($param['operator_user_id']>0){
                        if($wcc_info['operator_user_id'] == $param['operator_user_id']){
                            $operator_order_inc_num = -1;
                        }else{
                            $operator_order_inc_num = 0;
                        }
                    }else{
                        $operator_order_inc_num = -1;
                    }
                }
                //4.还原当前产品加工状态，未加工完
                $is_product_all_done = 0;
                Db::commit();
                $data = [
                    'operator_order_inc_num' => $operator_order_inc_num,//对应操作员的订单数是否增加
                    'order_inc_num' => $order_inc_num,//订单总数是否增加
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_all_done' => $is_product_all_done //当前产品（包括所有规格）是否全部加工完毕
                ];
                $ProducingBehaviorLog = new ProducingPlaningBehaviorLog();
                $log_data = [
                    "order_product_planning_details_id" => $param['id']
                ];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,4,$wcc_info['logistic_delivery_date'],$log_data);
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
        $OrderProductPlaningDetails = new OrderProductPlanningDetails();
        $wcc_info = $OrderProductPlaningDetails->getWccInfo($param['id'],$businessId);
        if (!$wcc_info) {
            return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
        }

        //2.更新该产品加工数量和状态
        if($wcc_info['new_customer_buying_quantity'] != $param['new_customer_buying_quantity']){
            $res = OrderProductPlanningDetails::getUpdate(['id' => $wcc_info['id']],['new_customer_buying_quantity'=>$param['new_customer_buying_quantity']]);
            if ($res) {
                $ProducingBehaviorLog = new ProducingPlaningBehaviorLog();
                $log_data = [
                    "order_product_planning_details_id" => $param['id'],
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
        $param = $this->request->only(['logistic_delivery_date','operator_user_id']);
        $param['operator_user_id'] = $param['operator_user_id']??'';
        $ProducingPlaningProgressSummery = new ProducingPlaningProgressSummery();

        //1.获取对应日期加工的商品信息
        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $goods = $ProducingPlaningProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['operator_user_id']);
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
        $data = array_values($data);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //选择需要预加工的产品
    public function setProductPlaning()
    {
        $param = $this->request->only(['logistic_delivery_date','product_id','action_type']);
        //1.验证数据
        //validate验证机制
        $validate = new IndexValidate();
        if (!$validate->scene('setProductPlaning')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $StaffRoles = new StaffRoles();

        //1.判断当前用户是都有权限添加预加工
        $isPermission = $StaffRoles->getProductPlaningPermission($user_id);
        if ($isPermission != 1) {
            return show(config('status.code')['product_plan_approved_error']['code'],config('status.code')['product_plan_approved_error']['msg']);
        }
        //2.判断该产品是否存在
        $where = [
            'delivery_date' => $param['logistic_delivery_date'],
            'userId' => $user_id,
            'business_userId' => $businessId,
            'product_id' => $param['product_id'],
        ];
        $info = ProducingPlaningSelect::getOne($where);

        if($param['action_type'] == 1){
            if(!empty($info)){
                return show(config('status.code')['product_plan_has_add']['code'],config('status.code')['product_plan_has_add']['msg']);
            }
            $data = [
                'userId' => $user_id,
                'business_userId' => $businessId,
                'delivery_date' => $param['logistic_delivery_date'],
                'product_id' => $param['product_id'],
                'created_at' => date('Y-m-d H:i:s',time())
            ];
            $res = ProducingPlaningSelect::createData($data);
        } else {
            if($info){
                $res = ProducingPlaningSelect::deleteAll(['id' => $info['id']]);
            }
        }
        if($res !== false){
            if ($param['action_type'] == 1) {
                $message = 'Added successfully';
            } else {
                $message = 'successfully deleted';
            }
            return show(config('status.code')['success']['code'],$message);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }

    //添加预加工订单
    public function addOrderProductPlaning()
    {
        $param = $this->request->only(['logistic_delivery_date','product_id','guige1_id','quantity','action_type']);
        $param['guige1_id'] = $param['guige1_id']?:0;
        //1.验证数据
        //validate验证机制
        $validate = new IndexValidate();
        if (!$validate->scene('addOrderProductPlaning')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }

        $businessId = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $StaffRoles = new StaffRoles();
        $RestaurantMenu= new RestaurantMenu();
        $ProducingBehaviorLog = new ProducingPlaningBehaviorLog();
        $ProducingPlaningProgressSummery = new ProducingPlaningProgressSummery();

        //1.判断当前用户是都有权限添加预加工
        $isPermission = $StaffRoles->getProductPlaningPermission($user_id);
        if ($isPermission != 1) {
            return show(config('status.code')['product_plan_approved_error']['code'],config('status.code')['product_plan_approved_error']['msg']);
        }
        //2.判断今日订单是否存在
        $info = Db::name('order_product_planning_details')
            ->alias('oppd')
            ->field('opp.orderId,oppd.id,oppd.customer_buying_quantity')
            ->leftJoin('order_product_planing opp','opp.orderId = oppd.order_id')
            ->where([
                'logistic_delivery_date' => $param['logistic_delivery_date'],
                'business_userId' => $businessId,
                'restaurant_menu_id' => $param['product_id'],
                'guige1_id' => $param['guige1_id'],
            ])->find();
        try {
            Db::startTrans();
            //1-新增 2-编辑
            if($param['action_type'] == 1){
                if($param['quantity']<=0){
                    return show(config('status.code')['quantity_error']['code'],config('status.code')['quantity_error']['msg']);
                }
                $time = time();
                if(empty($info)){
                    $order_id = makeNum();
                    $order_data = [
                        'orderId' => $order_id,
                        'userId' => $user_id,
                        'money' => 0,
                        'createTime' => $time,
                        'createIp' => request()->ip(),
                        'status' => 1,
                        'business_userId' => $businessId,
                        'coupon_status' => 'c01',
                        'logistic_delivery_date' => $param['logistic_delivery_date'],
                        'accountPay' => 1,
                        'payment' => 'offline',
                        'paytime' => $time,
                        'txn_id' => '',
                        'txn_result' => '',
                        'displayName' => '',
                        'house_number' => '',
                        'street' => '',
                        'city' => '',
                        'state' => '',
                        'id_number' => '',
                        'promotion_id' => 0,
                        'rated' => 0,
                        'tracking_id' => '',
                        'tracking_operator' => '',
                        'logistic_truck_No' => 0,
                        'logistic_sequence_No' => 0,
                        'logistic_stop_No' => 0,
                        'logistic_delivery_time_type' => 0,
                        'logisitic_schedule_time' => 0,
                        'logistic_delay_time' => 0,
                        'logistic_arrived_time' => 0,
                        'logistic_driver_code' => 0,
                        'logistic_suppliers_info' => '',
                        'freshx_order_id' => 0
                    ];
                    OrderProductPlaning::createData($order_data);
                } else {
                    $order_id = $info['orderId'];
                }
                //查询产品信息
                $goods_info = $RestaurantMenu->getMenuData($param['product_id'],$param['guige1_id']);
                if(empty($goods_info)){
                    return show(config('status.code')['product_error']['code'],config('status.code')['product_error']['msg']);
                }
//                //查询订单中该加工明细是否存在
//                $oppd_info = OrderProductPlanningDetails::getOne([
//                    ['order_id','=',$order_id],
//                    ['userId','=',$user_id],
//                    ['restaurant_menu_id','=',$param['product_id']],
//                    ['guige1_id','=',$param['guige1_id']]
//                ]);
//                if(!empty($oppd_info)){
//                    return show(config('status.code')['order_product_exists']['code'],config('status.code')['order_product_exists']['msg']);
//                }
                $goods_data = [
                    'restaurant_menu_id' => $param['product_id'],
                    'order_id' => $order_id,
                    'gen_date' => $time,
                    'userId' => $user_id,
                    'coupon_status' => 'c01',
                    'bonus_title' => $goods_info['menu_cn_name'],
                    'customer_buying_quantity' => $param['quantity'],
                    'menu_id' => $goods_info['menu_id'],
                    'guige1_id' => $param['guige1_id'],
                    'business_id' => $businessId,
                    'bonus_id' => 0,
                    'platform_commission_rate' => 0,
                    'platform_commission_base' => 0,
                    'adjust_subtotal_amount' => 0
                ];
                $order_product_planning_details_id = Db::name('order_product_planning_details')->insertGetId($goods_data);
                //同时将该加工明细信息加入加工汇总表中
                $ppps_data = [
                    'business_userId' => $businessId,
                    'delivery_date' => $param['logistic_delivery_date'],
                    'product_id' => $param['product_id'],
                    'guige1_id' => $param['guige1_id'],
                    'sum_quantities' => $param['quantity'],
                    'proucing_center_id' => 0
                ];
                $ProducingPlaningProgressSummery->addProgressSummary($ppps_data);
                Db::commit();
                $data = $param;
                $data['order_product_planning_details_id'] = $order_product_planning_details_id;
                $data['new_customer_buying_quantity'] = $param['quantity'];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,6,$data['logistic_delivery_date'],$data);
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            } else {
                if(empty($info)){
                    return show(config('status.code')['order_not_exists']['code'],config('status.code')['order_not_exists']['msg']);
                }
                //查询产品信息
                $goods_info = $RestaurantMenu->getMenuData($param['product_id'],$param['guige1_id']);
                if(empty($goods_info)){
                    return show(config('status.code')['product_error']['code'],config('status.code')['product_error']['msg']);
                }
                if($info['customer_buying_quantity'] != $param['quantity']){
                    OrderProductPlanningDetails::getUpdate(['id'=>$info['id']],[
                        'customer_buying_quantity' => $param['quantity'],
                        'new_customer_buying_quantity' => $param['quantity'],
                    ]);
                    //同时将该加工明细信息加入加工汇总表中
                    $ppps_data = [
                        'business_userId' => $businessId,
                        'delivery_date' => $param['logistic_delivery_date'],
                        'product_id' => $param['product_id'],
                        'guige1_id' => $param['guige1_id'],
                        'sum_quantities' => $param['quantity'],
                        'proucing_center_id' => 0
                    ];
                    $ProducingPlaningProgressSummery->addProgressSummary($ppps_data);
                }
                Db::commit();
                if($info['customer_buying_quantity'] != $param['quantity']) {
                    //添加修改日志
                    $data = $param;
                    $data['order_product_planning_details_id'] = $info['id'];
                    $data['new_customer_buying_quantity'] = $param['quantity'];
                    $ProducingBehaviorLog->addProducingBehaviorLog($user_id, $businessId, 7, $data['logistic_delivery_date'], $data);
                }
                return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return show(config('status.code')['system_error']['code'], $e->getMessage());
        }
    }
}
