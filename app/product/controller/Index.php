<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\product\validate\LoginValidate;
use app\product\validate\IndexValidate;
use app\common\service\RedisService;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Queue;
use think\facade\View;
use app\model\{
    User,
    Order,
    StaffRoles,
    RestaurantMenu,
    WjCustomerCoupon,
    RestaurantMenuTop,
    RestaurantCategory,
    ProducingBehaviorLog,
    ProducingProgressSummery
};

class Index extends AuthBase
{
    public function index()
    {
        // 模板输出
        return View::fetch('index');
    }

    public function test()
    {
//        $config = config('cache.stores')['redis'];
//        $config['auth'] = $config['password'];
//        $attr = [
//            'db_id' => $config['select'],
//            'timeout' => $config['timeout'],
//        ];
//        $res = RedisService::getInstance($config,$attr);
        $res = redis_connect();
        $res->hIncrBy('pincode_error','1',1);
    }

    //退出登录
    public function loginOut()
    {
        $this->clearCookie();
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
    }

    //pincode登录
    public function loginByPincode()
    {
        //接收参数
        $param = $this->request->only(['pincode']);

        //1.验证数据
        //validate验证机制
        $validate = new LoginValidate();
        if (!$validate->scene('loginByPincode')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $pincode = trim($param['pincode']);

        //2.查找该pincode是否在该供应商中存在
        $business_id = $this->getBusinessId();
        $map = "pincode=$pincode and (role=3 and id=$business_id or role=20 and user_belong_to_user=$business_id)";
        $user = User::getOne($map, 'id,name,nickname,password,role,user_belong_to_user,pincode');
        //如果未找到，记录pincode输入错误次数,错误此时大于6次，需要锁屏一分钟
        if (!$user) {
            $error = $this->getUserPincodeError();
            if (!$error) {
                $error = 1;
            } else {
                $error += 1;
            }
            if ($error > 5)  {
                Cookie::set('user_pincode_error',$error,60);
                return show(config('status.code')['pincode_error_limit']['code'],config('status.code')['pincode_error_limit']['msg']);
            } else {
                Cookie::set('user_pincode_error',$error);
                return show(config('status.code')['pincode_error']['code'],config('status.code')['pincode_error']['msg']);
            }
        }

        //3.该供应商用户存在，比较当前登录用户和pincode是否一致，一致则可以直接登录，不一致则需要强制登录当前账号
        $data = $this->getLoginInfo($user);
        if ($user['id'] == $this->getMemberUserId()) {
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        } else {
            Cookie::set('remember_user_id', $user['id'], 60 * 60 * 24 * 365);
            Cookie::set('remember_user_shell', md5( $user['id'].$user['name'].$user['password'] ), 60 * 60 * 24 * 365);
            if($user['role'] == 20){
                $business_id = $user['user_belong_to_user'];
            } else {
                $business_id = $user['id'];
            }
            Cookie::set('business_id', $business_id, 60 * 60 * 24 * 365);
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        }
    }

    //设置pincode
    public function setPincode()
    {
        //接收参数
        $param = $this->request->only(['new_pincode','sure_pincode']);

        //1.验证数据
        //validate验证机制
        $validate = new LoginValidate();
        if (!$validate->scene('setPincode')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $new_pincode = trim($param['new_pincode']);
        $sure_pincode = trim($param['sure_pincode']);
        if($new_pincode != $sure_pincode){
            return show(config('status.code')['match_pincode']['code'],config('status.code')['match_pincode']['msg']);
        }

        //2.查询该供应商下pincode是否已被占用
        $business_id = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $map = "id!=$user_id and pincode=$new_pincode and (role=3 and id=$business_id or role=20 and user_belong_to_user=$business_id)";
        $user = User::getOne($map,'id');
        if ($user) {
            return show(config('status.code')['pincode_exist']['code'],config('status.code')['pincode_exist']['msg']);
        }

        //3.更新pincode,并返回登录信息
        $user = User::getOne(['id' => $user_id], 'id,name,nickname,role,user_belong_to_user,pincode');
        $data = $this->getLoginInfo($user);
        $res = User::getUpdate(['id' => $user_id], ['pincode' => $new_pincode]);
        if ($res !== false) {
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }

    //修改pincode
    public function editPincode()
    {
        //接收参数
        $param = $this->request->only(['pincode','new_pincode','sure_pincode']);

        //1.验证数据
        //validate验证机制
        $validate = new LoginValidate();
        if (!$validate->scene('editPincode')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $pincode = trim($param['pincode']);
        $new_pincode = trim($param['new_pincode']);
        $sure_pincode = trim($param['sure_pincode']);
        if($new_pincode != $sure_pincode){
            return show(config('status.code')['match_pincode']['code'],config('status.code')['match_pincode']['msg']);
        }

        //2.查询原pincode是否正确
        $business_id = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $user = User::getOne(['id' => $user_id,'pincode' => $pincode], 'id,name,nickname,role,user_belong_to_user');
        if (!$user) {
            return show(config('status.code')['old_pincode_error']['code'],config('status.code')['old_pincode_error']['msg']);
        }

        //3.查询该供应商下pincode是否已被占用
        $map = "id!=$user_id and pincode=$new_pincode and (role=3 and id=$business_id or role=20 and user_belong_to_user=$business_id)";
        $user = User::getOne($map,'id');
        if ($user) {
            return show(config('status.code')['pincode_exist']['code'],config('status.code')['pincode_exist']['msg']);
        }

        //4.更新pincode,并返回登录信息
        $user = User::getOne(['id' => $user_id], 'id,name,nickname,role,user_belong_to_user,pincode');
        $data = $this->getLoginInfo($user);
        $res = User::getUpdate(['id' => $user_id], ['pincode' => $new_pincode]);
        if ($res !== false) {
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        } else {
            return show(config('status.code')['system_error']['code'],config('status.code')['system_error']['msg']);
        }
    }

    //获取用户登录信息
    public function loginInfo()
    {
        $user_id = $this->getMemberUserId();
        $user = User::getOne(['id' => $user_id], 'id,name,nickname,role,user_belong_to_user,pincode');
        $data = $this->getLoginInfo($user,2);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /*
     * 公共方法，获取用户登录信息
     * @param $user 用户信息
     * @param $type 1:存储用户信息 2:获取用户信息
     * */
    public function getLoginInfo($user,$type=1)
    {
        if($type == 1){
            $user_name = $user['nickname'] ?: $user['name'];
            Cookie::set('user_name',$user_name);
        }else{
            $user_name = $this->getUserName();
        }
        $businessId = $this->getBusinessId();
        $business_name = User::getVal(['id' => $businessId],'nickname');

        $User = new User();
        $StaffRoles = new StaffRoles();
        $isPermission = $StaffRoles->getProductPlaningPermission($user['id']);
        $data = [
            "user_name" => $user_name,
            "business_name" => $business_name,
            "is_has_pincode" => $user['pincode'] ? 1 : 2,//是否设置pincode，1设置 2未设置
            "is_manager" => $isPermission,//判断用户是否是管理员
            "user_info" => $User->getUsers($businessId,$user['id']),//获取当前用户信息
        ];
        return $data;
    }

    //退出pincode登录
    public function loginOutPincode()
    {
        if($this->getUserName()){
            $this->clearPincodeCookie();
        }
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
    }

    //获取可配送的加工日期
    public function deliveryDate()
    {
        $Order = new Order();
        $businessId = $this->getBusinessId();
        $res = $Order->getDeliveryDate($businessId);
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$res);
    }


    /**
     * 获取客户信息
     * @return \think\response\Json
     */
    public function customer()
    {
        //1.获取对应日期的客户（目前默认先获取当前的商家）
        $businessId = $this->getBusinessId();
        $info = User::getOne(['id' => $businessId],'id,nickname name');
        $data = [
            'all_customers' => [$info]
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    /**
     * 获取司机信息
     * @return \think\response\Json
     */
    public function drivers()
    {
        $param = $this->request->only(['logistic_delivery_date']);

        $businessId = $this->getBusinessId();
        $Order = new Order();
        //获取对应日期的配送司机
        $all_drivers = $Order->getDrivers($businessId,$param['logistic_delivery_date']);
        $data = [
            'all_drivers' => $all_drivers
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //根据筛选日期获取初始化数据
    public function iniData()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','goods_sort','product_id','type']);
        $param['logistic_truck_No'] = $param['logistic_truck_No']??'';
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
        $Order = new Order();
        $ProducingProgressSummery = new ProducingProgressSummery();
        //3.获取对应日期默认全部的司机的已加工订单数量和总的加工订单数量
        $driver_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No']);
        //4.获取对应日期全部的已加工订单数量和总的加工订单数量
        $all_order_count = $Order->getOrderCount($businessId,$param['logistic_delivery_date']);
        //5.获取对应日期加工的商品信息
        $goods = $ProducingProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['goods_sort']);
        //        //6.获取对应日期的加工明细信息
//        $order = $Order->getProductOrderList($businessId,$param['logistic_delivery_date'],$param['logistic_truck_No']);
        $data = [
            'goods' => $goods,
            'driver_order_count' => $driver_order_count,
            'all_order_count' => $all_order_count,
//            'order' => $order
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //切换商品获取对应的二级类目
    public function changeGoods()
    {
        //接收参数
        $param = $this->request->only(['logistic_delivery_date','logistic_truck_No','product_id']);
        $param['guige1_id'] = $param['guige1_id']??'';

        $businessId = $this->getBusinessId();

        $ProducingProgressSummery = new ProducingProgressSummery();

        //5.获取对应日期加工的商品信息
        $user_id = $this->getMemberUserId();
        $goods = $ProducingProgressSummery->getGoodsTwoCate($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id']);

        $data = [
            'goods_two_cate' => $goods,
        ];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //获取加工明细单数据
    public function productOrderList()
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
        $order = $Order->getProductOrderList($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No'],$param['product_id'],$param['guige1_id'],$param['wcc_sort'],$param['wcc_sort_type']);
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
        $pps_info = ProducingProgressSummery::getOne([
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
        $isPushed = Queue::push('app\job\JobLockProduct', $jobData, 'lockProduct');
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
        $pps_info = ProducingProgressSummery::getOne([
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
        $res = ProducingProgressSummery::getUpdate(['id' => $pps_info['id']],[
            'operator_user_id' => 0
        ]);
        if ($res) {
            $ProducingBehaviorLog = new ProducingBehaviorLog();
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
            $is_product_guige1_done = 0;//该产品/规格对应的总量是否加工完毕
            $is_product_all_done = 0;//该产品是否所有规则都加工完毕

            //1.获取加工明细信息
            $WjCustomerCoupon = new WjCustomerCoupon();
            $wcc_info = $WjCustomerCoupon->getWccInfo($param['id'],$businessId);
            if (!$wcc_info) {
                return show(config('status.code')['order_error']['code'], config('status.code')['order_error']['msg']);
            }
            if($wcc_info['is_producing_done'] == $param['is_producing_done']){
                return show(config('status.code')['summary_done_error']['code'], config('status.code')['summary_done_error']['msg']);
            }
            //1.查询该产品是否在汇总表中
            $pps_info = ProducingProgressSummery::getOne([
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
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['operator_user_id'=>$user_id,'is_producing_done'=>$param['is_producing_done']]);
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
                ProducingProgressSummery::getUpdate(['id' => $pps_info['id']],$pps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则更改订单加工状态
                $count = $WjCustomerCoupon->getWccOrderDone($wcc_info['order_id']);
                if($count == 0){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'is_producing_done'=>1
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
                //4.如果当前规格加工完毕，判断当前产品是否全部加工完毕
                if($is_product_guige1_done == 1){
                    $isDone_arr = ProducingProgressSummery::where([
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
                $ProducingBehaviorLog = new ProducingBehaviorLog();
                $log_data = [
                    "wj_customer_coupon_id" => $param['id']
                ];
                $ProducingBehaviorLog->addProducingBehaviorLog($user_id,$businessId,3,$wcc_info['logistic_delivery_date'],$log_data);
                $data = [
                    'driver_order_inc_num' => $driver_order_inc_num,//对应司机的订单数是否增加
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
                WjCustomerCoupon::getUpdate(['id' => $wcc_info['id']],['operator_user_id'=>$user_id,'is_producing_done'=>0]);
                $finish_quantities = $pps_info['finish_quantities']-$wcc_info['customer_buying_quantity'];
                $pps_data['finish_quantities'] = $finish_quantities;
                $pps_data['operator_user_id'] = $user_id;
                //判断之前是否已加工完成，若加工完成，需要修改状态
                if($pps_info['finish_quantities'] == $pps_info['sum_quantities']){
                    $pps_data['isDone'] = 0;
                    $is_product_guige1_done = 0;
                }
                ProducingProgressSummery::getUpdate(['id' => $pps_info['id']],$pps_data);
                //3.判断该订单是否全部加工完毕
                //如果该产品对应规则的产品全部加工完毕，则需还原更改订单加工状态
                if($wcc_info['order_is_producing_done'] == 1){
                    Order::getUpdate(['orderId' => $wcc_info['order_id']],[
                        'is_producing_done'=>0
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
                    'is_product_guige1_done' => $is_product_guige1_done,//当前加工产品对应的规格（没有规格即当前产品）是否加工完毕
                    'is_product_all_done' => $is_product_all_done //当前产品（包括所有规格）是否全部加工完毕
                ];
                $ProducingBehaviorLog = new ProducingBehaviorLog();
                $log_data = [
                    "wj_customer_coupon_id" => $param['id']
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
        $goods = $ProducingProgressSummery->getGoodsOneCate($businessId,$user_id,$param['logistic_delivery_date'],$param['logistic_truck_No']);
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

    //打印程序页面
    public function labelprint()
    {
        // 模板输出
        return View::fetch('labelprint');
    }


}
