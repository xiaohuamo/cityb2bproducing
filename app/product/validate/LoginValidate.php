<?php
declare (strict_types = 1);

namespace app\product\validate;

use think\Validate;

class LoginValidate extends Validate
{

    protected $rule = [
        'name' => 'require',//账号
        'pwd'  =>  'require',//密码
        'remember' =>  'require',//是否记住账号密码
        'pincode' => 'require|number|length:4'//pincode
    ];

    protected $scene = [
        'loginByPassword' => ['name','pwd','remember'],//密码登录
        'loginByPincode' => ['pincode'],//pincode登录
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
    ];
}
