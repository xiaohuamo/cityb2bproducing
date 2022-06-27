<?php
declare (strict_types = 1);

namespace app\driver\controller;

use app\driver\service\JwtService;
use app\driver\validate\LoginValidate;
use think\facade\Console;
use app\model\{
    User,
    StaffRoles,
    Order,
    ProducingProgressSummery,
    DispatchingProgressSummery,
    Truck
};

class Login extends Base
{
    /**
     * 显示登录页面
     *
     * @return \think\Response
     */
    public function login()
    {
        // 模板输出
        return $this->fetch('index');
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

        //4.判断用户是否可以登录
        if (!$user['isApproved']) {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        //5.会员暂停，请联系ubonus
        if ($user['isSuspended']) {
            return show(config('status.code')['account_suspend_error']['code'],config('status.code')['account_suspend_error']['msg']);
        }

        $StaffRoles = new StaffRoles();
        $truck = new Truck();
        //6.判断用户是否有权限可以登录生产页面(role=20-员工，role=3-owner)
        if (in_array($user['role'],[3,20])) {
            if ($user['role'] == 20) {//判断员工具体的职位是否有权限登录
                $isPermission = $StaffRoles->getProductPermission($user['id']);
                if (empty($isPermission)) {
                    return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
                }
                $roles = array_filter(explode(",",$isPermission['roles']));
            } else {
                $roles = [];
            }
            $user['roles'] = $roles;
        } else {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        if($user['role'] == 20){
            $business_id = $user['user_belong_to_user'];
        } else {
            $business_id = $user['id'];
        }
        //登录成功生成用户token
        //7.获取jwt的句柄
        $jwtAuth = JwtService::getInstance();
        $token = $jwtAuth->setBusinessId($business_id)->setUid($user['id'])->encode()->getToken();
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],['token'=>$token]);
    }
}
