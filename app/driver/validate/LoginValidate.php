<?php
declare (strict_types = 1);

namespace app\driver\validate;

use think\Validate;

class LoginValidate extends Validate
{

    protected $rule = [
        'name' => 'require',//账号
        'pwd'  =>  'require',//密码
        'remember' =>  'require',//是否记住账号密码
        'pincode' => 'require|number|length:4',//pincode
        'new_pincode' => 'require|number|length:4',//新pincode
//        'sure_pincode' => 'require|number|length:4|confirm:new_pincode',//确认pincode
        'sure_pincode' => 'require|number|length:4'//确认pincode
    ];

    protected $scene = [
        'loginByPassword' => ['name','pwd','remember'],//密码登录
        'loginByPincode' => ['pincode'],//pincode登录
        'setPincode' => ['new_pincode','sure_pincode'],//设置pincode
        'editPincode' => ['pincode','new_pincode','sure_pincode'],//修改pincode
    ];

    /**
     * 错误信息
    */
    protected $message = [
        'name.require' => 'Please input user name',
        'pwd.require' => 'Please input password',
        'remember.require' => 'Please check remember me',
        'pincode.require' => 'Please input pincode',
        'pincode.number' => 'Please enter the number',
        'pincode.length' => 'Please enter a 4-digit code',
        'new_pincode.require' => 'Please input pincode',
        'new_pincode.number' => 'Please enter the number',
        'new_pincode.length' => 'Please enter a 4-digit code',
        'sure_pincode.require' => 'Please input pincode',
        'sure_pincode.number' => 'Please enter the number',
        'sure_pincode.length' => 'Please enter a 4-digit code',
        'sure_pincode.confirm' => 'The pincode entered twice do not match',
    ];
}
