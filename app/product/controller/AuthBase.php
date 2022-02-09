<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\model\User;
use think\App;

class AuthBase extends Base
{
    function __construct(App $app) {
        parent::__construct($app);
    }

    protected function initialize()
    {
        $this->authCheck();
    }


    //权限校验
    public function authCheck()
    {
        $member_user_id = $this->getMemberUserId();
        //校验当前用户信息是否正确，不正确跳转到登录页面，重新登录
        $map['id'] = $member_user_id;
        $user = User::is_exist($map,'id,name,nickname,password,role,user_belong_to_user,isApproved,isSuspended');
        if (!$user) {
            $this->clearCookie();
            return redirect('login')->send();
        }
        //校验加密的信息和用户信息是否一致，不一致，跳转到登录页面
        $encrypt_data = md5( $user['id'].$user['name'].$user['password']);
        $cookie_data = $this->getMemberUserShell();
        if ($encrypt_data !== $cookie_data) {
            $this->clearCookie();
            return redirect('login')->send();
        }
        //校验供应商id是否一致
        $business_id = $this->getBusinessId();
        if ($user['role'] == 3) {
            if ($user['id'] != $business_id) {
                $this->clearCookie();
                return redirect('login')->send();
            }
        } else {
            if ($user['user_belong_to_user'] != $business_id) {
                $this->clearCookie();
                return redirect('login')->send();
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

}
