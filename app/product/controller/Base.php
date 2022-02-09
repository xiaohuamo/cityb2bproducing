<?php

declare (strict_types = 1);

namespace app\product\controller;

use think\App;
use app\model\User;
use think\facade\Cookie;

/**
 * 控制器基础类
 */
abstract class Base
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 构造方法
     * @access public
     * @param  App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        //获取当前访问地址
        $path = $this->request->baseUrl();
        $path_arr = explode('/',$path);
        $action = $path_arr[count($path_arr)-1];
        if ($action == 'login') {
            //校验用户已登录的话，直接跳转到生产页面
            $member_user_id = Cookie::get('remember_user_id');
            //校验当前用户信息是否正确，不正确跳转到登录页面，重新登录
            $map['id'] = $member_user_id;
            $user = User::is_exist($map);
            if ($user) {
                //校验加密的信息和用户信息是否一致，一致，跳转到生产页面
                $encrypt_data = md5( $user['id'].$user['name'].$user['password']);
                $cookie_data = Cookie::get('remember_user_shell');
                if ($encrypt_data == $cookie_data) {
                    return redirect('index')->send();
                }
            }
        }
    }

    /**
     * 自定义重定向方法
     * @param $args
     */
    public function redirect($args)
    {
        // 此处 throw new HttpResponseException 抛出异常重定向
        throw new HttpResponseException(redirect($args));
    }

    /**
     * 清空cookie
     */
    public function clearCookie()
    {
        Cookie::delete('remember');
        Cookie::delete('remember_user_id');
        Cookie::delete('remember_user_shell');
        Cookie::delete('business_id');
        Cookie::delete('user_pincode_error');
        Cookie::delete('user_name');
    }

    public function getBusinessId()
    {
        return Cookie::get('business_id');
    }

    public function getMemberUserId()
    {
        return Cookie::get('remember_user_id');
    }

    public function getMemberUserShell()
    {
        return Cookie::get('remember_user_shell');
    }

    public function getUserPincodeError()
    {
        return Cookie::get('user_pincode_error');
    }

    public function getUserName()
    {
        return Cookie::get('user_name');
    }
}
