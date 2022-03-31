<?php
declare (strict_types = 1);

namespace app\product\controller;

use think\App;
use think\facade\View;
use app\model\{
    User,
    StaffRoles
};

class AuthBase extends Base
{
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
        if ($encrypt_data != $cookie_data) {
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
            $roles = [];
        } else {
            if ($user['user_belong_to_user'] != $business_id) {
                $this->clearCookie();
                return redirect('login')->send();
            }
            $StaffRoles = new StaffRoles();
            $isPermission = $StaffRoles->getProductPermission($user['id']);
            $roles = array_filter(explode(",", $isPermission['roles']));
        }
        //判断当前用户页面是否有权限
        $controller = preg_replace_callback('/\.[A-Z]/', function ($d) {
            return strtolower($d[0]);
        }, $this->request->controller(), 1);
        $modulename = app()->http->getName();
        $controllername = parseName($controller);
        $actionname = strtolower($this->request->action());
        // 当前页面路径
        $action = $modulename.'/'.$controllername.'/'.$actionname;
        // 定义方法白名单
        $allow = [
            'product/index/index',      //生产加工页面
            'product/pre_product/index',//预加工页面
            'product/picking/index',    //拣货员页面
        ];
        if (in_array($action, $allow)) {
            //判断当前域名,是否和操作页面相符合
            $SERVER_NAME = $_SERVER['HTTP_HOST'];
            if ($action == $allow[2]) {
                if($SERVER_NAME != D_SERVER_NAME){
                    $this->_empty();
//                    return redirect(D_SITE_URL.'product/picking')->send();
                }
            } else {
                if($SERVER_NAME != M_SERVER_NAME){
                    $this->_empty();
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
     * @param string $template
     * @return string
     * @throws \Exception
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @describe:
     */
    protected function fetch(string $template = '')
    {
        return View::fetch($template);
    }

    /**
     * @param string $msg
     * @throws \Exception
     * @name: exception
     * @describe:
     */
    protected function error403()
    {
        exit($this->fetch('public/error-403'));
    }

    /**
     * @param string $msg
     * @throws \Exception
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @name: exception
     * @describe:
     */
    protected function exception($msg = '无法打开页面')
    {
        $this->assign(compact('msg'));
        exit($this->fetch('public/exception'));
    }


    /**
     * @return string
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @name: makeToken
     * @describe:生成一个不会重复的字符串
     */
    public function makeToken()
    {
        $str = md5(uniqid(md5(microtime(true)), true)); //
        $str = sha1($str); //加密
        return $str;
    }

    /**
     * @param $name
     * @throws \Exception
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @name: _empty
     * @describe:
     */
    public function _empty()
    {
        exit($this->fetch('public/error-404'));
    }



    /**
     * @param string $msg
     * @param int $url
     * @return \think\response\Json
     * @throws \Exception
     * @author: LuckyHhy <jackhhy520@qq.com>
     * @describe:错误提醒页面
     */
    protected function failed($msg = '哎呀…亲…您访问的页面出现错误', $url = 0)
    {
        if ($this->request->isAjax()) {
            return self::JsonReturn($msg,0,$url);
        } else {
            $this->assign(compact('msg', 'url'));
            exit($this->fetch('public/error'));
        }
    }

}
