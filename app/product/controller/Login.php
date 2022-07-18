<?php
declare (strict_types = 1);

namespace app\product\controller;

use app\product\service\BoxNumber;
use think\facade\Cookie;
use app\product\validate\LoginValidate;
use think\facade\Console;
use think\facade\View;
use think\facade\Db;
use app\model\{
    User,
    StaffRoles,
    Order,
    ProducingProgressSummery,
    DispatchingProgressSummery,
    Truck,
    Supplier
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

        //4.判断用户是否可以登录
        if (!$user['isApproved']) {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        //5.会员暂停，请联系ubonus
        if ($user['isSuspended']) {
            return show(config('status.code')['account_suspend_error']['code'],config('status.code')['account_suspend_error']['msg']);
        }

        $StaffRoles = new StaffRoles();
        $Supplier = new Supplier();
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
        //查询该商家的权限
        $pannel_arr = $Supplier->getCompanyPermission($business_id);
        //判断当前域名
        $SERVER_NAME = $_SERVER['HTTP_HOST'];
        if($SERVER_NAME == M_SERVER_NAME){
            //判断该商家是否有生产权限
            if(in_array(1,$pannel_arr)||in_array(2,$pannel_arr)){
                //如果当前使生产页面，判断是否有生产权限
                if($user['role'] == 3 || in_array(0,$user['roles']) || in_array(1,$user['roles']) || in_array(9,$user['roles']) || in_array(11,$user['roles'])){
                    $user['redirect_type'] = 1;
                    //如果既有生产权限又有预生产权限或者只有生产权限，则直接进入生产端
                    if(in_array(1,$pannel_arr)&&in_array(2,$pannel_arr)||in_array(1,$pannel_arr)&&!in_array(2,$pannel_arr)){
                        $user['redirect_url'] = M_SITE_URL.'product/index';
                    }else{
                        $user['redirect_url'] = M_SITE_URL.'product/preProduct';
                    }
                } else {
                    return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
                }
            }else{
                return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
            }
        } elseif ($SERVER_NAME == D_SERVER_NAME) {
            //判断该商家是否有拣货权限
            if(in_array(3,$pannel_arr)||in_array(4,$pannel_arr)){
                //如果当前使拣货员页面，判断是否有拣货权限
                if ($user['role'] == 3 || in_array(0, $user['roles']) || in_array(1, $user['roles']) || in_array(9, $user['roles']) || in_array(12, $user['roles'])) {
                    $user['redirect_type'] = 2;
                    if(in_array(3,$pannel_arr)&&in_array(4,$pannel_arr)||in_array(3,$pannel_arr)&&!in_array(4,$pannel_arr)) {
                        $user['redirect_url'] = D_SITE_URL . 'product/picking';
                    }else{
                        $user['redirect_url'] = D_SITE_URL . 'product/pickingOrder';
                    }
                } else {
                    return show(config('status.code')['account_approved_error']['code'], config('status.code')['account_approved_error']['msg']);
                }
            }else{
                return show(config('status.code')['account_approved_error']['code'], config('status.code')['account_approved_error']['msg']);
            }
        } elseif ($SERVER_NAME == DRIVER_SERVER_NAME) {
            //如果当前使拣货员页面，判断是否有拣货权限
            if(in_array(16,$user['roles'])){
                $user['redirect_type'] = 3;
                $user['redirect_url'] = DRIVER_SITE_URL.'product/me';
            } else {
                return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
            }
            $truck = new Truck();
            $truck_info = $truck->getTruckInfo($business_id,$user['id']);
        } else {
            return show(config('status.code')['account_approved_error']['code'],config('status.code')['account_approved_error']['msg']);
        }

        //7.登录成功，存储cookie信息
        Cookie::set('remember', $remember, 60 * 60 * 24 * 365);
        if ($remember) {
            Cookie::set('remember_user_id', $user['id'], 60 * 60 * 24 * 365);
            Cookie::set('remember_user_shell', md5( $user['id'].$user['name'].$user['password'] ), 60 * 60 * 24 * 365);
            Cookie::set('business_id', $business_id, 60 * 60 * 24 * 365);
            if($user['pincode']){
                $user_name = $user['nickname'] ?: $user['name'];
                Cookie::set('user_name',$user_name);
            }
        }
        //查询当前商家是否在汇总表中,如果不在，则更新数据
        if($SERVER_NAME == M_SERVER_NAME){
            $is_exist = ProducingProgressSummery::is_exist(['business_userId' => $business_id]);
            if(empty($is_exist)){
                $output = Console::call('producingprogresssummary', [(string)$business_id]);
            }
        } elseif ($SERVER_NAME == D_SERVER_NAME) {
            $is_exist = DispatchingProgressSummery::is_exist(['business_id' => $business_id]);
            if(empty($is_exist)){
                $output = Console::call('dispatchingprogresssummary', [(string)$business_id]);
            }
        } else {

        }
        $truck_info = $truck_info??[];
        return show(config('status.code')['success']['code'],config('status.code')['success']['msg'],['user'=>$user,'truck_info'=>$truck_info]);
    }


    public function test(){
//        $param = $this->request->only(['orderId','business_userId']);
//        $BoxNumber = new BoxNumber();
//        $order = $BoxNumber->getOrderBoxes($param['orderId'],$param['business_userId']);
//        halt($order);
        $dps_list = Db::name('dispatching_progress_summery')->field('id,orderId')->where(['isdeleted'=>0])->column('id','orderId');
        $orderId_arr = array_keys($dps_list);
        $order_list = Db::name('order')->field('logistic_schedule_id,logistic_truck_No,orderId')->where([
            ['orderId','in',$orderId_arr],
            ['logistic_schedule_id','>',0]
        ])->select()->toArray();
        $update = [];
        foreach ($order_list as $k=>$v){
            $update[$k] = [
                'id' => $dps_list[$v['orderId']],
                'logistic_schedule_id' => $v['logistic_schedule_id'],
                'truck_no' => $v['logistic_truck_No'],
                'orderId' => $v['orderId']
            ];
        }
        $DispatchingProgressSummery = new DispatchingProgressSummery();
        $res = $DispatchingProgressSummery->saveAll($update);
        halt($res);
    }
}
