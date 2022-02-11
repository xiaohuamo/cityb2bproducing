<?php
declare (strict_types = 1);

namespace app\product\controller;

use think\facade\Cookie;
use app\product\validate\LoginValidate;
use think\facade\View;
use app\model\{
    User,
    StaffRoles
};

class Login extends Base
{
    /**
     * 显示登录页面
     *
     * @return \think\Response
     */
    public function index()
    {
        // 模板输出
        return View::fetch('index');
    }

    //账号密码登录
    public function loginByPassword()
    {
        //接收参数
        $param = $this->request->only(['name','pwd','remember']);

        //1.验证数据
        //validate验证机制
        $validate = new LoginValidate();
        if (!$validate->scene('loginByPassword')->check($param)) {
            return show(config('status.code')['param_error']['code'], $validate->getError());
        }
        $name = trim($param['name']);
        $pwd = trim($param['pwd']);
        $remember = (int)$param['remember'];
        if(strlen($pwd)<=16) {
            $passwordByCustomMd5 = encryptdata($pwd);
        }else{
            $passwordByCustomMd5 = $pwd;
        }

        //2.查询用户是否存在
        $map['name'] = $name;
        $user = User::is_exist($map,'id,name,nickname,password,role,user_belong_to_user,isApproved,isSuspended,pincode');
        if (!$user) {
            return show(config('status.code')['account_or_pwd_error']['code'],config('status.code')['account_or_pwd_error']['msg']);
        }

        //3.验证密码
        if ($passwordByCustomMd5 != $user['password']) {
            return show(config('status.code')['account_or_pwd_error']['code'],config('status.code')['account_or_pwd_error']['msg']);
        }

        $StaffRoles = new StaffRoles();
        //4.判断用户是否有权限可以登录生产页面(role=20-员工，role=3-owner)
        if (in_array($user['role'],[3,20])) {
            if ($user['role'] == 20) {//判断员工具体的职位是否有权限登录
                $isPermission = $StaffRoles->getProductPermission($user['id']);
                if (!$isPermission) {
                    return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
                }
            }
        } else {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        //5.判断用户是否可以登录
        if (!$user['isApproved']) {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        //6.会员暂停，请联系ubonus
        if ($user['isSuspended']) {
            return show(config('status.code')['account_suspend_error']['code'],config('status.code')['account_suspend_error']['msg']);
        }

        //7.登录成功，存储cookie信息
        Cookie::set('remember', $remember, 60 * 60 * 24 * 365);
        if ($remember) {
            Cookie::set('remember_user_id', $user['id'], 60 * 60 * 24 * 365);
            Cookie::set('remember_user_shell', md5( $user['id'].$user['name'].$user['password'] ), 60 * 60 * 24 * 365);
            if($user['role'] == 20){
                $business_id = $user['user_belong_to_user'];
            } else {
                $business_id = $user['id'];
            }
            Cookie::set('business_id', $business_id, 60 * 60 * 24 * 365);
            if($user['pincode']){
                $user_name = $user['nickname'] ?: $user['name'];
                Cookie::set('user_name',$user_name);
            }
        }
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg']);
    }
}
