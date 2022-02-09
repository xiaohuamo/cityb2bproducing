<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\product\validate\LoginValidate;
use app\model\User;
use think\facade\Cookie;
use think\facade\View;
use app\common\service\RedisService;
use think\Validate;

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
        $user = User::getOne($map, 'id,name,nickname,role,user_belong_to_user');
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

        //3.该供应商用户存在，比较当前登录用户和pincode是否一致，一致则可以直接登录，不一致需要跳转到登录页面还是怎么处理？？？
        if ($user['id'] == $this->getMemberUserId()) {
            $user_name = $user['nickname'] ?: $user['name'];
            Cookie::set('user_name',$user_name);
            $data = $this->getLoginInfo();
            return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
        } else {

        }
    }

    //获取用户登录信息
    public function loginInfo()
    {
        $data = $this->getLoginInfo();
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],$data);
    }

    //公共方法，获取用户登录信息
    public function getLoginInfo()
    {
        $user_name = $this->getUserName();
        $data = [
            "user_name" => $user_name
        ];
        return $data;
    }
}
