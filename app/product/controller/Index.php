<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\product\validate\LoginValidate;
use app\common\service\RedisService;
use think\facade\Cookie;
use think\facade\View;
use app\model\{
    User,
    Order
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
        $user = User::getOne($map, 'id,name,nickname,role,user_belong_to_user,pincode');
        //如果未找到，记录pincode输入错误次数,错误此时大于6次，需要锁屏一分钟
        if (!$user) {
            $error = $this->getUserPincodeError();
            if (!$error) {
                $error = 1;
            } else {
                $error += 1;
            }
            if ($error > 5) {
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

        //2.查询原pincode是否正确
        $business_id = $this->getBusinessId();
        $user_id = $this->getMemberUserId();
        $user = User::getOne(['id' => $user_id,'pincode' => $pincode], 'id,name,nickname,role,user_belong_to_user');
        if (!$user) {
            return show(config('status.code')['pincode_error']['code'],config('status.code')['pincode_error']['msg']);
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
        $data = [
            "user_name" => $user_name,
            "is_has_pincode" => $user['pincode'] ? 1 : 2//是否设置pincode，1设置 2未设置
        ];
        return $data;
    }

    //退出pincode登录
    public function loginOutPincode()
    {
        $this->clearPincodeCookie();
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
    }

    //获取可配送的加工日期
    public function deliveryDate()
    {
        $Order = new Order();
        $businessId = $this->getBusinessId();
        $res = $Order->getDeliveryDate($businessId);
        dump($res);
    }

    //根据筛选日期获取初始化数据
    public function iniData()
    {
        //1.获取对应日期的客户

        //2.获取对应配送日期的司机

        //3.获取对应日期默认全部的司机的已加工订单数量和总的加工订单数量
        //4.获取对应日期全部的已加工订单数量和总的加工订单数量
    }


}
