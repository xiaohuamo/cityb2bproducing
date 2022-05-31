<?php

declare (strict_types = 1);

namespace app\product\controller;

use think\App;
use think\facade\Cookie;
use think\Model;
use app\model\{
    User,
    Supplier,
    StaffRoles
};

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
                    if ($user['role'] == 20) {//判断员工具体的职位是否有权限登录
                        $StaffRoles = new StaffRoles();
                        $isPermission = $StaffRoles->getProductPermission($user['id']);
                        if (!empty($isPermission)) {
                            $roles = array_filter(explode(",", $isPermission['roles']));
                        }else{
                            $roles = [];
                        }
                    } else {
                        $roles = [];
                    }
                    $user['roles'] = $roles;
                    if($user['role'] == 20){
                        $business_id = $user['user_belong_to_user'];
                    } else {
                        $business_id = $user['id'];
                    }
                    //查询该商家的权限
                    $Supplier = new Supplier();
                    $pannel_arr = $Supplier->getCompanyPermission($business_id);
                    //判断当前域名
                    $SERVER_NAME = $_SERVER['HTTP_HOST'];
                    if($SERVER_NAME == M_SERVER_NAME){
                        if(in_array(1,$pannel_arr)||in_array(2,$pannel_arr)){
                            if($user['role'] == 3 || in_array(0,$roles) || in_array(1,$roles) || in_array(9,$roles) || in_array(11,$roles)) {
                                //如果既有生产权限又有预生产权限或者只有生产权限，则直接进入生产端
                                if(in_array(1,$pannel_arr)&&in_array(2,$pannel_arr)||in_array(1,$pannel_arr)&&!in_array(2,$pannel_arr)){
                                    return redirect('index')->send();
                                }else{
                                    return redirect('preProduct')->send();
                                }
                            }
                        }
                    } elseif ($SERVER_NAME == D_SERVER_NAME) {
                        //判断该商家是否有拣货权限
                        if(in_array(3,$pannel_arr)||in_array(4,$pannel_arr)) {
                            //如果当前使拣货员页面，判断是否有拣货权限
                            if ($user['role'] == 3 || in_array(0, $user['roles']) || in_array(1, $user['roles']) || in_array(9, $user['roles']) || in_array(12, $user['roles'])) {
                                if(in_array(3,$pannel_arr)&&in_array(4,$pannel_arr)||in_array(3,$pannel_arr)&&!in_array(4,$pannel_arr)) {
                                    return redirect('picking')->send();
                                }else{
                                    return redirect('pickingOrder')->send();
                                }
                            }
                        }
                    } elseif ($SERVER_NAME == DRIVER_SERVER_NAME) {
//                        return redirect('me')->send();
                    } else {

                    }
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

    /**
     * 清空cookie
     */
    public function clearPincodeCookie()
    {
        Cookie::delete('user_name');
    }
}
